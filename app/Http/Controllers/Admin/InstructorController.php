<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InstructorController extends Controller
{
    public function index(Request $request)
    {
        $query = Student::query()
            ->where('role', 'instructor')
            ->with('taughtCourses:id,name,icon')
            ->withCount('taughtCourses')
            ->orderBy('name');

        if ($request->course_id) {
            $query->whereHas('taughtCourses', fn($q) => $q->where('courses.id', $request->course_id));
        }

        $instructors = $query->paginate(20)->withQueryString();
        $courses = Course::orderBy('sort_order')->get(['id', 'name', 'icon']);

        $mentoredCounts = DB::table('student_course')
            ->select('instructor_id', DB::raw('COUNT(DISTINCT student_id) AS n'))
            ->whereNotNull('instructor_id')
            ->groupBy('instructor_id')
            ->pluck('n', 'instructor_id');

        return view('admin.instructors.index', compact('instructors', 'courses', 'mentoredCounts'));
    }

    public function show(Student $instructor)
    {
        abort_unless($instructor->role === 'instructor', 404);

        $instructor->load('taughtCourses:id,name,icon,short_description');

        $mentoredByCourse = DB::table('student_course')
            ->join('students', 'students.id', '=', 'student_course.student_id')
            ->join('courses', 'courses.id', '=', 'student_course.course_id')
            ->where('student_course.instructor_id', $instructor->id)
            ->select(
                'student_course.course_id',
                'courses.name as course_name',
                'students.id as student_id',
                'students.name as student_name',
                'students.email as student_email'
            )
            ->orderBy('courses.name')
            ->orderBy('students.name')
            ->get()
            ->groupBy('course_id');

        $availableCourses = Course::orderBy('sort_order')
            ->whereNotIn('id', $instructor->taughtCourses->pluck('id'))
            ->get(['id', 'name', 'icon']);

        return view('admin.instructors.show', compact(
            'instructor', 'mentoredByCourse', 'availableCourses'
        ));
    }

    public function edit(Student $instructor)
    {
        abort_unless($instructor->role === 'instructor', 404);
        return view('admin.instructors.edit', compact('instructor'));
    }

    public function update(Request $request, Student $instructor)
    {
        abort_unless($instructor->role === 'instructor', 404);

        $data = $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => 'required|email|unique:students,email,' . $instructor->id,
            'phone'     => 'nullable|string|max:50',
            'company'   => 'nullable|string|max:255',
            'job_title' => 'nullable|string|max:100',
            'is_active' => 'sometimes|boolean',
        ]);

        $data['is_active'] = $request->boolean('is_active');

        $instructor->update($data);

        return redirect()->route('admin.instructors.show', $instructor)
            ->with('success', 'Formatore aggiornato.');
    }

    public function attachCourse(Request $request, Student $instructor)
    {
        abort_unless($instructor->role === 'instructor', 403, 'Solo gli utenti con ruolo formatore possono insegnare corsi');

        $data = $request->validate([
            'course_id' => 'required|uuid|exists:courses,id',
        ]);

        $instructor->taughtCourses()->syncWithoutDetaching([$data['course_id']]);

        Log::info('Admin attached instructor to course', [
            'admin'      => session('admin_email') ?? 'unknown',
            'instructor' => $instructor->email,
            'course_id'  => $data['course_id'],
        ]);

        return back()->with('success', 'Corso associato al formatore.');
    }

    public function detachCourse(Student $instructor, Course $course)
    {
        abort_unless($instructor->role === 'instructor', 404);

        DB::transaction(function () use ($instructor, $course) {
            $instructor->taughtCourses()->detach($course->id);

            $orphaned = DB::table('student_course')
                ->where('course_id', $course->id)
                ->where('instructor_id', $instructor->id)
                ->update(['instructor_id' => null]);

            Log::info('Admin detached instructor from course; orphaned student assignments cleared', [
                'admin'                  => session('admin_email') ?? 'unknown',
                'instructor'             => $instructor->email,
                'course_id'              => $course->id,
                'orphaned_enrollments'   => $orphaned,
            ]);
        });

        return back()->with('success', 'Corso rimosso dal formatore. Le iscrizioni dei discenti che lo puntavano sono ora senza formatore.');
    }

    public function forCourse(Course $course)
    {
        $list = $course->instructors()
            ->orderBy('students.name')
            ->get(['students.id', 'students.name', 'students.company'])
            ->map(fn($i) => [
                'id'    => $i->id,
                'label' => $i->company ? "{$i->name} ({$i->company})" : $i->name,
            ]);

        return response()->json($list);
    }
}
