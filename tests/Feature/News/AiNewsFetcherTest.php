<?php

namespace Tests\Feature\News;

use App\Models\NewsItem;
use App\Models\NewsRun;
use App\Services\News\AiNewsFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * AiNewsFetcher — recupero news via web_search. L'LLM è simulato con Http::fake: la
 * risposta ha un blocco web_search_tool_result (ignorato) + un blocco text col JSON items.
 * Verifica parsing, tag, filtro URL, dedup e scrittura come bozze.
 */
class AiNewsFetcherTest extends TestCase
{
    use RefreshDatabase;

    private function fakeItems(array $items): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response([
            'stop_reason' => 'end_turn',
            'content' => [
                // blocco tool-result del web (dati non fidati) — deve essere ignorato
                ['type' => 'web_search_tool_result', 'content' => [
                    ['type' => 'web_search_result', 'title' => 'x', 'url' => 'https://example.org'],
                ]],
                // giudizio finale del modello (usato)
                ['type' => 'text', 'text' => json_encode(['items' => $items], JSON_UNESCAPED_UNICODE)],
            ],
        ], 200)]);
    }

    public function test_crea_run_e_bozze_con_tag(): void
    {
        $this->fakeItems([
            ['title' => 'Nuovo modello AI', 'summary' => 'Sintesi della notizia.', 'source_url' => 'https://ec.europa.eu/x', 'source_name' => 'Commissione UE', 'published_date' => '2026-07-25', 'tags' => ['AI Act', 'governance'], 'confidence' => 0.9],
            ['title' => 'Studio su LLM', 'summary' => 'Altra sintesi.', 'source_url' => 'https://arxiv.org/abs/1', 'source_name' => 'arXiv', 'published_date' => null, 'tags' => ['ricerca'], 'confidence' => 0.7],
        ]);

        $run = app(AiNewsFetcher::class)->run();

        $this->assertSame('completed', $run->status);
        $this->assertSame(2, $run->items_found);
        $this->assertSame(2, NewsItem::count());

        $first = NewsItem::where('title', 'Nuovo modello AI')->first();
        $this->assertSame('draft', $first->status);
        $this->assertEqualsCanonicalizing(['AI Act', 'governance'], $first->tags);
        $this->assertSame('Commissione UE', $first->source_name);
        $this->assertSame('2026-07-25', $first->source_published_at->format('Y-m-d'));
        $this->assertNull($first->published_at);
    }

    public function test_scarta_item_senza_titolo_o_summary(): void
    {
        $this->fakeItems([
            ['title' => 'Valida', 'summary' => 'ok', 'source_url' => 'https://a.org', 'tags' => []],
            ['title' => '', 'summary' => 'senza titolo'],          // scartato
            ['summary' => 'manca il titolo'],                        // scartato
            ['title' => 'manca summary'],                           // scartato
        ]);

        $run = app(AiNewsFetcher::class)->run();

        $this->assertSame(1, $run->items_found);
        $this->assertSame(1, NewsItem::count());
    }

    public function test_dedup_su_source_url_recente(): void
    {
        // già presente da 3 giorni
        NewsItem::create(['title' => 'Vecchia', 'summary' => 's', 'source_url' => 'https://dup.org/a', 'status' => 'published', 'created_at' => now()->subDays(3)]);

        $this->fakeItems([
            ['title' => 'Duplicata', 'summary' => 's', 'source_url' => 'https://dup.org/a', 'tags' => []],
            ['title' => 'Nuova', 'summary' => 's', 'source_url' => 'https://nuova.org/b', 'tags' => []],
        ]);

        $run = app(AiNewsFetcher::class)->run();

        $this->assertSame(1, $run->items_found); // solo "Nuova"
        $this->assertNull(NewsItem::where('title', 'Duplicata')->first());
    }

    public function test_scarta_url_non_http(): void
    {
        $this->fakeItems([
            ['title' => 'Con url sporco', 'summary' => 's', 'source_url' => 'javascript:alert(1)', 'tags' => []],
        ]);

        app(AiNewsFetcher::class)->run();

        $this->assertNull(NewsItem::first()->source_url); // url non-http scartato
    }
}
