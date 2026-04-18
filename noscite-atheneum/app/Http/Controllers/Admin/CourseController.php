<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CourseController extends Controller
{
    public function index()
    {
        $courses = Course::withCount('modules')
            ->orderBy('sort_order')
            ->get();

        return view('admin.courses.index', compact('courses'));
    }

    public function create()
    {
        return view('admin.courses.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:courses,slug',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:20',
            'icon' => 'nullable|string|max:20',
            'duration_hours' => 'nullable|integer',
            'certification_name' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
        $data['is_active'] = $request->boolean('is_active');

        $course = Course::create($data);

        return redirect("/admin/courses/{$course->id}/edit")
            ->with('success', 'Corso creato. Aggiungi i moduli.');
    }

    public function show(string $id)
    {
        $course = Course::with('modules')->findOrFail($id);
        return view('admin.courses.show', compact('course'));
    }

    public function edit(string $id)
    {
        $course = Course::findOrFail($id);
        return view('admin.courses.edit', compact('course'));
    }

    public function update(Request $request, string $id)
    {
        $course = Course::findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:courses,slug,' . $course->id,
            'description' => 'nullable|string',
            'short_description' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:20',
            'icon' => 'nullable|string|max:20',
            'duration_hours' => 'nullable|integer',
            'certification_name' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $data['is_active'] = $request->boolean('is_active');

        $course->update($data);

        return redirect()->route('admin.courses.index')->with('success', 'Corso aggiornato.');
    }

    public function destroy(string $id)
    {
        Course::findOrFail($id)->delete();
        return redirect()->route('admin.courses.index')->with('success', 'Corso eliminato.');
    }

    public function generateQuiz(Request $request, Course $course)
    {
        $content = $course->modules()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('content')
            ->filter()
            ->join("\n\n");

        if (empty(trim($content))) {
            return back()->with('error', 'Nessun contenuto nei moduli. Aggiungi prima il testo dei moduli.');
        }

        $numQuestions = (int) $request->input('num_questions', 10);
        $generator = app(\App\Services\QuizGeneratorService::class);
        $quiz = $generator->generateFromContent($course, $content, $numQuestions);

        if (!$quiz) {
            return back()->with('error', 'Errore nella generazione del quiz. Riprova.');
        }

        return redirect("/admin/quizzes/{$quiz->id}/questions")
            ->with('success', "Quiz generato con {$quiz->questions()->count()} domande!");
    }
}
