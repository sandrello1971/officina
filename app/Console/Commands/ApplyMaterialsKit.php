<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\Material;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Applica un kit di correzioni ai materiali (resources/course-kits/<kit>/manifest.json):
 *
 *  files     — file in storage da sostituire con files/<path>; sha256_before deve
 *              combaciare con il file attuale (altrimenti qualcuno l'ha cambiato)
 *  new_files — file nuovi da scrivere da files/<path>; se esistono già devono
 *              essere identici
 *  update    — righe materials da aggiornare per id; "expect" deve combaciare
 *  create    — materiali da creare se non esiste già una riga con "match";
 *              course_id "COURSE:<slug>" viene risolto
 *
 * Tutte le verifiche vengono fatte prima di scrivere qualsiasi cosa. Idempotente:
 * un kit già applicato non fallisce, segnala "già applicato".
 */
class ApplyMaterialsKit extends Command
{
    protected $signature = 'materials:apply-kit {kit : cartella in resources/course-kits} {--dry-run : Mostra cosa farebbe senza scrivere}';

    protected $description = 'Applica un kit di correzioni a file e righe dei materiali, con verifiche preventive e backup';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $base = resource_path('course-kits/' . $this->argument('kit'));
        $manifest = json_decode((string) @file_get_contents("$base/manifest.json"), true);
        if (!is_array($manifest)) {
            $this->error("manifest.json mancante o non valido in $base");
            return self::FAILURE;
        }

        $disk = Storage::disk('local');
        $errors = [];
        $plan = ['files' => [], 'new_files' => [], 'update' => [], 'create' => []];

        foreach ($manifest['files'] ?? [] as $f) {
            $new = @file_get_contents("$base/files/{$f['path']}");
            if ($new === false) {
                $errors[] = "kit incompleto: files/{$f['path']}";
                continue;
            }
            if (!$disk->exists($f['path'])) {
                $errors[] = "file mancante in storage: {$f['path']}";
                continue;
            }
            $current = $disk->get($f['path']);
            if ($current === $new) {
                $this->line("= già applicato: {$f['path']}");
            } elseif (hash('sha256', $current) !== $f['sha256_before']) {
                $errors[] = "file cambiato rispetto all'originale atteso: {$f['path']}";
            } else {
                $plan['files'][] = [$f['path'], $new];
            }
        }

        foreach ($manifest['new_files'] ?? [] as $f) {
            $new = @file_get_contents("$base/files/{$f['path']}");
            if ($new === false) {
                $errors[] = "kit incompleto: files/{$f['path']}";
            } elseif ($disk->exists($f['path']) && $disk->get($f['path']) !== $new) {
                $errors[] = "esiste già un file diverso: {$f['path']}";
            } elseif (!$disk->exists($f['path'])) {
                $plan['new_files'][] = [$f['path'], $new];
            }
        }

        foreach ($manifest['update'] ?? [] as $u) {
            $m = Material::find($u['id']);
            if (!$m) {
                $errors[] = "materiale {$u['id']} non trovato";
                continue;
            }
            $already = collect($u['set'])->every(fn ($v, $k) => (string) $m->{$k} === (string) $v);
            if ($already) {
                continue;
            }
            foreach ($u['expect'] ?? [] as $k => $v) {
                if ((string) $m->{$k} !== (string) $v) {
                    $errors[] = "materiale {$u['id']}: $k atteso «{$v}», trovato «{$m->{$k}}»";
                    continue 2;
                }
            }
            $plan['update'][] = [$m, $u['set']];
        }

        foreach ($manifest['create'] ?? [] as $c) {
            $set = $c['set'];
            if (is_string($set['course_id'] ?? null) && str_starts_with($set['course_id'], 'COURSE:')) {
                $course = Course::where('slug', substr($set['course_id'], 7))->first();
                if (!$course) {
                    $errors[] = "corso {$set['course_id']} non trovato";
                    continue;
                }
                $set['course_id'] = $course->id;
            }
            if (Material::where($c['match'])->exists()) {
                continue;
            }
            $plan['create'][] = $c['match'] + $set;
        }

        if ($errors) {
            $this->error('Kit NON applicato, nessuna modifica fatta:');
            foreach ($errors as $e) {
                $this->line("  ✗ $e");
            }
            return self::FAILURE;
        }

        $suffix = $dry ? ' (dry-run)' : '';
        foreach ($plan['files'] as [$p]) {
            $this->info("↻ file $p$suffix");
        }
        foreach ($plan['new_files'] as [$p]) {
            $this->info("+ file $p$suffix");
        }
        foreach ($plan['update'] as [$m, $set]) {
            $this->info('↻ ' . $m->title . ' → ' . json_encode($set, JSON_UNESCAPED_UNICODE) . $suffix);
        }
        foreach ($plan['create'] as $row) {
            $this->info("+ materiale {$row['title']}$suffix");
        }
        $total = array_sum(array_map('count', $plan));
        if ($dry || $total === 0) {
            $this->info($total === 0 ? 'Niente da fare: kit già applicato.' : "Dry-run: $total modifiche previste, nessuna scritta.");
            return self::SUCCESS;
        }

        $stamp = now()->format('Ymd-His');
        foreach ($plan['files'] as [$p, $new]) {
            $disk->copy($p, "$p.bak-$stamp");
            $disk->put($p, $new);
            @chmod($disk->path($p), 0664);
        }
        foreach ($plan['new_files'] as [$p, $new]) {
            $disk->put($p, $new);
            @chmod($disk->path($p), 0664);
        }
        DB::transaction(function () use ($plan) {
            foreach ($plan['update'] as [$m, $set]) {
                $m->update($set);
            }
            foreach ($plan['create'] as $row) {
                Material::create($row + ['file_size' => Storage::disk('local')->size($row['file_path'])]);
            }
        });

        $this->info("Kit applicato: $total modifiche (backup dei file sostituiti: *.bak-$stamp).");
        return self::SUCCESS;
    }
}
