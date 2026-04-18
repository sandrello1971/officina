<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\StudentWelcomeMail;
use App\Models\Course;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $query = Student::with('courses')->orderByDesc('created_at');

        if ($request->course_id) {
            $query->whereHas('courses', fn($q) => $q->where('courses.id', $request->course_id));
        }

        $students = $query->paginate(20);
        $courses = Course::orderBy('sort_order')->get();

        return view('admin.students.index', compact('students', 'courses'));
    }

    public function create()
    {
        $courses = Course::active()->orderBy('sort_order')->get();
        return view('admin.students.create', compact('courses'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:students,email',
            'phone' => 'nullable|string|max:50',
            'company' => 'nullable|string|max:255',
            'role' => 'nullable|string|max:100',
            'course_ids' => 'nullable|array',
            'course_ids.*' => 'uuid|exists:courses,id',
        ]);

        $tempPassword = 'Nsc2024!' . Str::upper(Str::random(4));

        $student = Student::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $tempPassword,
            'phone' => $data['phone'] ?? null,
            'company' => $data['company'] ?? null,
            'role' => $data['role'] ?? null,
            'is_active' => true,
            'must_change_password' => true,
        ]);

        $courseNames = [];
        if (!empty($data['course_ids'])) {
            $attach = [];
            foreach ($data['course_ids'] as $courseId) {
                $attach[$courseId] = ['enrolled_at' => now(), 'is_active' => true];
            }
            $student->courses()->attach($attach);
            $courseNames = Course::whereIn('id', $data['course_ids'])->pluck('name')->toArray();
        }

        try {
            Mail::to($student->email)->send(new StudentWelcomeMail($student, $tempPassword, $courseNames));
        } catch (\Throwable $e) {
            session()->flash('warning', 'Studente creato, ma invio email fallito: ' . $e->getMessage());
        }

        return redirect()->route('admin.students.show', $student)
            ->with('success', "Studente {$student->name} creato con successo.");
    }

    public function show(Student $student)
    {
        $student->load(['courses', 'moduleProgress.module.course', 'quizAttempts.quiz']);
        return view('admin.students.show', compact('student'));
    }

    public function edit(Student $student)
    {
        $courses = Course::orderBy('sort_order')->get();
        return view('admin.students.edit', compact('student', 'courses'));
    }

    public function update(Request $request, Student $student)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:students,email,' . $student->id,
            'phone' => 'nullable|string|max:50',
            'company' => 'nullable|string|max:255',
            'role' => 'nullable|string|max:100',
            'is_active' => 'sometimes|boolean',
        ]);

        $data['is_active'] = $request->boolean('is_active');

        $student->update($data);

        return redirect()->route('admin.students.show', $student)
            ->with('success', 'Studente aggiornato.');
    }

    public function destroy(Student $student)
    {
        $student->update(['is_active' => false]);
        return redirect()->route('admin.students.index')
            ->with('success', 'Studente disattivato.');
    }

    public function assignCourse(Request $request, Student $student)
    {
        $data = $request->validate([
            'course_id' => 'required|uuid|exists:courses,id',
            'expires_at' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $student->courses()->syncWithoutDetaching([
            $data['course_id'] => [
                'enrolled_at' => now(),
                'expires_at' => $data['expires_at'] ?? null,
                'is_active' => true,
                'notes' => $data['notes'] ?? null,
            ],
        ]);

        return back()->with('success', 'Corso assegnato.');
    }

    public function removeCourse(Student $student, Course $course)
    {
        $student->courses()->detach($course->id);
        return back()->with('success', 'Corso rimosso.');
    }

    public function sendCredentials(Student $student)
    {
        $tempPassword = 'Nsc2024!' . Str::upper(Str::random(4));
        $student->update([
            'password' => $tempPassword,
            'must_change_password' => true,
        ]);

        $courseNames = $student->courses()->pluck('courses.name')->toArray();

        try {
            Mail::to($student->email)->send(new StudentWelcomeMail($student, $tempPassword, $courseNames));
            return back()->with('success', 'Nuove credenziali inviate.');
        } catch (\Throwable $e) {
            return back()->withErrors(['email' => 'Invio fallito: ' . $e->getMessage()]);
        }
    }
}
