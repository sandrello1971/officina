<?php

namespace App\Console\Commands;

use App\Models\Material;
use App\Services\InstructorManualSplitterService;
use Illuminate\Console\Command;

/**
 * Backfill di estimated_minutes sulle sezioni dei manuali formatore già
 * splittati (introdotto dopo la creazione di split()). Non ricrea le
 * sezioni: aggiorna solo il campo, preservando anchor/module_id/note legate.
 *
 *   php artisan manuali:estimate-durations
 *   php artisan manuali:estimate-durations --course=FREQUENZA
 */
class EstimateInstructorManualDurations extends Command
{
    protected $signature = 'manuali:estimate-durations {--course= : Nome (substring) o id del corso}';

    protected $description = 'Calcola/ricalcola la durata stimata dei capitoli dei manuali formatore già splittati';

    public function handle(InstructorManualSplitterService $splitter): int
    {
        $query = Material::where('is_instructor_only', true)->whereNotNull('sections_extracted_at');

        if ($courseOpt = $this->option('course')) {
            $ids = \App\Models\Course::where('name', 'ilike', '%' . $courseOpt . '%')
                ->when(\Illuminate\Support\Str::isUuid($courseOpt), fn ($q) => $q->orWhere('id', $courseOpt))
                ->pluck('id');
            $query->whereIn('course_id', $ids);
        }

        $materials = $query->get();

        if ($materials->isEmpty()) {
            $this->warn('Nessun manuale formatore già splittato per il filtro dato.');
            return self::SUCCESS;
        }

        $totSections = 0;
        foreach ($materials as $material) {
            $n = $splitter->recalculateEstimatedMinutes($material);
            $this->line("■ {$material->title} — {$n} sezioni aggiornate");
            $totSections += $n;
        }

        $this->info("TOTALE: {$materials->count()} manuali, {$totSections} sezioni con durata stimata.");

        return self::SUCCESS;
    }
}
