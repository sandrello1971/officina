<?php

namespace Tests\Feature;

use App\Jobs\IngestRagVideoJob;
use App\Models\Course;
use App\Models\DocumentRag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

/**
 * "Documenti AI" (RagController) — upload multi-formato per la KB del motore
 * di generazione corsi: PPTX indicizzato subito, video accodato (mai
 * sincrono: la trascrizione fa polling bloccante lato videoai).
 */
class RagUploadMultiFormatTest extends TestCase
{
    use RefreshDatabase;

    private function asAdmin(): self
    {
        $this->withSession(['admin_logged_in' => true, 'admin_email' => 'admin@ente.it']);

        return $this;
    }

    private function makeCourse(): Course
    {
        return Course::create(['name' => 'Corso upload', 'slug' => 'corso-' . Str::lower(Str::random(8)), 'is_active' => true]);
    }

    private function makePptxFile(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'ragpptx') . '.pptx';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('ppt/slides/slide1.xml', '<p:sld xmlns:a="x" xmlns:p="y"><a:t>Contenuto slide di test</a:t></p:sld>');
        $zip->close();

        return new UploadedFile($path, 'slides.pptx', 'application/vnd.openxmlformats-officedocument.presentationml.presentation', null, true);
    }

    public function test_upload_pptx_indicizza_subito(): void
    {
        Storage::fake('public');
        $course = $this->makeCourse();

        $this->asAdmin()->post(route('admin.rag.upload'), [
            'course_id' => $course->id,
            'files' => [$this->makePptxFile()],
        ])->assertRedirect();

        $doc = DocumentRag::where('course_id', $course->id)->first();
        $this->assertNotNull($doc);
        $this->assertStringContainsString('Contenuto slide di test', $doc->content);
    }

    public function test_upload_video_accoda_il_job_invece_di_indicizzare_subito(): void
    {
        Storage::fake('public');
        Bus::fake();
        $course = $this->makeCourse();

        $video = UploadedFile::fake()->create('lezione.mp4', 500, 'video/mp4');

        $this->asAdmin()->post(route('admin.rag.upload'), [
            'course_id' => $course->id,
            'files' => [$video],
        ])->assertRedirect();

        Bus::assertDispatched(IngestRagVideoJob::class, fn ($job) => $job->courseId === $course->id);
        $this->assertSame(0, DocumentRag::where('course_id', $course->id)->count());
    }

    public function test_riusa_documento_di_un_altro_corso_lo_ricompone_e_reindicizza(): void
    {
        $sourceCourse = $this->makeCourse();
        $targetCourse = $this->makeCourse();

        // Simula 2 chunk sovrapposti (200 char), come RagService::chunkText.
        DocumentRag::create(['course_id' => $sourceCourse->id, 'title' => 'Manuale condiviso', 'content' => str_repeat('A', 800) . str_repeat('X', 200), 'chunk_index' => 0]);
        DocumentRag::create(['course_id' => $sourceCourse->id, 'title' => 'Manuale condiviso', 'content' => str_repeat('X', 200) . str_repeat('B', 600), 'chunk_index' => 1]);

        $this->asAdmin()->get(route('admin.rag.index', ['course_id' => $targetCourse->id]))
            ->assertOk()
            ->assertSee('Manuale condiviso');

        $this->asAdmin()->post(route('admin.rag.attach'), [
            'source_course_id' => $sourceCourse->id,
            'title' => 'Manuale condiviso',
            'target_course_id' => $targetCourse->id,
        ])->assertRedirect();

        $copied = DocumentRag::where('course_id', $targetCourse->id)->where('title', 'Manuale condiviso')->get();
        $this->assertNotEmpty($copied);
        $fullText = DocumentRag::reassembleGroup($copied->sortBy('chunk_index'));
        $this->assertSame(1, substr_count($fullText, str_repeat('X', 200)));

        // Il corso sorgente non viene toccato.
        $this->assertSame(2, DocumentRag::where('course_id', $sourceCourse->id)->count());
    }
}
