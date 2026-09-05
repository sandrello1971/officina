<?php

namespace App\Services;

use App\Models\CompletenessFinding;
use App\Models\Course;
use App\Models\Module;
use Illuminate\Support\Facades\Storage;

/**
 * Controllo di completezza della "consegna" di un corso: non verifica se un contenuto è
 * aggiornato (Freshness) né se manca un ARGOMENTO (P26 Gap Scout), ma se ogni modulo ha la
 * dotazione minima per essere erogato — slide pronte, sezione nel manuale formatore, HTML
 * integro, ordinamento senza buchi — e se i permessi filesystem dei materiali sono corretti
 * per il server web. Puro/read-only: nessuna chiamata AI, nessuna scrittura sul corso.
 */
class CompletenessAuditor
{
    private const DISK = 'local';

    /**
     * Esegue il controllo e allinea i finding persistiti: chiude (resolved_at) quelli non
     * più rilevati, crea i nuovi, lascia intatti quelli già aperti (compresi eventuali
     * dismissed_at admin, mai toccati da qui).
     *
     * @return array{created:int, still_open:int, resolved:int}
     */
    public function auditAndPersist(Course $course): array
    {
        $now = now();
        $current = $this->audit($course);
        // "Non risolti" include i dismissed_at (scelta admin: non li tocchiamo/duplichiamo
        // finché il problema resta rilevato). Solo l'assenza dal giro corrente li chiude.
        $open = CompletenessFinding::where('course_id', $course->id)->whereNull('resolved_at')->get();

        $stillOpenIds = [];
        $created = 0;
        foreach ($current as $f) {
            $match = $open->first(fn (CompletenessFinding $existing) => $existing->check_type === $f['check_type']
                && $existing->module_id === $f['module_id']);

            if ($match) {
                $stillOpenIds[] = $match->id;
                continue;
            }

            $new = CompletenessFinding::create([
                'course_id' => $course->id,
                'module_id' => $f['module_id'],
                'check_type' => $f['check_type'],
                'severity' => $f['severity'],
                'message' => $f['message'],
                'detected_at' => $now,
            ]);
            $stillOpenIds[] = $new->id;
            $created++;
        }

        $resolved = $open->reject(fn (CompletenessFinding $existing) => in_array($existing->id, $stillOpenIds, true));
        foreach ($resolved as $r) {
            $r->update(['resolved_at' => $now]);
        }

        return ['created' => $created, 'still_open' => count($stillOpenIds), 'resolved' => $resolved->count()];
    }

    /** @return list<array{check_type:string, severity:string, message:string, module_id:?string}> */
    public function audit(Course $course): array
    {
        $findings = [];
        $modules = $course->modules()->orderBy('sort_order')->get();

        foreach ($modules as $module) {
            $findings = array_merge(
                $findings,
                $this->checkPresentation($module),
                $this->checkContentIntegrity($module),
            );
        }

        $findings = array_merge(
            $findings,
            $this->checkSortOrder($course, $modules),
            $this->checkInstructorManualCoverage($course, $modules),
            $this->checkFilePermissions($course),
        );

        return $findings;
    }

    /** Ogni modulo dovrebbe avere almeno una presentazione pronta. */
    private function checkPresentation(Module $module): array
    {
        $presentations = $module->presentations;
        if ($presentations->isEmpty()) {
            return [$this->finding('missing_presentation', 'warning',
                "Il modulo «{$module->title}» non ha nessuna presentazione.", $module->id)];
        }

        if (!$presentations->contains(fn ($p) => $p->status === 'ready')) {
            return [$this->finding('presentation_not_ready', 'warning',
                "Il modulo «{$module->title}» ha una presentazione ma non è mai arrivata a «pronta».", $module->id)];
        }

        return [];
    }

    /**
     * Il contenuto deve essere HTML valido, non testo/markdown grezzo salvato per errore
     * (bug reale trovato: **bold** e paragrafi senza tag, renderizzati come blocco unico).
     */
    private function checkContentIntegrity(Module $module): array
    {
        $content = trim((string) $module->content);
        if ($content === '') {
            return [$this->finding('empty_content', 'warning',
                "Il modulo «{$module->title}» non ha contenuto.", $module->id)];
        }

        $looksLikeHtml = str_starts_with($content, '<');
        $hasRawMarkdown = (bool) preg_match('/\*\*[^*]+\*\*/', $content) && !str_contains($content, '<strong>');

        if (!$looksLikeHtml || $hasRawMarkdown) {
            return [$this->finding('content_not_html', 'warning',
                "Il modulo «{$module->title}» sembra contenere markdown grezzo invece di HTML (verrà mostrato senza formattazione).", $module->id)];
        }

        return [];
    }

    /** L'ordinamento del corso non dovrebbe avere buchi né duplicati. */
    private function checkSortOrder(Course $course, $modules): array
    {
        $orders = $modules->pluck('sort_order')->values();
        $duplicates = $orders->duplicates();
        if ($duplicates->isNotEmpty()) {
            return [$this->finding('sort_order_duplicate', 'warning',
                'Sort_order duplicato tra moduli: ' . $duplicates->unique()->implode(', '), null)];
        }

        $sorted = $orders->sort()->values();
        for ($i = 1; $i < $sorted->count(); $i++) {
            if ($sorted[$i] - $sorted[$i - 1] > 1) {
                return [$this->finding('sort_order_gap', 'info',
                    "Buco nell'ordinamento tra {$sorted[$i - 1]} e {$sorted[$i]}.", null)];
            }
        }

        return [];
    }

    /**
     * Se il corso ha un manuale formatore, il numero di sezioni dovrebbe essere ragionevolmente
     * vicino al numero di moduli. Un match testuale per-modulo (titolo del capitolo cercato
     * nell'HTML) è stato provato e SCARTATO: produce falsi positivi sistematici sui manuali
     * organizzati per sessione/blocco invece che un capitolo = una sezione (osservato su corsi
     * reali: 100% di falsi «non menzionato» dove il manuale raggruppa più capitoli per sessione).
     * Resta un segnale grezzo ma onesto — un CONTEGGIO, non un'affermazione puntuale sbagliata —
     * a livello di corso, non di modulo.
     */
    private function checkInstructorManualCoverage(Course $course, $modules): array
    {
        // Un corso può avere PIÙ materiali instructor-only (es. "Soluzioni — Modulo N"
        // per i corsi tecnici): senza questo filtro ->first() può prendere uno di quelli
        // invece del vero manuale, leggendo "0 sezioni" e producendo un falso positivo
        // (bug reale trovato lanciando l'audit su un corso con questo pattern).
        $manualMaterial = $course->instructorMaterials()
            ->where('file_type', '!=', 'canvas')
            ->where('title', 'like', '%Manuale Formatore%')
            ->first();
        if (!$manualMaterial) {
            return []; // corso senza manuale formatore: fuori scope di questo controllo
        }

        $sectionsCount = $manualMaterial->instructorManualSections()->count();
        $modulesCount = $modules->count();
        if ($modulesCount === 0) {
            return [];
        }

        if ($sectionsCount < (int) ceil($modulesCount / 2)) {
            return [$this->finding('instructor_manual_low_coverage', 'info',
                "Il manuale formatore ha {$sectionsCount} sezioni per {$modulesCount} moduli: verifica a mano se copre tutti i capitoli (i manuali organizzati per sessione, non per capitolo, sono normali e non sono un problema).",
                null)];
        }

        return [];
    }

    /**
     * I materiali di questo corso (canvas, laboratori, manuale formatore, presentazioni)
     * devono essere leggibili dal server web (bug reale: cartelle create a 0700 da script
     * CLI, inaccessibili a www-data pur essendo www-data nel gruppo `noscite`).
     */
    private function checkFilePermissions(Course $course): array
    {
        $disk = Storage::disk(self::DISK);
        $bases = [
            "materials/{$course->slug}",
            "instructor-manuals/{$course->slug}",
        ];

        foreach ($course->modules as $module) {
            $bases[] = "module-presentations/{$module->id}";
        }

        $findings = [];
        foreach ($bases as $base) {
            $absBase = $disk->path($base);
            if (!is_dir($absBase)) {
                continue;
            }
            $broken = $this->findUnreadableDirs($absBase);
            foreach ($broken as $path) {
                $findings[] = $this->finding('bad_permissions', 'warning',
                    'Cartella non leggibile dal server web (permessi 0700 o simili): ' . str_replace($disk->path(''), '', $path), null);
            }
        }

        return $findings;
    }

    /**
     * @return list<string> percorsi assoluti di proprietà di uno shell user (non www-data)
     * senza r-x di gruppo. Una cartella posseduta da www-data è per definizione leggibile
     * dal server web: non va né segnalata né APERTA (0700 www-data è corretto e voluto, e
     * comunque un utente diverso da www-data — es. chi esegue questo comando — non può
     * aprirla: tentarlo lancerebbe un warning fatale, esattamente ciò che questo controllo
     * deve rilevare senza mai far crashare l'intero audit).
     */
    private function findUnreadableDirs(string $absPath): array
    {
        $out = [];
        $ownerName = function_exists('posix_getpwuid') ? (posix_getpwuid(fileowner($absPath))['name'] ?? '') : '';
        if ($ownerName === '' || $ownerName === 'www-data') {
            return $out;
        }

        $perms = fileperms($absPath);
        if (($perms & 0050) !== 0050) {
            $out[] = $absPath;
        }

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($absPath, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterator as $item) {
                if ($item->isDir()) {
                    $out = array_merge($out, $this->findUnreadableDirs($item->getPathname()));
                }
            }
        } catch (\Throwable $e) {
            // Non dovrebbe accadere (l'owner non-www-data è già verificato sopra), ma un
            // controllo di completezza non deve MAI interrompere l'audit di un corso.
        }

        return $out;
    }

    private function finding(string $checkType, string $severity, string $message, ?string $moduleId): array
    {
        return ['check_type' => $checkType, 'severity' => $severity, 'message' => $message, 'module_id' => $moduleId];
    }
}
