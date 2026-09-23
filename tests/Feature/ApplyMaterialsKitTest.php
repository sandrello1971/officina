<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Material;
use App\Models\Module;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApplyMaterialsKitTest extends TestCase
{
    use RefreshDatabase;

    private string $kit;
    private string $kitDir;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->kit = 'test-kit-' . uniqid();
        $this->kitDir = resource_path('course-kits/' . $this->kit);
        File::ensureDirectoryExists($this->kitDir . '/files/materials/c');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->kitDir);
        parent::tearDown();
    }

    /** @return array{0:Course,1:Module,2:Module,3:Material} */
    private function fixture(): array
    {
        $course = Course::create(['name' => 'Corso', 'slug' => 'corso-kit', 'is_active' => true, 'sort_order' => 1]);
        $m1 = Module::create(['course_id' => $course->id, 'title' => 'Parte 2', 'sort_order' => 1, 'is_active' => true]);
        $m2 = Module::create(['course_id' => $course->id, 'title' => 'Parte 3', 'sort_order' => 2, 'is_active' => true]);
        Storage::disk('local')->put('materials/c/a.html', 'VECCHIO Modulo 2');
        $mat = Material::create(['course_id' => $course->id, 'module_id' => $m2->id, 'title' => 'PACK_canvas-a',
            'file_path' => 'materials/c/a.html', 'file_type' => 'canvas', 'sort_order' => 0]);

        File::put($this->kitDir . '/files/materials/c/a.html', 'NUOVO Parte 2');
        File::put($this->kitDir . '/files/materials/c/b.html', 'COPIA');
        File::put($this->kitDir . '/manifest.json', json_encode([
            'files' => [['path' => 'materials/c/a.html', 'sha256_before' => hash('sha256', 'VECCHIO Modulo 2')]],
            'new_files' => [['path' => 'materials/c/b.html']],
            'update' => [['id' => $mat->id, 'expect' => ['title' => 'PACK_canvas-a'], 'set' => ['title' => 'Canvas — A', 'module_id' => $m1->id]]],
            'create' => [['match' => ['module_id' => $m1->id, 'title' => 'Canvas — B'], 'set' => ['course_id' => 'COURSE:corso-kit', 'file_path' => 'materials/c/b.html', 'file_type' => 'canvas', 'sort_order' => 5]]],
        ]));

        return [$course, $m1, $m2, $mat];
    }

    public function test_dry_run_writes_nothing(): void
    {
        [, , , $mat] = $this->fixture();

        $this->artisan('materials:apply-kit', ['kit' => $this->kit, '--dry-run' => true])->assertSuccessful();

        $this->assertSame('VECCHIO Modulo 2', Storage::disk('local')->get('materials/c/a.html'));
        $this->assertFalse(Storage::disk('local')->exists('materials/c/b.html'));
        $this->assertSame('PACK_canvas-a', $mat->fresh()->title);
        $this->assertSame(1, Material::count());
    }

    public function test_applies_everything_with_backup_and_is_idempotent(): void
    {
        [$course, $m1, , $mat] = $this->fixture();

        $this->artisan('materials:apply-kit', ['kit' => $this->kit])->assertSuccessful();
        $this->artisan('materials:apply-kit', ['kit' => $this->kit])->assertSuccessful()->expectsOutputToContain('già applicato');

        $this->assertSame('NUOVO Parte 2', Storage::disk('local')->get('materials/c/a.html'));
        $this->assertCount(1, array_filter(Storage::disk('local')->files('materials/c'), fn ($f) => str_contains($f, 'a.html.bak-')));
        $this->assertSame('COPIA', Storage::disk('local')->get('materials/c/b.html'));
        $this->assertSame('Canvas — A', $mat->fresh()->title);
        $this->assertSame($m1->id, $mat->fresh()->module_id);
        $created = Material::where('title', 'Canvas — B')->sole();
        $this->assertSame($course->id, $created->course_id);
        $this->assertSame(5, (int) $created->file_size);
    }

    public function test_aborts_without_changes_if_a_file_was_modified_meanwhile(): void
    {
        [, , , $mat] = $this->fixture();
        Storage::disk('local')->put('materials/c/a.html', 'MODIFICATO A MANO');

        $this->artisan('materials:apply-kit', ['kit' => $this->kit])->assertFailed();

        $this->assertSame('MODIFICATO A MANO', Storage::disk('local')->get('materials/c/a.html'));
        $this->assertSame('PACK_canvas-a', $mat->fresh()->title);
        $this->assertFalse(Storage::disk('local')->exists('materials/c/b.html'));
    }

    public function test_aborts_if_a_title_is_not_the_expected_one(): void
    {
        [, , , $mat] = $this->fixture();
        $mat->update(['title' => 'Rinominato da admin']);

        $this->artisan('materials:apply-kit', ['kit' => $this->kit])->assertFailed();

        $this->assertSame('VECCHIO Modulo 2', Storage::disk('local')->get('materials/c/a.html'));
    }
}
