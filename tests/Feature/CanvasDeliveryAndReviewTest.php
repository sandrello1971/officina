<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Material;
use App\Models\Module;
use App\Models\Student;
use App\Models\StudentCanvasData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CanvasDeliveryAndReviewTest extends TestCase
{
    use RefreshDatabase;

    private const CANVAS_HTML = <<<'HTML'
<!DOCTYPE html><html><head><title>Scheda</title></head><body>
<h1>Scheda di analisi bug</h1>
<div class="card"><h2>Fenomeno osservato</h2><textarea data-field="fenomeno"></textarea></div>
<div class="card"><h2>Causa radice confermata</h2><textarea data-field="causa"></textarea></div>
<script>var t=(document.querySelector('meta[name=csrf-token]')||{}).content;fetch('/learn/canvas/'+MID+'/data');</script>
</body></html>
HTML;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function makeStudent(array $attrs = []): Student
    {
        return Student::create(array_merge([
            'name'                 => 'Tizio ' . uniqid(),
            'email'                => 'tizio+' . uniqid() . '@example.com',
            'password'             => bcrypt('secret-pw'),
            'is_active'            => true,
            'is_demo'              => false,
            'must_change_password' => false,
        ], $attrs));
    }

    private function actingAsStudent(Student $student): self
    {
        return $this->withSession([
            'student_id'    => $student->id,
            'student_email' => $student->email,
            'student_name'  => $student->name,
        ]);
    }

    /** @return array{0:Course,1:Material} */
    private function courseWithCanvas(string $html = self::CANVAS_HTML): array
    {
        $course = Course::create(['name' => 'Corso ' . uniqid(), 'slug' => 'corso-' . uniqid(), 'is_active' => true, 'sort_order' => 1]);
        $module = Module::create(['course_id' => $course->id, 'title' => 'Capitolo 5', 'sort_order' => 1, 'is_active' => true]);
        Storage::disk('local')->put('materials/x/scheda.html', $html);
        $canvas = Material::create([
            'course_id' => $course->id, 'module_id' => $module->id, 'title' => 'Canvas — Scheda di analisi bug',
            'file_path' => 'materials/x/scheda.html', 'file_type' => 'canvas', 'sort_order' => 0,
        ]);

        return [$course, $canvas];
    }

    private function enrolledStudent(Course $course): Student
    {
        $student = $this->makeStudent();
        $student->courses()->attach($course->id, ['enrolled_at' => now(), 'is_active' => true]);

        return $student;
    }

    // ---- serving del canvas ------------------------------------------------

    public function test_canvas_is_served_with_current_endpoint_csrf_and_toolbar(): void
    {
        [$course, $canvas] = $this->courseWithCanvas();
        $student = $this->enrolledStudent($course);

        $html = $this->actingAsStudent($student)
            ->get(route('student.material.canvas', $canvas) . '?mid=' . $canvas->id)
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=utf-8')
            ->getContent();

        $this->assertStringNotContainsString('/learn/canvas/', $html);
        $this->assertStringContainsString("fetch('/canvas/'+MID+'/data')", $html);
        $this->assertStringContainsString('<meta name="csrf-token"', $html);
        $this->assertStringContainsString('officina-canvas-toolbar', $html);
    }

    public function test_read_only_canvas_gets_no_toolbar(): void
    {
        [$course, $canvas] = $this->courseWithCanvas('<html><head></head><body><h1>Laboratorio 2</h1><p>Istruzioni</p></body></html>');
        $student = $this->enrolledStudent($course);

        $html = $this->actingAsStudent($student)->get(route('student.material.canvas', $canvas))->assertOk()->getContent();

        $this->assertStringNotContainsString('officina-canvas-toolbar', $html);
    }

    public function test_student_saves_and_reloads_canvas_on_the_rewritten_endpoint(): void
    {
        [$course, $canvas] = $this->courseWithCanvas();
        $student = $this->enrolledStudent($course);

        // stesso path che il canvas servito usa per il salvataggio, sull'host learn
        $url = route('student.canvas.save', $canvas);
        $this->assertStringEndsWith('/canvas/' . $canvas->id . '/data', $url);

        $this->actingAsStudent($student)
            ->patchJson($url, ['data' => ['fenomeno' => 'Mancano 2 articoli', 'causa' => 'except inghiottito']])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->actingAsStudent($student)
            ->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.fenomeno', 'Mancano 2 articoli');
    }

    // ---- vista formatore ---------------------------------------------------

    public function test_platform_instructor_reviews_enrolled_students_canvases_with_labels(): void
    {
        [$course, $canvas] = $this->courseWithCanvas();
        $student = $this->enrolledStudent($course);
        StudentCanvasData::create(['student_id' => $student->id, 'material_id' => $canvas->id, 'data' => [
            'fenomeno' => 'Il report mostra 6 articoli su 8',
            'causa' => 'Formato numerico italiano',
        ]]);
        $instructor = $this->makeStudent(['role' => 'instructor', 'auto_enroll_all_courses' => true]);

        $this->actingAsStudent($instructor)
            ->get(route('student.course.canvas-review.index', $course->slug))
            ->assertOk()
            ->assertSee('Canvas — Scheda di analisi bug')
            ->assertSee('1 / 1');

        $this->actingAsStudent($instructor)
            ->get(route('student.course.canvas-review.show', [$course->slug, $canvas]))
            ->assertOk()
            ->assertSee($student->name)
            ->assertSee('Fenomeno osservato')
            ->assertSee('Il report mostra 6 articoli su 8')
            ->assertSee('Causa radice confermata');
    }

    public function test_course_page_links_review_for_instructor(): void
    {
        [$course] = $this->courseWithCanvas();
        $instructor = $this->makeStudent(['role' => 'instructor', 'auto_enroll_all_courses' => true]);

        $this->actingAsStudent($instructor)
            ->get(route('student.course.show', $course))
            ->assertOk()
            ->assertSee('Schede dei discenti');
    }

    public function test_student_cannot_review_canvases(): void
    {
        [$course, $canvas] = $this->courseWithCanvas();
        $student = $this->enrolledStudent($course);

        $this->actingAsStudent($student)->get(route('student.course.canvas-review.index', $course->slug))->assertForbidden();
        $this->actingAsStudent($student)->get(route('student.course.canvas-review.show', [$course->slug, $canvas]))->assertForbidden();
    }

    public function test_instructor_enrolled_as_student_cannot_review_others(): void
    {
        [$course] = $this->courseWithCanvas();
        $instructor = $this->makeStudent(['role' => 'instructor']);
        $instructor->courses()->attach($course->id, ['enrolled_at' => now(), 'is_active' => true]);

        $this->actingAsStudent($instructor)->get(route('student.course.canvas-review.index', $course->slug))->assertForbidden();
    }

    public function test_review_rejects_canvas_of_another_course(): void
    {
        [$course] = $this->courseWithCanvas();
        [, $otherCanvas] = $this->courseWithCanvas();
        $instructor = $this->makeStudent(['role' => 'instructor', 'auto_enroll_all_courses' => true]);

        $this->actingAsStudent($instructor)
            ->get(route('student.course.canvas-review.show', [$course->slug, $otherCanvas]))
            ->assertNotFound();
    }

    // ---- materiale riservato HTML (chiave del laboratorio) ------------------

    public function test_instructor_html_material_is_rendered_from_file(): void
    {
        [$course] = $this->courseWithCanvas();
        Storage::disk('local')->put('materials/x/chiave.html', '<h1>Chiave</h1><p>Causa: default mutabile</p>');
        $key = Material::create([
            'course_id' => $course->id, 'title' => 'Laboratorio 2 — Chiave per il formatore',
            'file_path' => 'materials/x/chiave.html', 'file_type' => 'html', 'is_instructor_only' => true, 'sort_order' => 10,
        ]);
        $instructor = $this->makeStudent(['role' => 'instructor', 'auto_enroll_all_courses' => true]);

        $this->actingAsStudent($instructor)
            ->get(route('student.instructor.material.show', [$course->slug, $key]))
            ->assertOk()
            ->assertSee('Causa: default mutabile', false);

        $this->actingAsStudent($this->enrolledStudent($course))
            ->get(route('student.instructor.material.show', [$course->slug, $key]))
            ->assertForbidden();
    }

    // ---- installazione del kit ---------------------------------------------

    public function test_lab_kit_command_installs_canvases_repo_and_key_idempotently(): void
    {
        $course = Course::create(['name' => 'Claude Code', 'slug' => 'claude-code-supporto-ai-al-lavoro-sul-codice', 'is_active' => true, 'sort_order' => 1]);
        $dir = 'materials/claude-code-supporto-ai-al-lavoro-sul-codice';
        foreach (['canvas-analisi-bug.html', 'lab-1-configurazione-e-comprensione.html', 'lab-2-analisi-e-debug.html'] as $i => $file) {
            $module = Module::create(['course_id' => $course->id, 'title' => "Capitolo $i", 'sort_order' => $i + 1, 'is_active' => true]);
            Storage::disk('local')->put("$dir/$file", '<html>vecchio</html>');
            Material::create(['course_id' => $course->id, 'module_id' => $module->id, 'title' => $file,
                'file_path' => "$dir/$file", 'file_type' => 'canvas', 'sort_order' => 0]);
        }

        $this->artisan('course:install-claude-code-lab-kit', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame('<html>vecchio</html>', Storage::disk('local')->get("$dir/lab-2-analisi-e-debug.html"));

        $this->artisan('course:install-claude-code-lab-kit')->assertSuccessful();
        $this->artisan('course:install-claude-code-lab-kit')->assertSuccessful();

        $this->assertStringContainsString('SEGNALAZIONI.md', Storage::disk('local')->get("$dir/lab-2-analisi-e-debug.html"));
        $this->assertNotEmpty(Storage::disk('local')->files($dir), 'backup presenti');
        $this->assertTrue(collect(Storage::disk('local')->files($dir))->contains(fn ($f) => str_contains($f, 'lab-2-analisi-e-debug.html.bak-')));
        $this->assertSame(2, Material::where('file_path', "$dir/magazzino-esercitazione.zip")->count());
        $this->assertSame(1, Material::where('file_path', "$dir/chiave-formatore-lab-2.html")->where('is_instructor_only', true)->count());

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open(Storage::disk('local')->path("$dir/magazzino-esercitazione.zip")) === true);
        $this->assertNotFalse($zip->locateName('magazzino-esercitazione/SEGNALAZIONI.md'));
        $this->assertNotFalse($zip->locateName('magazzino-esercitazione/magazzino/importa.py'));
        $zip->close();
    }
}
