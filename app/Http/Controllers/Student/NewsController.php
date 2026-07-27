<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\NewsItem;
use Illuminate\Http\Request;

/**
 * News AI — fruizione discente. Feed GLOBALE (tutti i discenti autenticati vedono le stesse
 * news), mostra SOLO le news pubblicate dall'admin. Filtro opzionale per tag/argomento.
 */
class NewsController extends Controller
{
    public function index(Request $request)
    {
        $published = NewsItem::published()->orderByDesc('published_at')->get();

        // Tag distinti dalle news pubblicate (per i chip di filtro).
        $allTags = $published
            ->flatMap(fn (NewsItem $n) => is_array($n->tags) ? $n->tags : [])
            ->unique()
            ->sort()
            ->values();

        $activeTag = $request->query('tag');
        $items = $activeTag
            ? $published->filter(fn (NewsItem $n) => is_array($n->tags) && in_array($activeTag, $n->tags, true))->values()
            : $published;

        return view('student.news.index', compact('items', 'allTags', 'activeTag'));
    }
}
