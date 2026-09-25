<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Course;
use App\Models\CourseEdition;
use App\Models\CourseSession;
use App\Models\Module;
use App\Models\Student;
use App\Models\StudentModuleProgress;
use Illuminate\Support\Collection;

/**
 * Logica centrale del registro di frequenza.
 *
 * FAD asincrona: l'heartbeat accredita il tempo REALMENTE trascorso dall'ultimo
 * ping (cap anti-frode: max HEARTBEAT_MAX_INTERVAL secondi per ping, e mai oltre
 * la durata del modulo). Un modulo si può marcare completato solo dopo aver
 * tracciato almeno MIN_COMPLETION_FRACTION della sua durata. Al completamento si
 * scrive UN record di presenza con le ore effettive (tempo tracciato, cap durata).
 */
class AttendanceService
{
    /** Secondi massimi accreditabili per singolo ping (il client pinga ~ogni 30s). */
    private const HEARTBEAT_MAX_INTERVAL = 90;
    /** Frazione della durata modulo da tracciare per poter completare. */
    private const MIN_COMPLETION_FRACTION = 0.8;
    /** Debounce dei log di accesso: un nuovo "accesso" solo dopo N minuti di stacco. */
    private const ACCESS_DEBOUNCE_MINUTES = 30;

    /**
     * Registra un ping di presenza sul modulo. Accredita solo il tempo reale
     * dall'ultimo ping (anti-frode), aggiornando il progresso.
     *
     * @return array{tracked_seconds:int, required_seconds:int, can_complete:bool}
     */
    public function heartbeat(Student $student, Module $module): array
    {
        $progress = StudentModuleProgress::firstOrCreate(
            ['student_id' => $student->id, 'module_id' => $module->id],
            ['status' => 'in_progress', 'started_at' => now(), 'tracked_seconds' => 0]
        );

        $now = now();
        $credit = 0;
        if ($progress->last_heartbeat_at) {
            $elapsed = $now->getTimestamp() - $progress->last_heartbeat_at->getTimestamp();
            $credit = max(0, min($elapsed, self::HEARTBEAT_MAX_INTERVAL));
        }

        // Mai oltre la durata dichiarata del modulo.
        if ($module->duration_minutes) {
            $residuo = max(0, $module->duration_minutes * 60 - $progress->tracked_seconds);
            $credit = min($credit, $residuo);
        }

        $progress->tracked_seconds += $credit;
        $progress->time_spent_minutes = intdiv($progress->tracked_seconds, 60);
        $progress->last_heartbeat_at = $now;
        $progress->started_at ??= $now;
        if ($progress->status === 'not_started') {
            $progress->status = 'in_progress';
        }
        $progress->save();

        return [
            'tracked_seconds'  => $progress->tracked_seconds,
            'required_seconds' => $this->requiredSeconds($module),
            'can_complete'     => $this->minCompletionReached($progress, $module),
        ];
    }

    /** Secondi di tracciamento richiesti per completare il modulo (0 = nessun gate). */
    public function requiredSeconds(Module $module): int
    {
        return $module->duration_minutes
            ? (int) ceil($module->duration_minutes * 60 * self::MIN_COMPLETION_FRACTION)
            : 0;
    }

    public function minCompletionReached(?StudentModuleProgress $progress, Module $module): bool
    {
        $required = $this->requiredSeconds($module);
        if ($required === 0) {
            return true; // modulo senza durata dichiarata: nessun gate
        }

        return ($progress?->tracked_seconds ?? 0) >= $required;
    }

    /**
     * Scrive il record di presenza al completamento di un modulo (idempotente:
     * un solo record per modulo). Ore accreditate = tempo tracciato, cap durata.
     */
    public function creditModuleCompletion(Student $student, Course $course, Module $module): void
    {
        $already = AttendanceRecord::where('student_id', $student->id)
            ->where('module_id', $module->id)
            ->where('source', 'module_completion')
            ->exists();
        if ($already) {
            return;
        }

        $tracked = (int) (StudentModuleProgress::where('student_id', $student->id)
            ->where('module_id', $module->id)->value('tracked_seconds') ?? 0);
        $cap = $module->duration_minutes ? $module->duration_minutes * 60 : $tracked;
        $hours = round(min($tracked, $cap) / 3600, 2);

        AttendanceRecord::create([
            'student_id'   => $student->id,
            'course_id'    => $course->id,
            'type'         => 'async_activity',
            'source'       => 'module_completion',
            'module_id'    => $module->id,
            'occurred_at'  => now(),
            'hours_credited' => $hours,
            'meta'         => ['tracked_seconds' => $tracked, 'duration_minutes' => $module->duration_minutes],
        ]);
    }

    /**
     * Logga un accesso al modulo (traccia di attività, 0 ore), con debounce: un
     * nuovo record solo se l'ultimo accesso a quel modulo è più vecchio di N minuti.
     */
    public function logModuleAccess(Student $student, Course $course, Module $module, ?string $ip = null): void
    {
        $recent = AttendanceRecord::where('student_id', $student->id)
            ->where('module_id', $module->id)
            ->where('source', 'module_access')
            ->where('occurred_at', '>=', now()->subMinutes(self::ACCESS_DEBOUNCE_MINUTES))
            ->exists();
        if ($recent) {
            return;
        }

        AttendanceRecord::create([
            'student_id'  => $student->id,
            'course_id'   => $course->id,
            'type'        => 'async_activity',
            'source'      => 'module_access',
            'module_id'   => $module->id,
            'occurred_at' => now(),
            'hours_credited' => 0,
            'ip'          => $ip,
        ]);
    }

    // ---------------------------------------------------------------------
    // Sessioni sincrone (aula / live online): presenza segnata dal docente.
    // ---------------------------------------------------------------------

    /**
     * Appello del formatore su una giornata. Per ogni discente dell'edizione
     * (o del corso, per giornate senza edizione) scrive UN record con stato,
     * orari di entrata/uscita, nota e ore: anche gli assenti, a 0 ore.
     *
     * $marks[student_id] = ['status' => presente|assente|assente_giustificato,
     *   'arrived_at' => 'HH:MM'|null, 'left_at' => 'HH:MM'|null, 'note' => ?, 'hours' => ?]
     * Discenti senza voce in $marks: record rimosso (appello non ancora fatto).
     *
     * @return int  numero di presenti
     */
    public function markSessionAttendance(CourseSession $session, array $marks, ?string $markedBy = null): int
    {
        $studentIds = $session->course_edition_id
            ? $session->edition->students()->pluck('students.id')->all()
            : $session->course->students()->pluck('students.id')->all();
        $present = 0;

        foreach ($studentIds as $studentId) {
            $existing = AttendanceRecord::where('course_session_id', $session->id)
                ->where('student_id', $studentId)
                ->where('source', 'instructor_mark')
                ->first();

            $mark = $marks[$studentId] ?? null;
            if (! is_array($mark) || empty($mark['status'])) {
                $existing?->delete();
                continue;
            }

            $status = $mark['status'];
            $isPresent = $status === AttendanceRecord::STATUS_PRESENT;
            $present += $isPresent ? 1 : 0;

            $arrived = $isPresent ? $this->normalizeTime($mark['arrived_at'] ?? null) : null;
            $left = $isPresent ? $this->normalizeTime($mark['left_at'] ?? null) : null;
            $manual = $mark['hours'] ?? null;

            $payload = [
                'course_id'      => $session->course_id,
                'type'           => 'sync_session',
                'source'         => 'instructor_mark',
                'status'         => $status,
                'arrived_at'     => $arrived,
                'left_at'        => $left,
                'note'           => trim((string) ($mark['note'] ?? '')) ?: null,
                'marked_by'      => $markedBy,
                'occurred_at'    => $session->scheduled_at ?? now(),
                'hours_credited' => ! $isPresent ? 0
                    : ($manual !== null && $manual !== '' ? round((float) $manual, 2) : $this->sessionHours($session, $arrived, $left)),
            ];

            if ($existing) {
                $existing->update($payload);
            } else {
                AttendanceRecord::create($payload + [
                    'student_id'        => $studentId,
                    'course_session_id' => $session->id,
                ]);
            }
        }

        return $present;
    }

    /**
     * Ore svolte in una giornata: durata prevista meno ritardo e uscita
     * anticipata (orari fuori dalla giornata non contano).
     */
    public function sessionHours(CourseSession $session, ?string $arrived, ?string $left): float
    {
        if (! $session->scheduled_at) {
            return round(($session->duration_minutes ?? 0) / 60, 2);
        }

        $start = $session->scheduled_at->copy();
        $end = $session->endsAt();
        $from = $arrived ? $start->copy()->setTimeFromTimeString($arrived) : $start;
        $to = $left ? $start->copy()->setTimeFromTimeString($left) : $end;

        $from = $from->max($start);
        $to = $to->min($end);

        return round(max(0, $from->diffInMinutes($to, false)) / 60, 2);
    }

    /** Minuti di ritardo rispetto all'inizio della giornata (0 se puntuale). */
    public function lateMinutes(CourseSession $session, ?AttendanceRecord $record): int
    {
        if (! $record?->arrived_at || ! $session->scheduled_at) {
            return 0;
        }
        $arrived = $session->scheduled_at->copy()->setTimeFromTimeString($record->arrived_at);

        return max(0, (int) $session->scheduled_at->diffInMinutes($arrived, false));
    }

    /** Minuti di uscita anticipata rispetto alla fine della giornata. */
    public function earlyMinutes(CourseSession $session, ?AttendanceRecord $record): int
    {
        if (! $record?->left_at || ! $session->scheduled_at) {
            return 0;
        }
        $left = $session->scheduled_at->copy()->setTimeFromTimeString($record->left_at);

        return max(0, (int) $left->diffInMinutes($session->endsAt(), false));
    }

    private function normalizeTime(?string $time): ?string
    {
        $time = trim((string) $time);

        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time) ? $time : null;
    }

    /**
     * Registro dell'edizione: griglia discenti × giornate con stato, orari e
     * ore per cella, totali e percentuale di frequenza per discente, presenti
     * per giornata.
     *
     * @return array{days: Collection, rows: Collection, present_per_day: array<string,int>, planned_hours: float}
     */
    public function editionRegister(CourseEdition $edition): array
    {
        $days = $edition->days()->get();
        $planned = round($days->sum('duration_minutes') / 60, 2);
        $records = AttendanceRecord::whereIn('course_session_id', $days->pluck('id'))
            ->where('source', 'instructor_mark')
            ->get()
            ->groupBy('student_id');

        $presentPerDay = array_fill_keys($days->pluck('id')->all(), 0);

        $rows = $edition->students()->get()->map(function (Student $student) use ($days, $records, $planned, &$presentPerDay) {
            $byDay = $records->get($student->id, collect())->keyBy('course_session_id');
            $cells = [];
            $totals = ['present' => 0, 'absent' => 0, 'justified' => 0, 'late' => 0, 'early' => 0, 'unmarked' => 0];

            foreach ($days as $day) {
                $record = $byDay->get($day->id);
                $late = $this->lateMinutes($day, $record);
                $early = $this->earlyMinutes($day, $record);
                $cells[$day->id] = ['record' => $record, 'late' => $late, 'early' => $early];

                match ($record?->status) {
                    AttendanceRecord::STATUS_PRESENT => $totals['present']++,
                    AttendanceRecord::STATUS_ABSENT => $totals['absent']++,
                    AttendanceRecord::STATUS_JUSTIFIED => $totals['justified']++,
                    default => $totals['unmarked']++,
                };
                if ($record?->isPresent()) {
                    $presentPerDay[$day->id]++;
                    $totals['late'] += $late > 0 ? 1 : 0;
                    $totals['early'] += $early > 0 ? 1 : 0;
                }
            }

            $hours = round((float) $byDay->sum('hours_credited'), 2);

            return [
                'student' => $student,
                'cells'   => $cells,
                'hours'   => $hours,
                'planned' => $planned,
                'percent' => $planned > 0 ? round($hours / $planned * 100, 1) : 0.0,
                'totals'  => $totals,
            ];
        })->values();

        return ['days' => $days, 'rows' => $rows, 'present_per_day' => $presentPerDay, 'planned_hours' => $planned];
    }

    // ---------------------------------------------------------------------
    // Aggregazioni per il registro (service riusabile, non inline nei controller).
    // ---------------------------------------------------------------------

    /**
     * Registro di corso: una riga per studente iscritto con le ore maturate,
     * distinte tra sincrono (sessioni) e asincrono (FAD), e i totali.
     *
     * @return Collection<int, array{student:Student, sync_hours:float, async_hours:float, total_hours:float, sessions_attended:int, modules_completed:int, last_activity:?\Illuminate\Support\Carbon}>
     */
    public function courseRegister(Course $course): Collection
    {
        $records = AttendanceRecord::where('course_id', $course->id)->get();
        $byStudent = $records->groupBy('student_id');

        return $course->students()->orderBy('name')->get()->map(function (Student $student) use ($byStudent, $course) {
            $recs = $byStudent->get($student->id, collect());
            $sync = round((float) $recs->where('type', 'sync_session')->sum('hours_credited'), 2);
            $async = round((float) $recs->where('source', 'module_completion')->sum('hours_credited'), 2);

            return [
                'student'           => $student,
                'sync_hours'        => $sync,
                'async_hours'       => $async,
                // Il TOTALE dipende dalla modalità del corso: async → solo FAD,
                // sync → solo presenze, non impostata → entrambi (storico).
                'total_hours'       => $this->countedHours($course, $sync, $async),
                'sessions_attended' => $recs->where('source', 'instructor_mark')
                    ->where('status', AttendanceRecord::STATUS_PRESENT)->count(),
                'modules_completed' => $recs->where('source', 'module_completion')->count(),
                'last_activity'     => $recs->max('occurred_at'),
            ];
        })->values();
    }

    /** Ore che concorrono al totale in base alla modalità di erogazione del corso. */
    public function countedHours(Course $course, float $sync, float $async): float
    {
        if ($course->isAsync()) {
            return round($async, 2);
        }
        if ($course->isSync()) {
            return round($sync, 2);
        }

        return round($sync + $async, 2);
    }

    /**
     * Dettaglio cronologico della presenza di uno studente su un corso: la
     * lista completa dei record (sessioni, completamenti, accessi) ordinati.
     *
     * @return Collection<int, AttendanceRecord>
     */
    public function studentCourseDetail(Course $course, Student $student): Collection
    {
        return AttendanceRecord::where('course_id', $course->id)
            ->where('student_id', $student->id)
            ->with(['session', 'module'])
            ->orderBy('occurred_at')
            ->get();
    }
}
