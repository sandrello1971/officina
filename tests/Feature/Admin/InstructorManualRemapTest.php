<?php

namespace Tests\Feature\Admin;

use App\Models\Course;
use App\Models\InstructorManualSection;
use App\Models\Material;
use App\Models\Module;
use App\Services\InstructorManualRemapService;
use App\Services\InstructorManualSplitterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InstructorManualRemapTest extends TestCase
{
    use RefreshDatabase;

    private function course(): Course
    {
        return Course::create(['name' => 'CORSO', 'slug' => 'c-' . uniqid(), 'is_active' => true, 'sort_order' => 1]);
    }

    private function module(Course $c, int $sort, string $title): Module
    {
        return Module::create(['course_id' => $c->id, 'sort_order' => $sort, 'title' => $title, 'content' => '<p>x</p>']);
    }

    private function section(Material $mat, int $sort, string $title, ?string $moduleId = null, bool $manual = false): InstructorManualSection
    {
        return InstructorManualSection::create([
            'material_id' => $mat->id, 'course_id' => $mat->course_id, 'module_id' => $moduleId,
            'title' => $title, 'anchor' => 'a-' . uniqid(), 'heading_level' => 2, 'sort_order' => $sort,
            'content_html' => "<h2>{$title}</h2><p>contenuto</p>", 'module_assigned_manually' => $manual,
        ]);
    }

    /** L'euristica abbina "Modulo N" per TITOLO del modulo, non per sort_order (off-by-one corretto). */
    public function test_euristica_abbina_per_titolo_non_per_sort_order(): void
    {
        $c = $this->course();
        $this->module($c, 0, 'Frontespizio');
        $this->module($c, 1, 'Modulo 0 — Ambiente');       // vecchio off-by-one avrebbe scelto questo
        $target = $this->module($c, 3, 'Modulo 1 — Cos\'è un modello');

        $modules = Module::where('course_id', $c->id)->orderBy('sort_order')->get();
        $splitter = app(InstructorManualSplitterService::class);

        $this->assertSame($target->id, $splitter->autoMapToModule('Capitolo 4 — Modulo 1: cos\'è un modello', $modules));
    }

    /** Tassonomia diversa (formatore "Modulo N" vs discente "Parte N") → nessun match euristico. */
    public function test_euristica_null_su_tassonomie_diverse(): void
    {
        $c = $this->course();
        $this->module($c, 0, 'Parte 1 — I fondamenti');

        $modules = Module::where('course_id', $c->id)->orderBy('sort_order')->get();
        $splitter = app(InstructorManualSplitterService::class);

        $this->assertNull($splitter->autoMapToModule('Capitolo 4 — Modulo 1: Fondamenti', $modules));
    }

    /** Blocco X abbinato per lettera nel titolo del modulo. */
    public function test_euristica_blocco_per_lettera(): void
    {
        $c = $this->course();
        $target = $this->module($c, 5, 'Workshop L2 — Blocco A');

        $modules = Module::where('course_id', $c->id)->orderBy('sort_order')->get();
        $splitter = app(InstructorManualSplitterService::class);

        $this->assertSame($target->id, $splitter->autoMapToModule('Capitolo 9 — Blocco A: audit', $modules));
    }

    /** Il remap corregge una mappatura AUTO sbagliata, preserva quella MANUALE, e dry-run non scrive. */
    public function test_remap_corregge_auto_preserva_manuale_e_dry_run(): void
    {
        $c = $this->course();
        $wrong = $this->module($c, 1, 'Modulo 0 — Ambiente');
        $right = $this->module($c, 3, 'Modulo 1 — Cos\'è un modello');
        $man   = $this->module($c, 0, 'Frontespizio');

        $mat = Material::create(['course_id' => $c->id, 'title' => 'Manuale', 'is_instructor_only' => true, 'content_html' => '<p>x</p>']);
        $sAuto   = $this->section($mat, 0, 'Modulo 1 — Cos\'è un modello', $wrong->id, false);        // AUTO sbagliata
        $sManual = $this->section($mat, 1, 'Modulo 1 — altro', $man->id, true);                       // MANUALE, da preservare
        $sGlobal = $this->section($mat, 2, 'MANUALE DEL FORMATORE', null, false);                     // globale → resta null

        $svc = app(InstructorManualRemapService::class);

        // dry-run: rileva il cambiamento ma NON scrive
        $dry = $svc->remap($mat, false, true);
        $this->assertCount(1, $dry['changes']);
        $this->assertSame($wrong->id, $sAuto->fresh()->module_id, 'dry-run non deve scrivere');

        // apply
        $svc->remap($mat, false, false);
        $this->assertSame($right->id, $sAuto->fresh()->module_id, 'AUTO corretta al modulo giusto');
        $this->assertSame($man->id, $sManual->fresh()->module_id, 'MANUALE preservata');
        $this->assertNull($sGlobal->fresh()->module_id, 'globale resta non mappata');
    }

    /** Con --ai, le sezioni non risolte dall'euristica sono mappate dalla proposta di Claude. */
    public function test_fallback_ai_mappa_le_residue(): void
    {
        $c = $this->course();
        $parte1 = $this->module($c, 0, 'Parte 1 — I fondamenti');

        $mat = Material::create(['course_id' => $c->id, 'title' => 'Manuale', 'is_instructor_only' => true, 'content_html' => '<p>x</p>']);
        $sec = $this->section($mat, 0, 'Capitolo 4 — Modulo 1: Fondamenti', null, false); // euristica → null

        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['text' => json_encode(['map' => [
                ['section_id' => $sec->id, 'module_sort_order' => 0],
            ]])]],
        ], 200)]);

        $r = app(InstructorManualRemapService::class)->remap($mat, true, false);

        $this->assertTrue($r['ai_used']);
        $this->assertSame(1, $r['ai_assigned']);
        $this->assertSame($parte1->id, $sec->fresh()->module_id);
    }
}
