<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Course;
use App\Models\InstructorManualSection;
use App\Models\Material;
use App\Models\Module;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Editor admin del contenuto del manuale formatore: salva materials.content_html
 * (master letto dai formatori), poi ri-deriva le sezioni preservando le mappature
 * manuali dei moduli. Non tocca il .docx sorgente.
 */
class InstructorManualEditContentTest extends TestCase
{
    use RefreshDatabase;

    private string $adminEmail = 'edit@ente.it';

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(); // il RAG non deve chiamare servizi esterni nei test
        Admin::create(['name' => 'Ed', 'email' => $this->adminEmail, 'password' => 'pw', 'is_active' => true]);
    }

    private function actingAdmin()
    {
        return $this->withSession(['admin_logged_in' => true, 'admin_email' => $this->adminEmail]);
    }

    private function course(): Course
    {
        return Course::create(['name' => 'CORSO', 'slug' => 'c-' . uniqid(), 'is_active' => true, 'sort_order' => 1]);
    }

    private function manual(Course $course): Material
    {
        return Material::create([
            'course_id' => $course->id,
            'title' => 'Manuale',
            'is_instructor_only' => true,
            'content_html' => '<h2>Alpha</h2><p>Testo iniziale.</p>',
        ]);
    }

    public function test_salva_contenuto_e_rideriva_le_sezioni(): void
    {
        $course = $this->course();
        $material = $this->manual($course);

        $newHtml = '<h2>Alpha</h2><p>Testo aggiornato.</p><h2>Beta</h2><p>Seconda sezione.</p>';

        $this->actingAdmin()
            ->put(route('admin.courses.instructor-materials.content.update', [$course->id, $material->id]), [
                'content_html' => $newHtml,
            ])
            ->assertRedirect(route('admin.courses.instructor-materials.edit-content', [$course->id, $material->id]));

        $this->assertSame($newHtml, $material->fresh()->content_html);

        $sections = InstructorManualSection::where('material_id', $material->id)->orderBy('sort_order')->get();
        $this->assertCount(2, $sections);
        $this->assertSame(['Alpha', 'Beta'], $sections->pluck('title')->all());
    }

    public function test_preserva_la_mappatura_modulo_manuale(): void
    {
        $course = $this->course();
        $material = $this->manual($course);
        $module = Module::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0, 'content' => '<p>x</p>']);

        // Prima derivazione: crea la sezione "Alpha".
        $this->actingAdmin()->put(
            route('admin.courses.instructor-materials.content.update', [$course->id, $material->id]),
            ['content_html' => '<h2>Alpha</h2><p>Uno.</p>']
        );

        // Mappatura manuale della sezione a un modulo.
        $section = InstructorManualSection::where('material_id', $material->id)->firstOrFail();
        $section->update(['module_id' => $module->id, 'module_assigned_manually' => true]);

        // Nuovo edit dello stesso heading → la mappatura manuale sopravvive al re-split.
        $this->actingAdmin()->put(
            route('admin.courses.instructor-materials.content.update', [$course->id, $material->id]),
            ['content_html' => '<h2>Alpha</h2><p>Uno modificato.</p>']
        );

        $reSplit = InstructorManualSection::where('material_id', $material->id)->firstOrFail();
        $this->assertSame($module->id, $reSplit->module_id);
        $this->assertTrue($reSplit->module_assigned_manually);
    }

    public function test_404_se_materiale_non_e_manuale_formatore(): void
    {
        $course = $this->course();
        $material = Material::create([
            'course_id' => $course->id,
            'title' => 'Normale',
            'is_instructor_only' => false,
            'content_html' => '<p>pubblico</p>',
        ]);

        $this->actingAdmin()->put(
            route('admin.courses.instructor-materials.content.update', [$course->id, $material->id]),
            ['content_html' => '<h2>Hack</h2>']
        )->assertNotFound();
    }

    public function test_404_se_materiale_di_altro_corso(): void
    {
        $course = $this->course();
        $other = $this->course();
        $material = $this->manual($other);

        $this->actingAdmin()->put(
            route('admin.courses.instructor-materials.content.update', [$course->id, $material->id]),
            ['content_html' => '<h2>Hack</h2>']
        )->assertNotFound();
    }
}
