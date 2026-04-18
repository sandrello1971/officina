<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Module;
use Illuminate\Http\Request;

class ModuleController extends Controller
{
    public function index(Course $course)
    {
        $modules = $course->modules()->with('materials')->orderBy('sort_order')->get();
        return view('admin.courses.modules', compact('course', 'modules'));
    }

    public function create(Course $course)
    {
        return view('admin.courses.modules.create', compact('course'));
    }

    public function store(Request $request, Course $course)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'content' => 'nullable|string',
            'duration_minutes' => 'nullable|integer',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable',
        ]);

        $data['is_active'] = isset($data['is_active']);
        $data['course_id'] = $course->id;

        $module = Module::create($data);

        return redirect("/admin/courses/{$course->id}/modules/{$module->id}/edit")
            ->with('success', 'Modulo creato.');
    }

    public function show(Course $course, Module $module)
    {
        return redirect("/admin/courses/{$course->id}/modules/{$module->id}/edit");
    }

    public function edit(Course $course, Module $module)
    {
        $module->load('materials');
        return view('admin.courses.modules.edit', compact('course', 'module'));
    }

    public function update(Request $request, Course $course, Module $module)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'content' => 'nullable|string',
            'duration_minutes' => 'nullable|integer',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable',
        ]);
        $data['is_active'] = isset($data['is_active']);
        $module->update($data);

        return back()->with('success', 'Modulo aggiornato.');
    }

    public function destroy(Course $course, Module $module)
    {
        $module->delete();
        return redirect("/admin/courses/{$course->id}/modules")->with('success', 'Modulo eliminato.');
    }
}
