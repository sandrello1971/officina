<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CourseCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * CRUD categorie corso (tassonomia esclusiva). Nessun seed: le categorie si
 * creano per-deployment da qui.
 */
class CourseCategoryController extends Controller
{
    public function index()
    {
        $categories = CourseCategory::ordered()->withCount('courses')->get();

        return view('admin.course-categories.index', compact('categories'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'slug'       => 'nullable|string|max:255|unique:course_categories,slug',
            'color'      => 'nullable|string|max:20',
            'sort_order' => 'nullable|integer',
        ]);

        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
        $data['sort_order'] = $data['sort_order'] ?? 0;

        CourseCategory::create($data);

        return redirect()->route('admin.course-categories.index')
            ->with('success', 'Categoria creata.');
    }

    public function update(Request $request, string $id)
    {
        $category = CourseCategory::findOrFail($id);

        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'slug'       => 'nullable|string|max:255|unique:course_categories,slug,' . $category->id,
            'color'      => 'nullable|string|max:20',
            'sort_order' => 'nullable|integer',
        ]);

        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);
        $data['sort_order'] = $data['sort_order'] ?? 0;

        $category->update($data);

        return redirect()->route('admin.course-categories.index')
            ->with('success', 'Categoria aggiornata.');
    }

    public function destroy(string $id)
    {
        $category = CourseCategory::findOrFail($id);
        $category->delete(); // FK nullOnDelete: i corsi restano, categoria → null

        return redirect()->route('admin.course-categories.index')
            ->with('success', 'Categoria eliminata. I corsi collegati restano senza categoria.');
    }
}
