<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Course;
use App\Models\Module;
use App\Models\Student;
use App\Models\StudentModuleProgress;

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
}
