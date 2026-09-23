<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use ZipArchive;

/**
 * Estrae il testo (titoli + bullet) da un .pptx caricato come fonte KB.
 * Un pptx è uno zip OOXML: il testo di ogni slide sta in
 * `ppt/slides/slideN.xml` dentro tag `<a:t>`. Nessuna libreria di terze
 * parti: ZipArchive è nativo PHP, evita una nuova dipendenza composer solo
 * per leggere titoli/bullet (non serve un parser OOXML completo).
 */
class PptxTextExtractorService
{
    public function extract(string $absPath): string
    {
        $zip = new ZipArchive();
        if ($zip->open($absPath) !== true) {
            Log::warning('PPTX extract: impossibile aprire il file come zip', ['path' => $absPath]);
            return '';
        }

        $slideNumbers = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('#^ppt/slides/slide(\d+)\.xml$#', $name, $m)) {
                $slideNumbers[(int) $m[1]] = $name;
            }
        }
        ksort($slideNumbers);

        $out = [];
        foreach ($slideNumbers as $n => $entry) {
            $xml = $zip->getFromName($entry);
            if ($xml === false) {
                continue;
            }
            $texts = [];
            if (preg_match_all('#<a:t>(.*?)</a:t>#s', $xml, $matches)) {
                foreach ($matches[1] as $t) {
                    $t = trim(html_entity_decode($t, ENT_QUOTES | ENT_XML1, 'UTF-8'));
                    if ($t !== '') {
                        $texts[] = $t;
                    }
                }
            }
            if (!empty($texts)) {
                $out[] = "Slide {$n}:\n" . implode("\n", $texts);
            }
        }

        $zip->close();

        return implode("\n\n", $out);
    }
}
