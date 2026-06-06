<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Student;

class StudentClassController extends Controller
{
    public function index()
    {
        $student = Student::findOrFail(session('student_id'));

        $classes = $student->schoolClasses()
            ->with('subject', 'teacher')
            ->wherePivot('status', '!=', 'removed')
            ->orderBy('name')
            ->get();

        return view('student.classi.index', compact('classes'));
    }
}
