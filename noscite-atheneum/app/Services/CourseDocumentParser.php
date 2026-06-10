<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class CourseDocumentParser
{
    public function __construct(private CourseIngestionService $llm)
    {
    }

    public function convertDocxToHtml(string $docxPath): string
    {
        $process = new Process([
            'pandoc',
            $docxPath,
            '--from=docx',
            '--to=html5',
            '--wrap=none',
        ]);
        $process->setTimeout(60);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException('pandoc fallito: ' . $process->getErrorOutput());
        }

        return $process->getOutput();
    }

    public function normalizeHeadings(string $html): string
    {
        return preg_replace_callback(
            '/<p[^>]*>\s*<strong[^>]*>(.*?)<\/strong>\s*<\/p>/is',
            function ($m) {
                $text = trim(strip_tags($m[1]));

                // Livello 1: PARTE/MODULO/LEZIONE/UNITÀ/SEZIONE/ARGOMENTO seguito da ordinale o numero
                if (preg_match('/^(?:PARTE|MODULO|LEZIONE|UNIT[ÀA]|SEZIONE|ARGOMENTO)\s+(?:PRIMA|SECONDA|TERZA|QUARTA|QUINTA|SESTA|SETTIMA|OTTAVA|NONA|DECIMA|[IVX]+|\d+)\b/iu', $text)) {
                    return '<h1>' . $m[1] . '</h1>';
                }

                // Livello 2: Capitolo N
                if (preg_match('/^Capitolo\s+\d+/iu', $text)) {
                    return '<h2>' . $m[1] . '</h2>';
                }

                // Livello 3: X.Y Titolo
                if (preg_match('/^\d+\.\d+\s+\S/u', $text)) {
                    return '<h3>' . $m[1] . '</h3>';
                }

                return $m[0];
            },
            $html
        );
    }

    public function splitIntoModules(string $normalizedHtml): array
    {
        $level = $this->chooseTopLevel($normalizedHtml);
        $modules = $this->extractTopLevelSections($normalizedHtml, $level);

        // Fallback: nessun titolo riconosciuto come heading → modulo unico con tutto il contenuto.
        // Evita il blocco "0 moduli"; l'utente può poi suddividere a mano nell'admin.
        if (empty($modules) && trim(strip_tags($normalizedHtml)) !== '') {
            $modules[] = [
                'title' => 'Contenuto del corso',
                'short_description' => null,
                'content_html' => $normalizedHtml,
                'sort_order' => 0,
            ];
        }

        return $modules;
    }

    public function normalizeAndSplitIntoModules(string $html): array
    {
        return $this->splitIntoModules($this->normalizeHeadings($html));
    }

    public function extractFrontmatter(string $normalizedHtml): string
    {
        $pos = stripos($normalizedHtml, '<h1');
        if ($pos === false) {
            return $normalizedHtml;
        }
        return substr($normalizedHtml, 0, $pos);
    }

    public function separateExamPrep(array $modules): array
    {
        if (empty($modules)) {
            return ['modules' => $modules, 'exam_prep_html' => null];
        }

        $lastIndex = count($modules) - 1;
        $lastContent = $modules[$lastIndex]['content_html'];

        if (preg_match(
            '/<h2[^>]*>(?:[^<]*?)(?:preparazione\s+all[\'\x{2019}]?esame|preparazione\s+esame|ripasso|recap)(?:[^<]*?)<\/h2>/iu',
            $lastContent,
            $matches,
            PREG_OFFSET_CAPTURE
        )) {
            $cutPos = $matches[0][1];
            $beforeCut = substr($lastContent, 0, $cutPos);
            $examPrep = substr($lastContent, $cutPos);

            $modules[$lastIndex]['content_html'] = rtrim($beforeCut);

            return ['modules' => $modules, 'exam_prep_html' => $examPrep];
        }

        return ['modules' => $modules, 'exam_prep_html' => null];
    }

    public function extractCourseMetadata(string $frontmatterHtml): array
    {
        $text = strip_tags($frontmatterHtml);
        $text = trim(preg_replace('/\s+/', ' ', $text));

        if (mb_strlen($text) < 50) {
            return [
                'name' => 'Corso senza titolo',
                'short_description' => null,
                'description' => null,
            ];
        }

        $brand = atheneum_setting('instance_name', 'di formazione');
        $systemPrompt = <<<SYS
Ricevi il testo introduttivo di un manuale didattico {$brand}. Devi estrarre tre campi:
1. name: il nome del corso (cerca pattern tipo "SEGNALE — Fondamenta AI Operativa", o titoli simili)
2. short_description: una frase di 1-2 righe che riassume di cosa tratta il corso
3. description: una descrizione più estesa (2-4 frasi) che riprende i temi principali

Rispondi SOLO con JSON valido, nessun testo extra, nessun markdown.

Formato richiesto:
{"name": "...", "short_description": "...", "description": "..."}

Regole:
- Mantieni il tono del testo originale
- Non inventare informazioni non presenti
- Se non riesci a estrarre un campo, mettilo a null (non stringa vuota)
SYS;

        $userPrompt = "Frontmatter del manuale:\n\n" . mb_substr($text, 0, 8000);

        return $this->llm->callClaudeJsonPublic($systemPrompt, $userPrompt);
    }

    public function extractTextForExam(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($ext === 'txt') {
            return file_get_contents($path) ?: '';
        }

        if (in_array($ext, ['docx', 'doc'], true)) {
            $process = new Process([
                'pandoc',
                $path,
                '--from=' . ($ext === 'docx' ? 'docx' : 'doc'),
                '--to=plain',
                '--wrap=none',
            ]);
            $process->setTimeout(60);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new \RuntimeException('pandoc plain fallito: ' . $process->getErrorOutput());
            }

            return $process->getOutput();
        }

        if ($ext === 'pdf') {
            try {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($path);
                return $pdf->getText();
            } catch (\Exception $e) {
                Log::warning('PDF extract error: ' . $e->getMessage());
                return '';
            }
        }

        return '';
    }

    private function chooseTopLevel(string $html): int
    {
        $h1Count = preg_match_all('/<h1[^>]*>/i', $html);
        $h2Count = preg_match_all('/<h2[^>]*>/i', $html);

        if ($h1Count > 0) return 1;
        if ($h2Count > 0) return 2;
        return 1;
    }

    private function extractTopLevelSections(string $html, int $level): array
    {
        $tag = $level === 1 ? 'h1' : 'h2';

        $parts = preg_split(
            "/(<{$tag}[^>]*>.*?<\\/{$tag}>)/is",
            $html,
            -1,
            PREG_SPLIT_DELIM_CAPTURE
        );

        $modules = [];
        $current = null;
        $sortOrder = 0;

        foreach ($parts as $part) {
            if (preg_match("/^<{$tag}[^>]*>(.*?)<\\/{$tag}>$/is", $part, $m)) {
                if ($current !== null) {
                    $modules[] = $current;
                }
                $title = trim(strip_tags($m[1]));
                if ($title === '') {
                    $current = null;
                    continue;
                }
                $current = [
                    'title' => $title,
                    'short_description' => null,
                    'content_html' => $part,
                    'sort_order' => $sortOrder++,
                ];
            } else {
                if ($current !== null) {
                    $current['content_html'] .= $part;
                }
            }
        }
        if ($current !== null) {
            $modules[] = $current;
        }

        if ($level === 2) {
            foreach ($modules as &$mod) {
                $mod['content_html'] = preg_replace('/<(\/?)h2/', '<$1h1', $mod['content_html'], 1);
            }
            unset($mod);
        }

        return $modules;
    }
}
