<?php

namespace Tests\Feature;

use App\Services\CourseSourcePdfBuilder;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

/**
 * Tutela del diritto d'autore: ogni PDF/documento generato dalla piattaforma
 * porta in fondo, in piccolo, la dicitura config('atheneum.copyright').
 *
 * Qui si copre il builder dei documenti (CourseSourcePdfBuilder → TCPDF con
 * footer su ogni pagina). Il certificato è coperto da CertificatePdfBrandingTest;
 * le slide (.pptx) sono rese dal python build_pptx.py, non eseguibile in test.
 */
class CopyrightFooterTest extends TestCase
{
    public function test_documento_pdf_riporta_il_copyright_in_footer(): void
    {
        $bytes = (new CourseSourcePdfBuilder())
            ->buildFromHtml('<h2>Titolo</h2><p>Contenuto.</p>', ['title' => 'Doc']);

        $text = (new Parser())->parseContent($bytes)->getText();

        $this->assertStringContainsString(
            (string) config('atheneum.copyright'),
            $text,
            'La dicitura di copyright deve comparire nel PDF generato.'
        );
    }

    public function test_copyright_configurato_con_il_titolare_corretto(): void
    {
        $this->assertStringContainsString(
            'Stefano Domenico Andrello',
            (string) config('atheneum.copyright')
        );
    }
}
