<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Models\ClassStudent;
use App\Models\SchoolClass;
use Illuminate\Http\Request;

class ClassRosterController extends Controller
{
    public function update(Request $request, SchoolClass $class, ClassStudent $enrollment)
    {
        // Difesa: solo classi proprie e iscrizione appartenente alla classe.
        abort_unless($class->teacher_id === session('student_id'), 403);
        abort_unless($enrollment->school_class_id === $class->id, 404);

        $data = $request->validate([
            'action' => 'required|in:approve,remove',
        ]);

        if ($data['action'] === 'approve') {
            $enrollment->update(['status' => 'active', 'approved_at' => now()]);
            $msg = 'Studente approvato.';
        } else {
            $enrollment->update(['status' => 'removed']);
            $msg = 'Studente rimosso dalla classe.';
        }

        return redirect()->route('docente.classes.show', $class)->with('success', $msg);
    }
}
