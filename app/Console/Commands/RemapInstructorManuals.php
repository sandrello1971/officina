<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\Material;
use App\Services\InstructorManualRemapService;
use Illuminate\Console\Command;

/**
 * Ri-mappa le sezioni dei manuali formatore ai moduli discente con l'euristica
 * corretta (etichetta-unità dal titolo, non sort_order) e, con --ai, un fallback
 * Claude per tema. Le mappature manuali sono preservate. --dry-run non scrive.
 *
 *   php artisan manuali:remap --dry-run            # anteprima, tutti i manuali
 *   php artisan manuali:remap --dry-run --ai       # anteprima incl. proposte AI
 *   php artisan manuali:remap --ai                 # applica (euristica + AI)
 *   php artisan manuali:remap --course=FREQUENZA   # limita a un corso (nome o id)
 */
class RemapInstructorManuals extends Command
{
    protected $signature = 'manuali:remap {--course= : Nome (substring) o id del corso} {--ai : Fallback AI per le sezioni non risolte} {--dry-run : Non scrive, mostra solo}';

    protected $description = 'Ri-mappa le sezioni dei manuali formatore ai moduli discente (euristica + AI opzionale)';

    public function handle(InstructorManualRemapService $service): int
    {
        $dry = (bool) $this->option('dry-run');
        $ai = (bool) $this->option('ai');

        $query = Material::where('is_instructor_only', true);
        if ($courseOpt = $this->option('course')) {
            $ids = Course::where('name', 'ilike', '%' . $courseOpt . '%')
                ->when(\Illuminate\Support\Str::isUuid($courseOpt), fn ($q) => $q->orWhere('id', $courseOpt))
                ->pluck('id');
            $query->whereIn('course_id', $ids);
        }
        $materials = $query->get();

        if ($materials->isEmpty()) {
            $this->warn('Nessun manuale formatore trovato per il filtro dato.');
            return self::SUCCESS;
        }

        $this->info(sprintf('%s%s — %d manuale/i', $dry ? '[DRY-RUN] ' : '', $ai ? '[AI ON] ' : '', $materials->count()));

        $totChanges = 0;
        $totUnmapped = 0;
        $totAi = 0;

        foreach ($materials as $material) {
            $r = $service->remap($material, $ai, $dry);

            $this->line('');
            $this->line("<comment>■ {$r['material']}</comment>  (sezioni: {$r['total']}, manuali preservate: {$r['manual_kept']})");

            if (empty($r['changes'])) {
                $this->line('  nessuna variazione.');
            } else {
                foreach ($r['changes'] as $c) {
                    $from = $c['from'] ?? '—';
                    $to = $c['to'] ?? '— (rimossa)';
                    $this->line("  • {$c['section']}");
                    $this->line("      {$from}  →  {$to}");
                }
            }
            $this->line("  <info>variazioni: " . count($r['changes'])
                . ($r['ai_used'] ? ", assegnate da AI: {$r['ai_assigned']}" : '')
                . ", non mappate dopo: {$r['unmapped_after']}</info>");

            $totChanges += count($r['changes']);
            $totUnmapped += $r['unmapped_after'];
            $totAi += $r['ai_assigned'];
        }

        $this->line('');
        $this->info(sprintf(
            '%s TOTALE: %d variazioni%s, %d ancora non mappate.',
            $dry ? 'ANTEPRIMA' : 'APPLICATO',
            $totChanges,
            $ai ? " (di cui {$totAi} da AI)" : '',
            $totUnmapped
        ));
        if ($dry) {
            $this->comment('Nessuna scrittura: rilancia senza --dry-run per applicare.');
        }

        return self::SUCCESS;
    }
}
