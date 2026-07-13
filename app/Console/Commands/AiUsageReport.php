<?php

namespace App\Console\Commands;

use App\Models\AiUsage;
use Illuminate\Console\Command;

/**
 * Report dei costi/token delle chiamate AI (tabella ai_usage), per attribuire la
 * spesa Anthropic a feature / corso / scuola. Base per il pricing multi-tenant.
 *
 *   php artisan ai:usage                         # ultimi 30 giorni, per feature
 *   php artisan ai:usage --days=7                # finestra diversa
 *   php artisan ai:usage --feature=quiz.generate # filtra una feature
 *   php artisan ai:usage --school=<uuid>         # filtra un tenant
 *   php artisan ai:usage --by=school|course|day  # raggruppa diversamente
 */
class AiUsageReport extends Command
{
    protected $signature = 'ai:usage {--days=30} {--feature=} {--school=} {--by=feature : feature|school|course|day}';

    protected $description = 'Report costi/token delle chiamate AI (da ai_usage)';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $since = now()->subDays($days);

        $base = AiUsage::where('created_at', '>=', $since);
        if ($f = $this->option('feature')) {
            $base->where('feature', $f);
        }
        if ($s = $this->option('school')) {
            $base->where('school_id', $s);
        }

        $by = $this->option('by');
        $col = match ($by) {
            'school' => 'school_id',
            'course' => 'course_id',
            'day'    => 'created_at::date',
            default  => 'feature',
        };

        $rows = (clone $base)
            ->selectRaw("$col as k,
                COUNT(*) as n,
                SUM(CASE WHEN status='error' THEN 1 ELSE 0 END) as errors,
                SUM(tokens_in) as ti, SUM(tokens_out) as too,
                SUM(cost_usd) as cost")
            ->groupByRaw($col)
            ->orderByRaw('cost DESC NULLS LAST')
            ->get();

        if ($rows->isEmpty()) {
            $this->warn("Nessun utilizzo AI registrato negli ultimi {$days} giorni.");
            return self::SUCCESS;
        }

        $this->info("Utilizzo AI — ultimi {$days} giorni — raggruppato per: {$by}");

        $this->table(
            [ucfirst($by), 'Chiamate', 'Errori', 'Token in', 'Token out', 'Costo $'],
            $rows->map(fn ($r) => [
                $r->k ?? '—',
                number_format((int) $r->n),
                (int) $r->errors,
                number_format((int) $r->ti),
                number_format((int) $r->too),
                $r->cost === null ? 'n/d' : '$' . number_format((float) $r->cost, 4),
            ])->all()
        );

        $tot = (clone $base)->selectRaw('COUNT(*) n, SUM(tokens_in) ti, SUM(tokens_out) too, SUM(cost_usd) cost')->first();
        $this->line('');
        $this->info(sprintf(
            'TOTALE: %s chiamate · %s token in · %s token out · costo stimato %s',
            number_format((int) $tot->n),
            number_format((int) $tot->ti),
            number_format((int) $tot->too),
            $tot->cost === null ? 'n/d' : '$' . number_format((float) $tot->cost, 2)
        ));
        $this->comment('Nota: i costi usano il listino in config/services.php (services.anthropic.prices) — verificarlo col listino reale.');

        return self::SUCCESS;
    }
}
