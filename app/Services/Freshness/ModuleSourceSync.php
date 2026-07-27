<?php

namespace App\Services\Freshness;

use App\Models\Course;
use App\Models\CourseSource;
use App\Services\CourseSourceExtractor;
use Illuminate\Support\Facades\Log;

/**
 * Allinea `course_sources` al contenuto ATTUALE dei moduli per i corsi async SENZA
 * manuale formatore. Risolve due fragilità del Freshness:
 *
 *  1) STALENESS — il sorgente è uno snapshot: se i moduli vengono editati (come un
 *     aggiornamento contenuti) il vecchio sorgente resta e l'agente verificherebbe testo
 *     superato. Qui la versione del sorgente È l'hash del contenuto dei moduli
 *     (Course::currentContentHash), quindi un edit produce una versione nuova e l'agente
 *     — che carica l'ULTIMO course_sources — vede sempre il testo corrente.
 *
 *  2) PASSO MANUALE — i nuovi corsi async non richiedono più `course:recover-source-from-modules`
 *     lanciato a mano via SSH: al primo run il sorgente si crea da solo.
 *
 * Additivo e idempotente: crea una NUOVA riga solo quando il contenuto è cambiato; non
 * modifica né cancella mai righe esistenti (immutabilità di course_sources). Se il corso
 * ha un manuale formatore, quello resta la fonte di verità del sorgente e qui non si tocca
 * nulla (il disaccoppiamento sorgente↔manuale è gestito altrove, P25.3f).
 */
class ModuleSourceSync
{
    /** Prefisso di versione per i sorgenti derivati dai moduli (distingue dai "1.0" del docx). */
    private const VERSION_PREFIX = 'mod-';

    public function __construct(private CourseSourceExtractor $extractor) {}

    /**
     * Assicura un course_sources allineato ai moduli. Ritorna la riga creata, oppure null
     * se: il corso ha un manuale, il sorgente è già allineato, o i moduli sono vuoti.
     */
    public function ensureFresh(Course $course): ?CourseSource
    {
        // Il manuale formatore, quando esiste, è la fonte di verità: non lo scavalchiamo.
        if ($course->instructorMaterials()->exists()) {
            return null;
        }

        // varchar(20): "mod-" (4) + 12 hex = 16.
        $version = self::VERSION_PREFIX . substr($course->currentContentHash(), 0, 12);

        // Idempotenza: un sorgente per questo hash significa "già allineato".
        if (CourseSource::where('course_id', $course->id)->where('version', $version)->exists()) {
            return null;
        }

        $html = $course->modules()
            ->orderBy('sort_order')
            ->get(['id', 'sort_order', 'content'])
            ->map(fn ($m) => trim((string) $m->content))
            ->filter()
            ->implode("\n\n");

        if (trim($html) === '') {
            return null;
        }

        // File temporaneo .html per pandoc (stessa pipeline di course:recover-source-from-modules).
        $tmp = tempnam(sys_get_temp_dir(), 'coursesrc_') . '.html';
        file_put_contents($tmp, $html);
        try {
            $result = $this->extractor->extractFromHtml($tmp);
        } finally {
            @unlink($tmp);
        }

        foreach ($result['warnings'] ?? [] as $w) {
            Log::info('[ModuleSourceSync] ' . $w, ['course_id' => $course->id]);
        }

        $blocks = $result['blocks'] ?? [];
        if (empty($blocks)) {
            return null;
        }

        return CourseSource::create([
            'course_id' => $course->id,
            'version'   => $version,
            'blocks'    => $blocks,
        ]);
    }
}
