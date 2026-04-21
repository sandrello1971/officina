<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\StudentNote;
use Illuminate\Http\Request;

class NoteController extends Controller
{
    public function save(Request $request, Module $module)
    {
        $request->validate(['content' => 'nullable|string|max:5000']);

        $student = \App\Models\Student::find(session('student_id'));
        if ($student && $student->is_demo) {
            return response()->json(['success' => true, 'demo' => true]);
        }

        StudentNote::updateOrCreate(
            ['student_id' => session('student_id'), 'module_id' => $module->id],
            ['content' => $request->content]
        );

        return response()->json(['success' => true]);
    }
}
