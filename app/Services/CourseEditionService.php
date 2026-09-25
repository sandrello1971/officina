<?php

namespace App\Services;

use App\Models\CourseEdition;
use App\Models\CourseSession;
use App\Models\StudentCourse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Pianificazione delle edizioni di un corso: generazione delle giornate e
 * gestione dei discenti. La logica vive qui, i controller admin e formatore
 * la condividono.
 */
class CourseEditionService
{
    /**
     * Genera $days giornate a partire da $firstDate, alle $startTime, di
     * $hoursPerDay ore, solo nei giorni della settimana indicati (ISO 1=lun…7=dom).
     * Le giornate si accodano a quelle eventualmente già presenti.
     *
     * @param  int[]  $weekdays
     * @return int  giornate create
     */
    public function planDays(CourseEdition $edition, string $firstDate, int $days, string $startTime, float $hoursPerDay, array $weekdays = [1, 2, 3, 4, 5], ?string $createdBy = null): int
    {
        $weekdays = array_values(array_unique(array_map('intval', $weekdays))) ?: [1, 2, 3, 4, 5];
        $date = Carbon::parse($firstDate)->startOfDay();
        $number = (int) $edition->days()->max('day_number');
        $created = 0;

        // Limite di sicurezza sui giorni scanditi (es. un solo giorno della settimana su tanti mesi).
        for ($guard = 0; $created < $days && $guard < 3660; $guard++, $date->addDay()) {
            if (! in_array($date->dayOfWeekIso, $weekdays, true)) {
                continue;
            }
            $number++;
            $created++;
            $this->createDay($edition, $number, $date->copy()->setTimeFromTimeString($startTime), (int) round($hoursPerDay * 60), $createdBy);
        }

        return $created;
    }

    public function createDay(CourseEdition $edition, int $number, Carbon $at, int $minutes, ?string $createdBy = null): CourseSession
    {
        return CourseSession::create([
            'course_id' => $edition->course_id,
            'course_edition_id' => $edition->id,
            'day_number' => $number,
            'title' => "Giornata {$number}",
            'scheduled_at' => $at,
            'duration_minutes' => $minutes,
            'modality' => $edition->modality === 'live_online' ? 'live_online' : 'in_person',
            'location' => $edition->location,
            'created_by' => $createdBy,
        ]);
    }

    /** Rinumera le giornate in ordine di calendario (dopo modifiche o cancellazioni). */
    public function renumberDays(CourseEdition $edition): void
    {
        foreach ($edition->days()->get()->values() as $i => $day) {
            $n = $i + 1;
            // Il titolo si aggiorna solo se è quello automatico: uno personalizzato resta.
            $title = preg_match('/^Giornata \d+$/', (string) $day->title) ? "Giornata {$n}" : $day->title;
            $day->update(['day_number' => $n, 'title' => $title]);
        }
    }

    /**
     * Associa discenti all'edizione e li iscrive al corso (così vedono i
     * contenuti). Chi è già in un'altra edizione dello stesso corso è escluso.
     *
     * @param  string[]  $studentIds
     * @return array{added: int, skipped: string[]}  skipped = id già in un'altra edizione
     */
    public function addStudents(CourseEdition $edition, array $studentIds): array
    {
        $studentIds = array_values(array_unique(array_filter($studentIds)));
        $taken = DB::table('course_edition_students')
            ->where('course_id', $edition->course_id)
            ->whereIn('student_id', $studentIds)
            ->where('course_edition_id', '!=', $edition->id)
            ->pluck('student_id')->all();
        $already = $edition->students()->pluck('students.id')->all();
        $added = 0;

        foreach (array_diff($studentIds, $taken, $already) as $studentId) {
            DB::transaction(function () use ($edition, $studentId) {
                $edition->students()->attach($studentId, ['course_id' => $edition->course_id]);
                StudentCourse::firstOrCreate(
                    ['student_id' => $studentId, 'course_id' => $edition->course_id],
                    ['enrolled_at' => now(), 'is_active' => true]
                );
            });
            $added++;
        }

        return ['added' => $added, 'skipped' => array_values($taken)];
    }

    /** Toglie il discente dall'edizione (resta iscritto al corso) e cancella i suoi appelli. */
    public function removeStudent(CourseEdition $edition, string $studentId): void
    {
        DB::transaction(function () use ($edition, $studentId) {
            \App\Models\AttendanceRecord::whereIn('course_session_id', $edition->days()->pluck('id'))
                ->where('student_id', $studentId)
                ->where('source', 'instructor_mark')
                ->delete();
            $edition->students()->detach($studentId);
        });
    }
}
