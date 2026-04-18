<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Module;
use App\Models\Quiz;
use Illuminate\Http\Request;

class QuizController extends Controller
{
    public function index()
    {
        $quizzes = Quiz::with(['course', 'module'])
            ->withCount(['questions', 'attempts'])
            ->orderByDesc('created_at')
            ->get();

        return view('admin.quizzes.index', compact('quizzes'));
    }

    public function create()
    {
        $courses = Course::where('is_active', true)->orderBy('sort_order')->get();
        $modules = Module::with('course')->where('is_active', true)->get();
        return view('admin.quizzes.create', compact('courses', 'modules'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'course_id' => 'nullable|uuid',
            'module_id' => 'nullable|uuid',
            'passing_score' => 'required|integer|min:0|max:100',
            'time_limit_minutes' => 'nullable|integer|min:0',
            'max_attempts' => 'nullable|integer|min:0',
            'randomize_questions' => 'nullable',
            'is_active' => 'nullable',
        ]);

        $data['randomize_questions'] = isset($data['randomize_questions']);
        $data['is_active'] = isset($data['is_active']);
        $data['time_limit_minutes'] = $data['time_limit_minutes'] ?: null;
        $data['max_attempts'] = $data['max_attempts'] ?: null;
        $data['course_id'] = $data['course_id'] ?: null;
        $data['module_id'] = $data['module_id'] ?? null ?: null;

        $quiz = Quiz::create($data);

        return redirect("/admin/quizzes/{$quiz->id}/questions")
            ->with('success', 'Quiz creato. Aggiungi le domande.');
    }

    public function show(string $id)
    {
        $quiz = Quiz::with(['course', 'module', 'questions'])->findOrFail($id);
        return view('admin.quizzes.show', compact('quiz'));
    }

    public function edit(string $id)
    {
        $quiz = Quiz::findOrFail($id);
        $courses = Course::where('is_active', true)->orderBy('sort_order')->get();
        return view('admin.quizzes.edit', compact('quiz', 'courses'));
    }

    public function update(Request $request, string $id)
    {
        $quiz = Quiz::findOrFail($id);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'course_id' => 'nullable|uuid',
            'passing_score' => 'required|integer|min:0|max:100',
            'time_limit_minutes' => 'nullable|integer|min:0',
            'max_attempts' => 'nullable|integer|min:0',
            'randomize_questions' => 'nullable',
            'is_active' => 'nullable',
        ]);

        $data['randomize_questions'] = isset($data['randomize_questions']);
        $data['is_active'] = isset($data['is_active']);
        $data['time_limit_minutes'] = $data['time_limit_minutes'] ?: null;
        $data['max_attempts'] = $data['max_attempts'] ?: null;
        $data['course_id'] = $data['course_id'] ?: null;

        $quiz->update($data);

        return redirect()->route('admin.quizzes.index')->with('success', 'Quiz aggiornato.');
    }

    public function destroy(string $id)
    {
        Quiz::findOrFail($id)->delete();
        return redirect()->route('admin.quizzes.index')->with('success', 'Quiz eliminato.');
    }

    public function results(string $id)
    {
        $quiz = Quiz::with(['attempts.student', 'questions'])->findOrFail($id);
        return view('admin.quizzes.results', compact('quiz'));
    }
}
