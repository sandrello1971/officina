<?php

namespace App\Http\Controllers\Scuola;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Scuola\Concerns\ResolvesSchoolAccess;
use App\Models\Student;
use Illuminate\View\View;

// Elenco docenti della PROPRIA scuola (tenancy via ResolvesSchoolAccess).
class TeacherController extends Controller
{
    use ResolvesSchoolAccess;

    public function index(): View
    {
        $school = $this->currentSchool();

        $teachers = Student::where('school_id', $school->id)
            ->where('role', 'professor')
            ->with('teachableSubjects:id,name')
            ->orderBy('name')
            ->get();

        return view('scuola.docenti.index', compact('teachers'));
    }
}
