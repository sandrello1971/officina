<?php

namespace Tests\Feature\P26;

use App\Models\Admin;
use App\Models\Course;
use App\Models\CourseFreshnessConfig;
use App\Models\CourseSource;
use App\Models\TrustedSource;
use App\Services\TopicSuggester;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * P26.1 — Topic suggester con anti-drift: legge il corso, propone un topic (riusando un esistente
 * se affine), normalizzato a slug; solo suggerisce (l'admin conferma). Isolato.
 */
class TopicSuggesterTest extends TestCase
{
    use RefreshDatabase;

    private string $adminEmail = 'rev@ente.it';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.p26.enabled' => true]);
        Admin::create(['name' => 'Rev', 'email' => $this->adminEmail, 'password' => 'pw', 'is_active' => true]);
    }

    private function actingAdmin()
    {
        return $this->withSession(['admin_logged_in' => true, 'admin_email' => $this->adminEmail]);
    }

    private function course(string $name = 'FREQUENZA — Agenti AI per PMI'): Course
    {
        $c = Course::create(['name' => $name, 'slug' => 'c-' . uniqid(), 'is_active' => true, 'sort_order' => 1]);
        CourseSource::create(['course_id' => $c->id, 'version' => '1.0', 'blocks' => [
            ['id' => 'p1-cap1', 'type' => 'H1', 'text' => 'Introduzione agli agenti AI'],
            ['id' => 'p1-cap1-p1', 'type' => 'P', 'text' => 'Gli agenti AI percepiscono e agiscono in autonomia.'],
        ]]);
        return $c;
    }

    private function fakeTopic(array $json): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode($json)]],
        ], 200)]);
    }

    // ---- propone uno slug normalizzato ----

    public function test_propone_slug_normalizzato(): void
    {
        $this->fakeTopic(['suggested_topic' => 'Agenti AI', 'is_existing' => false, 'alternatives' => ['AI Generativa']]);

        $res = app(TopicSuggester::class)->suggest($this->course());

        $this->assertSame('agenti-ai', $res['suggested_topic']); // slug
        $this->assertContains('ai-generativa', $res['alternatives']);
    }

    // ---- ANTI-DRIFT: riusa un topic esistente affine + passa la lista all'LLM ----

    public function test_anti_drift_riusa_esistente_e_passa_la_lista(): void
    {
        TrustedSource::create(['label' => 'arXiv', 'url_or_domain' => 'arxiv.org', 'mode' => 'search',
            'topic' => 'agenti-ai', 'status' => 'approved', 'proposed_by' => 'admin']);
        $this->fakeTopic(['suggested_topic' => 'agenti-ai', 'is_existing' => true, 'alternatives' => []]);

        $res = app(TopicSuggester::class)->suggest($this->course());

        $this->assertSame('agenti-ai', $res['suggested_topic']);
        $this->assertTrue($res['is_existing']);
        // i topic esistenti sono stati passati all'LLM (meccanismo anti-drift)
        Http::assertSent(fn ($req) => str_contains($req->body(), 'agenti-ai'));
    }

    public function test_topic_nuovo_se_nessuno_affine(): void
    {
        // Esiste solo 'agenti-ai'; corso di elettronica → topic nuovo.
        TrustedSource::create(['label' => 'x', 'url_or_domain' => 'arxiv.org', 'mode' => 'search',
            'topic' => 'agenti-ai', 'status' => 'approved', 'proposed_by' => 'admin']);
        $this->fakeTopic(['suggested_topic' => 'elettronica', 'is_existing' => false, 'alternatives' => []]);

        $res = app(TopicSuggester::class)->suggest($this->course('Elettronica di base'));

        $this->assertSame('elettronica', $res['suggested_topic']);
        $this->assertFalse($res['is_existing']);
    }

    public function test_is_existing_verificato_lato_server(): void
    {
        // L'LLM "mente" (is_existing=true) ma lo slug non è fra gli esistenti → il server lo corregge.
        $this->fakeTopic(['suggested_topic' => 'quasi-nuovo', 'is_existing' => true, 'alternatives' => []]);

        $res = app(TopicSuggester::class)->suggest($this->course());

        $this->assertFalse($res['is_existing']);
    }

    // ---- UI: suggerimento precompilato, richiede conferma admin per salvare ----

    public function test_suggerimento_precompila_ma_non_salva_finche_admin_non_conferma(): void
    {
        $course = $this->course();
        $this->fakeTopic(['suggested_topic' => 'agenti-ai', 'is_existing' => false, 'alternatives' => []]);

        // "Suggerisci topic" → flash della proposta, NIENTE salvato.
        $this->actingAdmin()->post(route('admin.coverage.topic.suggest', $course))
            ->assertRedirect()->assertSessionHas('topic_suggestion');
        $this->assertNull($course->fresh()->freshnessConfig?->topic);

        // L'admin conferma con "Salva topic".
        $this->actingAdmin()->post(route('admin.coverage.topic', $course), ['topic' => 'agenti-ai'])->assertRedirect();
        $this->assertSame('agenti-ai', $course->fresh()->freshnessConfig->topic);
    }

    // ---- isolamento: errore LLM → topic impostabile a mano ----

    public function test_errore_llm_isolato_topic_a_mano_resta_possibile(): void
    {
        $course = $this->course();
        Http::fake(['api.anthropic.com/*' => Http::response(['error' => ['message' => 'Your credit balance is too low']], 400)]);

        $this->actingAdmin()->post(route('admin.coverage.topic.suggest', $course))
            ->assertRedirect()->assertSessionHas('error');

        // Nonostante il fallimento del suggeritore, il topic si imposta a mano.
        $this->actingAdmin()->post(route('admin.coverage.topic', $course), ['topic' => 'agenti-ai'])->assertRedirect();
        $this->assertSame('agenti-ai', $course->fresh()->freshnessConfig->topic);
    }

    public function test_gating_off_404(): void
    {
        $course = $this->course();
        config(['services.p26.enabled' => false]);
        $this->actingAdmin()->post(route('admin.coverage.topic.suggest', $course))->assertNotFound();
    }
}
