<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\FetchAiNewsJob;
use App\Models\Admin;
use App\Models\NewsItem;
use App\Models\NewsRun;
use Illuminate\Http\Request;

/**
 * News AI — revisione HITL. Le news arrivano come bozze dal recupero settimanale; qui
 * l'admin le pubblica (visibili ai discenti), le scarta o le corregge. Il recupero manuale
 * ("Recupera ora") è sempre disponibile, a prescindere dal flag automatico.
 */
class NewsController extends Controller
{
    public function index()
    {
        $drafts = NewsItem::draft()->latest('created_at')->get();
        $published = NewsItem::published()->latest('published_at')->limit(50)->get();
        $lastRun = NewsRun::latest('created_at')->first();

        return view('admin.news.index', compact('drafts', 'published', 'lastRun'));
    }

    /** Recupero manuale immediato (test/on-demand): sempre consentito. */
    public function fetchNow()
    {
        FetchAiNewsJob::dispatch();

        return back()->with('success', 'Recupero news avviato. Fa una ricerca online e può richiedere qualche minuto: le bozze appariranno qui a breve.');
    }

    public function publish(NewsItem $news)
    {
        $news->update([
            'status' => 'published',
            'published_at' => now(),
            'reviewed_by' => $this->adminId(),
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'News pubblicata: ora è visibile ai discenti.');
    }

    public function reject(NewsItem $news)
    {
        $news->update([
            'status' => 'rejected',
            'reviewed_by' => $this->adminId(),
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'News scartata.');
    }

    /** Correzione redazionale prima/dopo la pubblicazione (titolo/riassunto/tag). */
    public function update(Request $request, NewsItem $news)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'summary' => 'required|string|max:4000',
            'tags' => 'nullable|string|max:255', // CSV di tag
        ]);

        $tags = collect(explode(',', (string) ($data['tags'] ?? '')))
            ->map(fn ($t) => trim($t))
            ->filter()
            ->take(4)
            ->values()
            ->all();

        $news->update([
            'title' => $data['title'],
            'summary' => $data['summary'],
            'tags' => $tags !== [] ? $tags : null,
        ]);

        return back()->with('success', 'News aggiornata.');
    }

    private function adminId(): ?string
    {
        return Admin::where('email', session('admin_email'))->value('id');
    }
}
