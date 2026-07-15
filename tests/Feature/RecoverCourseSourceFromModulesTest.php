<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseSource;
use App\Models\Module;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * course:recover-source-from-modules — ricostruisce course_sources dal contenuto
 * (HTML) dei moduli, per i corsi async senza manuale .docx. Richiede pandoc.
 */
class RecoverCourseSourceFromModulesTest extends TestCase
{
    use RefreshDatabase;

    private function skipIfNoPandoc(): void
    {
        exec('pandoc --version 2>/dev/null', $o, $rc);
        if ($rc !== 0) {
            $this->markTestSkipped('pandoc non disponibile');
        }
    }

    private function course(): Course
    {
        return Course::create([
            'name' => 'CUSTODIA Test', 'slug' => 'custodia-' . uniqid(),
            'is_active' => true, 'sort_order' => 1,
        ]);
    }

    private function module(Course $c, string $html, int $order): Module
    {
        return Module::create([
            'course_id' => $c->id, 'title' => 'MODULO ' . $order,
            'content' => $html, 'sort_order' => $order, 'is_active' => true,
        ]);
    }

    public function test_builds_course_source_from_module_html(): void
    {
        $this->skipIfNoPandoc();
        $course = $this->course();
        $this->module($course, '<h1>Capitolo 1</h1><h2>Sezione A</h2><p>Il primo fatto.</p>', 1);
        $this->module($course, '<h1>Capitolo 2</h1><p>Il secondo fatto.</p>', 2);

        $this->artisan('course:recover-source-from-modules', ['course_id' => $course->id])
            ->assertExitCode(0);

        $this->assertDatabaseHas('course_sources', ['course_id' => $course->id, 'version' => '1.0']);
        $src = CourseSource::where('course_id', $course->id)->first();
        $this->assertNotEmpty($src->blocks);
        // Ogni blocco ha la forma attesa {id,text,type} come dal docx.
        $this->assertArrayHasKey('id', $src->blocks[0]);
        $this->assertArrayHasKey('type', $src->blocks[0]);
        $this->assertStringContainsString('fatto', json_encode($src->blocks, JSON_UNESCAPED_UNICODE));
    }

    public function test_rejects_duplicate_version(): void
    {
        $this->skipIfNoPandoc();
        $course = $this->course();
        $this->module($course, '<h1>Cap</h1><p>Testo.</p>', 1);
        CourseSource::create(['course_id' => $course->id, 'version' => '1.0', 'blocks' => [['id' => 'p1', 'text' => 'x', 'type' => 'PART']]]);

        $this->artisan('course:recover-source-from-modules', ['course_id' => $course->id])
            ->assertExitCode(1);
    }

    public function test_fails_on_uuid_not_valid(): void
    {
        $this->artisan('course:recover-source-from-modules', ['course_id' => 'non-un-uuid'])
            ->assertExitCode(1);
    }

    public function test_fails_when_modules_empty(): void
    {
        $this->skipIfNoPandoc();
        $course = $this->course(); // nessun modulo
        $this->artisan('course:recover-source-from-modules', ['course_id' => $course->id])
            ->assertExitCode(1);
    }
}
