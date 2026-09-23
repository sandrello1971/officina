<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Student;
use App\Services\CertificatePdfBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Riscritto il 06/06: il certificato non è più renderizzato da una Blade
 * (pdf/certificate.blade.php + dompdf), ma generato da CertificatePdfBuilder
 * via FPDI/TCPDF (overlay su template PDF). La vecchia suite testava la Blade
 * ormai rimossa (commit 38e82d1) e falliva per view inesistente.
 *
 * Qui si verifica ciò che resta logica nostra e non rendering di terze parti:
 *  - il builder produce un PDF valido a partire da un Certificate;
 *  - il branding (intestatario) è preso da settings, non hardcoded, e propaga
 *    nei metadati del documento (con fallback al default cablato).
 */
class CertificatePdfBrandingTest extends TestCase
{
    use RefreshDatabase;

    private function makeCertificate(): Certificate
    {
        $student = Student::create([
            'name' => 'Mario Rossi',
            'email' => 'mario+' . uniqid() . '@example.com',
            'password' => bcrypt('secret-pw'),
            'is_active' => true,
        ]);
        $course = Course::create([
            'name' => 'Corso Test',
            'slug' => 'corso-' . uniqid(),
            'is_active' => true,
            'sort_order' => 1,
        ]);

        return Certificate::create([
            'student_id' => $student->id,
            'course_id'  => $course->id,
            'code'       => 'TEST-' . strtoupper(uniqid()),
            'score'      => 90,
            'issued_at'  => now(),
            'certification_name' => 'Certificato Test',
        ]);
    }

    public function test_builder_produces_a_valid_pdf(): void
    {
        $pdf = (new CertificatePdfBuilder())->build($this->makeCertificate());

        // Header e trailer di un PDF ben formato + dimensione non banale
        // (il template viene importato, quindi sono decine di KB).
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString('%%EOF', $pdf);
        $this->assertGreaterThan(2000, strlen($pdf), 'PDF troppo piccolo: build incompleta');
    }

    public function test_owner_branding_comes_from_settings(): void
    {
        // Default cablato: senza platform_owner il documento usa "Stefano Andrello".
        $pdfDefault = (new CertificatePdfBuilder())->build($this->makeCertificate());
        $this->assertStringContainsString('Stefano Andrello', $pdfDefault,
            'Senza setting, il PDF deve riportare l\'intestatario di default');

        // Con platform_owner valorizzato (ASCII, così resta literal nei metadati
        // PDF e l\'asserzione non dipende dalla compressione degli stream di
        // contenuto), il nuovo intestatario deve propagare nel documento e il
        // default non deve più comparire.
        atheneum_setting_put('platform_owner', 'ACME Formazione SRL');

        $pdfBranded = (new CertificatePdfBuilder())->build($this->makeCertificate());
        $this->assertStringContainsString('ACME Formazione SRL', $pdfBranded,
            'Il PDF deve riportare l\'intestatario impostato via settings');
        $this->assertStringNotContainsString('Stefano Andrello', $pdfBranded,
            'L\'intestatario di default non deve sopravvivere quando platform_owner è settato');
    }

    public function test_certificato_riporta_il_copyright_in_footer(): void
    {
        $pdf = (new CertificatePdfBuilder())->build($this->makeCertificate());

        $text = (new \Smalot\PdfParser\Parser())->parseContent($pdf)->getText();

        $this->assertStringContainsString(
            'Stefano Domenico Andrello',
            $text,
            'Il certificato deve riportare la dicitura di copyright in footer.'
        );
    }

    public function test_font_mancanti_vengono_rigenerati_al_volo(): void
    {
        // Regressione: le definizioni stavano in vendor/, un deploy le ha
        // cancellate e ogni certificato falliva. Il builder deve rigenerarle.
        foreach (array_keys(CertificatePdfBuilder::FONTS) as $name) {
            foreach (glob(CertificatePdfBuilder::fontDir() . "/{$name}.*") ?: [] as $file) {
                unlink($file);
            }
        }

        $pdf = (new CertificatePdfBuilder())->build($this->makeCertificate());

        $this->assertStringStartsWith('%PDF-', $pdf);
        foreach (array_keys(CertificatePdfBuilder::FONTS) as $name) {
            $this->assertFileExists(CertificatePdfBuilder::fontDir() . "/{$name}.php");
        }
    }

    public function test_errori_tcpdf_sono_eccezioni_non_die(): void
    {
        // Con la config di default TCPDF fa die(): il try/catch dell'observer
        // non lo intercetta e la richiesta di submit del quiz muore a metà.
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('TCPDF ERROR');

        (new \TCPDF())->SetFont('font-che-non-esiste');
    }
}
