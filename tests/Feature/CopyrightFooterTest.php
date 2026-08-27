<?php

namespace Tests\Feature;

use App\Services\CourseSourcePdfBuilder;
use App\Support\Pdf\CopyrightTcpdf;
use Smalot\PdfParser\Parser;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Tutela del diritto d'autore: OGNI contenuto prodotto dalla piattaforma porta
 * la STESSA dicitura, quella di copyright_notice().
 *
 * Superfici coperte qui: dispense/documenti PDF (CourseSourcePdfBuilder — è il
 * renderer anche di ModuleDocumentService e CourseDocumentService), registro
 * presenze, pagine a schermo (partial + aggancio nei layout) e slide caricate a
 * mano (stampigliatura python). Il certificato è coperto da
 * CertificatePdfBrandingTest; le slide GENERATE dal python build_pptx.py.
 */
class CopyrightFooterTest extends TestCase
{
    public function test_documento_pdf_riporta_il_copyright_in_footer(): void
    {
        $bytes = (new CourseSourcePdfBuilder())
            ->buildFromHtml('<h2>Titolo</h2><p>Contenuto.</p>', ['title' => 'Doc']);

        $text = (new Parser())->parseContent($bytes)->getText();

        $this->assertStringContainsString(
            copyright_notice(),
            $text,
            'La dicitura di copyright deve comparire nel PDF generato.'
        );
    }

    public function test_copyright_configurato_con_il_titolare_corretto(): void
    {
        $this->assertStringContainsString('Stefano Domenico Andrello', copyright_notice());
    }

    /**
     * L'anno si compone a ogni chiamata: è il motivo per cui non sta in config
     * (dove `config:cache` lo congelerebbe, com'era successo al "© 2025").
     */
    public function test_anno_corrente_composto_a_runtime(): void
    {
        $this->assertStringContainsString('© ' . date('Y'), copyright_notice());
    }

    /** L'override esplicito vince, per i casi eccezionali. */
    public function test_override_da_config_ha_la_precedenza(): void
    {
        config(['atheneum.copyright' => '© 1999 Altro Titolare.']);

        $this->assertSame('© 1999 Altro Titolare.', copyright_notice());
    }

    /** Senza titolare né override non si stampa nulla (i chiamanti gestiscono il vuoto). */
    public function test_senza_titolare_la_dicitura_e_vuota(): void
    {
        config(['atheneum.copyright' => '', 'atheneum.copyright_holder' => '']);

        $this->assertSame('', copyright_notice());
    }

    /** Il footer condiviso: stampato da TCPDF su ogni pagina, senza altro setup. */
    public function test_footer_condiviso_stampa_la_dicitura_su_ogni_pagina(): void
    {
        $pdf = new CopyrightTcpdf('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        $pdf->AddPage();
        $pdf->AddPage();

        $text = (new Parser())->parseContent($pdf->Output('', 'S'))->getText();

        $this->assertSame(
            2,
            substr_count($text, copyright_notice()),
            'La dicitura deve comparire su OGNI pagina, non solo sulla prima.'
        );
    }

    /**
     * Il registro presenze passa dallo stesso footer (costruirlo davvero
     * richiederebbe corso, sessioni e iscritti: qui basta l'aggancio).
     */
    public function test_registro_presenze_usa_il_footer_condiviso(): void
    {
        $source = file_get_contents(app_path('Services/AttendanceRegisterPdfBuilder.php'));

        $this->assertStringContainsString('new CopyrightTcpdf', $source);
        $this->assertStringContainsString('setPrintFooter(true)', $source);
    }

    /** Le pagine a schermo: il partial stampa la stessa dicitura dei PDF. */
    public function test_partial_html_stampa_la_dicitura(): void
    {
        $html = view('layouts.partials._copyright')->render();

        $this->assertStringContainsString(e(copyright_notice()), $html);
    }

    /**
     * Ogni layout applicativo aggancia il partial: è ciò che garantisce che il
     * canvas dei corsi, l'area docente, la scuola e l'admin non restino nudi.
     */
    public function test_tutti_i_layout_agganciano_il_copyright(): void
    {
        foreach (['student', 'docente', 'scuola', 'admin'] as $layout) {
            $this->assertStringContainsString(
                "layouts.partials._copyright",
                file_get_contents(resource_path("views/layouts/{$layout}.blade.php")),
                "Il layout {$layout} deve includere il partial di copyright."
            );
        }
    }

    /** Il sito pubblico non deve tornare a una dicitura scritta a mano. */
    public function test_footer_pubblici_usano_la_dicitura_unica(): void
    {
        foreach (['views/layouts/app.blade.php', 'views/components/footer.blade.php'] as $file) {
            $this->assertStringContainsString(
                'copyright_notice()',
                file_get_contents(resource_path($file)),
                "{$file} deve usare la dicitura unica."
            );
        }
    }

    /**
     * Slide CARICATE a mano: non passano da build_pptx.py, quindi la dicitura
     * gliela applica lo stampigliatore. Verifica end-to-end col python reale,
     * inclusa l'idempotenza (un ri-upload non deve accumulare scritte).
     */
    public function test_stampigliatura_pptx_caricato(): void
    {
        $python = config('services.pptx.python');
        if (!is_executable((string) $python)) {
            $this->markTestSkipped('Interprete python-pptx non disponibile in questo ambiente.');
        }

        $pptx = tempnam(sys_get_temp_dir(), 'stamp') . '.pptx';
        $make = new Process([$python, '-c', <<<'PY'
import sys
from pptx import Presentation
p = Presentation()
p.slides.add_slide(p.slide_layouts[6])
p.slides.add_slide(p.slide_layouts[6])
p.save(sys.argv[1])
PY, $pptx]);
        $make->run();
        $this->assertTrue($make->isSuccessful(), 'Impossibile creare il .pptx di prova.');

        $script = base_path('resources/python/stamp_pptx_copyright.py');
        foreach ([1, 2] as $_) { // due passate: la seconda non deve duplicare
            $stamp = new Process([$python, $script, $pptx, copyright_notice()]);
            $stamp->run();
            $this->assertTrue($stamp->isSuccessful(), $stamp->getErrorOutput());
        }

        $check = new Process([$python, '-c', <<<'PY'
import sys
from pptx import Presentation
p = Presentation(sys.argv[1])
notice = sys.argv[2]
for slide in p.slides:
    hits = [s for s in slide.shapes if s.has_text_frame and s.text_frame.text == notice]
    if len(hits) != 1:
        print("KO", len(hits)); sys.exit(1)
print("OK")
PY, $pptx, copyright_notice()]);
        $check->run();

        @unlink($pptx);
        $this->assertTrue(
            $check->isSuccessful(),
            'Ogni slide caricata deve portare la dicitura, una sola volta: ' . $check->getOutput()
        );
    }
}
