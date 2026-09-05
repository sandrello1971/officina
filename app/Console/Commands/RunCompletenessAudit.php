<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Services\CompletenessAuditor;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Controllo di completezza della consegna: slide, sezione nel manuale formatore, HTML
 * integro, ordinamento, permessi filesystem. Puro/read-only, nessuna chiamata AI — a
 * differenza di Freshness e Gap Scout non ha interruttore di costo né cap: può girare
 * su tutti i corsi ogni volta.
 */
class RunCompletenessAudit extends Command
{
    protected $signature = 'course:completeness-audit {course? : Slug o ID del corso, altrimenti tutti}';

    protected $description = 'Verifica che ogni modulo abbia slide, materiali e manuale formatore coerenti, e che i permessi file siano corretti.';

    public function handle(CompletenessAuditor $auditor): int
    {
        $arg = $this->argument('course');
        $courses = match (true) {
            $arg === null => Course::active()->get(),
            Str::isUuid($arg) => Course::where('slug', $arg)->orWhere('id', $arg)->get(),
            default => Course::where('slug', $arg)->get(),
        };

        if ($courses->isEmpty()) {
            $this->error('Nessun corso trovato.');
            return self::FAILURE;
        }

        foreach ($courses as $course) {
            $r = $auditor->auditAndPersist($course);
            $this->info("{$course->name}: {$r['created']} nuovi, {$r['still_open']} ancora aperti, {$r['resolved']} risolti.");
        }

        return self::SUCCESS;
    }
}
