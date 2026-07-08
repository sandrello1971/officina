<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CourseTag;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * CRUD tag corso (tassonomia trasversale). Nessun seed: i tag si creano
 * per-deployment da qui.
 */
class CourseTagController extends Controller
{
    public function index()
    {
        $tags = CourseTag::orderBy('name')->withCount('courses')->get();

        return view('admin.course-tags.index', compact('tags'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:course_tags,slug',
        ]);

        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        CourseTag::create($data);

        return redirect()->route('admin.course-tags.index')
            ->with('success', 'Tag creato.');
    }

    public function update(Request $request, string $id)
    {
        $tag = CourseTag::findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:course_tags,slug,' . $tag->id,
        ]);

        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);

        $tag->update($data);

        return redirect()->route('admin.course-tags.index')
            ->with('success', 'Tag aggiornato.');
    }

    public function destroy(string $id)
    {
        $tag = CourseTag::findOrFail($id);
        $tag->delete(); // pivot cascadeOnDelete: le assegnazioni ai corsi si puliscono

        return redirect()->route('admin.course-tags.index')
            ->with('success', 'Tag eliminato.');
    }
}
