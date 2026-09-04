<?php

namespace Tests\Feature\News;

use App\Models\NewsItem;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fruizione discente: /learn/news mostra SOLO le news pubblicate; bozze e scartate no.
 * Filtro per tag via query-string.
 */
class StudentNewsVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function student(): Student
    {
        return Student::create([
            'name' => 'Discente ' . uniqid(),
            'email' => 'd+' . uniqid() . '@example.com',
            'password' => bcrypt('secret-pw'),
            'is_active' => true, 'is_demo' => false, 'must_change_password' => false,
        ]);
    }

    private function actingAsStudent(Student $s): self
    {
        return $this->withSession(['student_id' => $s->id, 'student_email' => $s->email, 'student_name' => $s->name]);
    }

    public function test_mostra_solo_pubblicate(): void
    {
        NewsItem::create(['title' => 'PUBBLICATA', 'summary' => 's', 'status' => 'published', 'published_at' => now(), 'tags' => ['AI Act']]);
        NewsItem::create(['title' => 'BOZZA', 'summary' => 's', 'status' => 'draft']);
        NewsItem::create(['title' => 'SCARTATA', 'summary' => 's', 'status' => 'rejected']);

        $res = $this->actingAsStudent($this->student())->get($this->learnUrl('/news'));

        $res->assertOk();
        $res->assertSee('PUBBLICATA');
        $res->assertDontSee('BOZZA');
        $res->assertDontSee('SCARTATA');
    }

    public function test_filtro_per_tag(): void
    {
        NewsItem::create(['title' => 'GOV', 'summary' => 's', 'status' => 'published', 'published_at' => now(), 'tags' => ['governance']]);
        NewsItem::create(['title' => 'RIC', 'summary' => 's', 'status' => 'published', 'published_at' => now(), 'tags' => ['ricerca']]);

        $res = $this->actingAsStudent($this->student())->get($this->learnUrl('/news?tag=governance'));

        $res->assertOk();
        $res->assertSee('GOV');
        $res->assertDontSee('RIC');
    }
}
