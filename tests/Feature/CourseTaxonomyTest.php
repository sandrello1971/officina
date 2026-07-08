<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\CourseTag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseTaxonomyTest extends TestCase
{
    use RefreshDatabase;

    private function makeCourse(string $slug = null, int $order = 1): Course
    {
        return Course::create([
            'name'       => 'Corso ' . ($slug ?? uniqid()),
            'slug'       => $slug ?? ('corso-' . uniqid()),
            'is_active'  => true,
            'sort_order' => $order,
        ]);
    }

    private function makeCategory(string $name): CourseCategory
    {
        return CourseCategory::create([
            'name' => $name,
            'slug' => \Str::slug($name) . '-' . uniqid(),
        ]);
    }

    private function makeTag(string $name): CourseTag
    {
        return CourseTag::create([
            'name' => $name,
            'slug' => \Str::slug($name) . '-' . uniqid(),
        ]);
    }

    public function test_course_has_at_most_one_category_and_reassigning_replaces(): void
    {
        $course = $this->makeCourse();
        $catA = $this->makeCategory('Sicurezza');
        $catB = $this->makeCategory('Qualità');

        $course->course_category_id = $catA->id;
        $course->save();
        $this->assertSame($catA->id, $course->fresh()->category->id);

        // Riassegnando, sostituisce (non accumula).
        $course->course_category_id = $catB->id;
        $course->save();
        $this->assertSame($catB->id, $course->fresh()->category->id);
        $this->assertSame(1, $catB->fresh()->courses()->count());
        $this->assertSame(0, $catA->fresh()->courses()->count());
    }

    public function test_course_can_have_many_tags_via_attach_then_sync(): void
    {
        $course = $this->makeCourse();
        $t1 = $this->makeTag('Base');
        $t2 = $this->makeTag('Avanzato');
        $t3 = $this->makeTag('Aggiornamento');

        $course->tags()->attach([$t1->id, $t2->id]);
        $this->assertEqualsCanonicalizing(
            [$t1->id, $t2->id],
            $course->fresh()->tags->pluck('id')->all()
        );

        // sync sostituisce l'insieme.
        $course->tags()->sync([$t2->id, $t3->id]);
        $this->assertEqualsCanonicalizing(
            [$t2->id, $t3->id],
            $course->fresh()->tags->pluck('id')->all()
        );
    }

    public function test_deleting_category_nulls_fk_and_course_survives(): void
    {
        $category = $this->makeCategory('Temporanea');
        $course = $this->makeCourse();
        $course->course_category_id = $category->id;
        $course->save();

        $category->delete();

        $fresh = $course->fresh();
        $this->assertNotNull($fresh, 'Il corso deve sopravvivere alla cancellazione della categoria');
        $this->assertNull($fresh->course_category_id);
    }

    public function test_deleting_tag_cleans_pivot_and_course_survives(): void
    {
        $course = $this->makeCourse();
        $tag = $this->makeTag('Effimero');
        $course->tags()->attach($tag->id);

        $tag->delete();

        $fresh = $course->fresh();
        $this->assertNotNull($fresh, 'Il corso deve sopravvivere alla cancellazione del tag');
        $this->assertSame(0, $fresh->tags()->count());
        $this->assertDatabaseMissing('course_course_tag', ['course_tag_id' => $tag->id]);
    }
}
