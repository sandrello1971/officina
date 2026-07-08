<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\CourseTag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gate 2 — Admin tassonomia: CRUD categorie/tag, assegnazione in create/update,
 * filtro (categoria + tag AND) e ricerca testuale case-insensitive nell'index.
 */
class CourseTaxonomyAdminTest extends TestCase
{
    use RefreshDatabase;

    private string $adminEmail = 'admin@ente.it';

    private function admin(): Admin
    {
        return Admin::create([
            'name' => 'Admin', 'email' => $this->adminEmail,
            'password' => 'secret-pw', 'is_active' => true,
        ]);
    }

    private function actingAdmin()
    {
        return $this->withSession(['admin_logged_in' => true, 'admin_email' => $this->adminEmail]);
    }

    private function course(string $name, string $short = null): Course
    {
        return Course::create([
            'name' => $name,
            'slug' => \Str::slug($name) . '-' . uniqid(),
            'short_description' => $short,
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    private function category(string $name): CourseCategory
    {
        return CourseCategory::create(['name' => $name, 'slug' => \Str::slug($name) . '-' . uniqid()]);
    }

    private function tag(string $name): CourseTag
    {
        return CourseTag::create(['name' => $name, 'slug' => \Str::slug($name) . '-' . uniqid()]);
    }

    public function test_index_without_params_returns_all_courses(): void
    {
        $this->admin();
        $a = $this->course('Alfa');
        $b = $this->course('Beta');

        $res = $this->actingAdmin()->get(route('admin.courses.index'));

        $res->assertOk();
        $res->assertSee('Alfa');
        $res->assertSee('Beta');
    }

    public function test_category_filter_narrows_results(): void
    {
        $this->admin();
        $cat = $this->category('Sicurezza');
        $inCat = $this->course('Corso Sicurezza');
        $inCat->course_category_id = $cat->id;
        $inCat->save();
        $this->course('Corso Altro');

        $res = $this->actingAdmin()->get(route('admin.courses.index', ['category' => $cat->id]));

        $res->assertOk();
        $res->assertSee('Corso Sicurezza');
        $res->assertDontSee('Corso Altro');
    }

    public function test_two_tag_filter_requires_all_tags_and(): void
    {
        $this->admin();
        $t1 = $this->tag('Base');
        $t2 = $this->tag('Avanzato');

        $both = $this->course('Corso Completo');
        $both->tags()->sync([$t1->id, $t2->id]);

        $onlyOne = $this->course('Corso Parziale');
        $onlyOne->tags()->sync([$t1->id]);

        $res = $this->actingAdmin()->get(route('admin.courses.index', ['tags' => [$t1->id, $t2->id]]));

        $res->assertOk();
        $res->assertSee('Corso Completo');
        $res->assertDontSee('Corso Parziale');
    }

    public function test_text_search_is_case_insensitive_on_name_and_short_description(): void
    {
        $this->admin();
        $this->course('Introduzione Cybersecurity');
        $this->course('Corso Generico', 'Approfondimento sulla CYBERSECURITY aziendale');
        $this->course('Corso Irrilevante', 'Contabilità di base');

        // match su name (lowercase query vs mixed-case name)
        $res = $this->actingAdmin()->get(route('admin.courses.index', ['q' => 'cybersecurity']));
        $res->assertOk();
        $res->assertSee('Introduzione Cybersecurity');
        $res->assertSee('Corso Generico'); // match su short_description uppercase
        $res->assertDontSee('Corso Irrilevante');
    }

    public function test_store_assigns_category_and_syncs_tags(): void
    {
        $this->admin();
        $cat = $this->category('Qualità');
        $t1 = $this->tag('ISO');
        $t2 = $this->tag('Audit');

        $res = $this->actingAdmin()->post(route('admin.courses.store'), [
            'name' => 'Corso Qualità',
            'course_category_id' => $cat->id,
            'tags' => [$t1->id, $t2->id],
            'is_active' => 1,
        ]);

        $res->assertRedirect();
        $course = Course::where('name', 'Corso Qualità')->firstOrFail();
        $this->assertSame($cat->id, $course->course_category_id);
        $this->assertEqualsCanonicalizing([$t1->id, $t2->id], $course->tags()->pluck('course_tags.id')->all());
    }

    public function test_update_replaces_category_and_resyncs_tags(): void
    {
        $this->admin();
        $catA = $this->category('Vecchia');
        $catB = $this->category('Nuova');
        $t1 = $this->tag('Uno');
        $t2 = $this->tag('Due');
        $t3 = $this->tag('Tre');

        $course = $this->course('Corso Mutabile');
        $course->course_category_id = $catA->id;
        $course->save();
        $course->tags()->sync([$t1->id, $t2->id]);

        $res = $this->actingAdmin()->put(route('admin.courses.update', $course->id), [
            'name' => 'Corso Mutabile',
            'slug' => $course->slug,
            'course_category_id' => $catB->id,
            'tags' => [$t2->id, $t3->id],
            'is_active' => 1,
        ]);

        $res->assertRedirect();
        $course->refresh();
        $this->assertSame($catB->id, $course->course_category_id);
        $this->assertEqualsCanonicalizing([$t2->id, $t3->id], $course->tags()->pluck('course_tags.id')->all());
    }

    public function test_admin_can_create_and_delete_category(): void
    {
        $this->admin();

        $this->actingAdmin()->post(route('admin.course-categories.store'), ['name' => 'Nuova Categoria'])
            ->assertRedirect();
        $cat = CourseCategory::where('name', 'Nuova Categoria')->firstOrFail();
        $this->assertNotNull($cat->slug);

        $this->actingAdmin()->delete(route('admin.course-categories.destroy', $cat->id))
            ->assertRedirect();
        $this->assertDatabaseMissing('course_categories', ['id' => $cat->id]);
    }

    public function test_admin_can_create_and_delete_tag(): void
    {
        $this->admin();

        $this->actingAdmin()->post(route('admin.course-tags.store'), ['name' => 'Nuovo Tag'])
            ->assertRedirect();
        $tag = CourseTag::where('name', 'Nuovo Tag')->firstOrFail();

        $this->actingAdmin()->delete(route('admin.course-tags.destroy', $tag->id))
            ->assertRedirect();
        $this->assertDatabaseMissing('course_tags', ['id' => $tag->id]);
    }
}
