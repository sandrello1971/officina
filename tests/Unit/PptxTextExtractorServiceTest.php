<?php

namespace Tests\Unit;

use App\Services\PptxTextExtractorService;
use Tests\TestCase;
use ZipArchive;

/**
 * Estrazione testo da .pptx caricato come fonte KB del motore di generazione
 * corsi — nessuna dipendenza esterna (ZipArchive nativo), verificato qui con
 * un pptx minimale costruito ad-hoc (2 slide, testo in <a:t>).
 */
class PptxTextExtractorServiceTest extends TestCase
{
    private function makeMinimalPptx(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pptxtest') . '.pptx';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE);

        $zip->addFromString('ppt/slides/slide1.xml', <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<p:sld xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
       xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">
  <p:cSld><p:spTree><p:sp><p:txBody>
    <a:p><a:r><a:t>Titolo slide 1</a:t></a:r></a:p>
    <a:p><a:r><a:t>Bullet uno</a:t></a:r></a:p>
  </p:txBody></p:sp></p:spTree></p:cSld>
</p:sld>
XML);

        $zip->addFromString('ppt/slides/slide2.xml', <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<p:sld xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
       xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">
  <p:cSld><p:spTree><p:sp><p:txBody>
    <a:p><a:r><a:t>Slide due con &amp; simbolo</a:t></a:r></a:p>
  </p:txBody></p:sp></p:spTree></p:cSld>
</p:sld>
XML);

        $zip->close();

        return $path;
    }

    public function test_estrae_testo_in_ordine_di_slide(): void
    {
        $path = $this->makeMinimalPptx();

        $text = (new PptxTextExtractorService())->extract($path);

        $this->assertStringContainsString('Slide 1:', $text);
        $this->assertStringContainsString('Titolo slide 1', $text);
        $this->assertStringContainsString('Bullet uno', $text);
        $this->assertStringContainsString('Slide 2:', $text);
        $this->assertStringContainsString('Slide due con & simbolo', $text);
        $this->assertTrue(strpos($text, 'Slide 1:') < strpos($text, 'Slide 2:'));

        unlink($path);
    }

    public function test_file_non_zip_ritorna_stringa_vuota(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'notpptx');
        file_put_contents($path, 'non è uno zip');

        $text = (new PptxTextExtractorService())->extract($path);

        $this->assertSame('', $text);

        unlink($path);
    }
}
