<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Setting;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificatePdfBrandingTest extends TestCase
{
    use RefreshDatabase;

    private function makeFixtures(): array
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
        $cert = Certificate::create([
            'student_id' => $student->id,
            'course_id'  => $course->id,
            'code'       => 'TEST-' . strtoupper(uniqid()),
            'score'      => 90,
            'issued_at'  => now(),
            'certification_name' => 'Certificato Test',
        ]);
        return compact('student', 'course', 'cert');
    }

    private function renderPdfView(array $fix): string
    {
        return view('pdf.certificate', [
            'cert'      => $fix['cert'],
            'student'   => $fix['student'],
            'course'    => $fix['course'],
            'date'      => $fix['cert']->issued_at->format('d/m/Y'),
            'qrDataUri' => 'data:image/png;base64,iVBORw0KGgo=',
            'verifyUrl' => 'https://atheneum.noscite.it/certificato/verifica/' . $fix['cert']->code,
        ])->render();
    }

    public function test_pdf_uses_dejavu_fonts_for_unicode_coverage(): void
    {
        $html = $this->renderPdfView($this->makeFixtures());

        // Font dichiarati nel <style>: DejaVu Serif (body) +
        // DejaVu Sans Mono (verify-url + codice certificato).
        $this->assertStringContainsString("font-family: 'DejaVu Serif', serif", $html);
        $this->assertStringContainsString("'DejaVu Sans Mono', monospace", $html);

        // Tagline con macron deve sopravvivere nel render
        // (Georgia non li ha → senza il fix render come '?').
        // Il default cablato 'In digitālī nova virtūs' deve apparire
        // (se atheneum_setting('platform_tagline') è vuoto, fallback).
        $this->assertStringContainsString('In digitālī nova virtūs', $html);
    }

    public function test_verify_block_has_explicit_width(): void
    {
        $html = $this->renderPdfView($this->makeFixtures());

        // Senza width esplicita, dompdf calcola position:absolute dalla
        // width naturale del contenuto e il blocco esce dalla pagina.
        $this->assertStringContainsString('width: 45mm', $html);
    }

    public function test_brand_strings_come_from_settings_with_fallback(): void
    {
        $fix = $this->makeFixtures();

        // Senza settings → default cablati
        $html = $this->renderPdfView($fix);
        $this->assertStringContainsString('NOSCITE', $html, 'watermark/logo default');
        $this->assertStringContainsString('Noscite SRLS', $html, 'rilasciato-da default');

        // Con platform_owner settato → propagazione completa
        Setting::put('platform_owner', 'Accademia Atena');
        Setting::put('platform_tagline', 'Sapere est posse');
        Setting::put('instance_name', 'Atena');

        $html2 = $this->renderPdfView($fix);

        $this->assertStringContainsString('ACCADEMIA ATENA', $html2,
            'watermark + logo-text usano platform_owner uppercase');
        $this->assertStringContainsString('Accademia Atena', $html2,
            'footer "Rilasciato da" usa platform_owner');
        $this->assertStringContainsString('Sapere est posse', $html2,
            'logo-sub usa platform_tagline');

        // Nessuno dei brand Noscite-specifici deve rimanere quando
        // ho settato altri valori
        $this->assertStringNotContainsString('Noscite SRLS', $html2);
        $this->assertStringNotContainsString('In digitālī nova virtūs', $html2);
    }

    public function test_no_georgia_font_in_active_css(): void
    {
        $html = $this->renderPdfView($this->makeFixtures());

        // Cerca solo dichiarazioni font-family attive (escludendo commenti).
        // Estrae tutti i match font-family: ... fino a ; e verifica
        // che nessuno usi Georgia.
        preg_match_all('/font-family:\s*([^;]+);/i', $html, $matches);
        foreach ($matches[1] as $declaration) {
            $this->assertStringNotContainsStringIgnoringCase(
                'georgia',
                $declaration,
                "Found Georgia in active font-family declaration: $declaration"
            );
        }
    }
}
