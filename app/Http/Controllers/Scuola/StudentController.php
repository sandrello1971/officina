<?php

namespace App\Http\Controllers\Scuola;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Scuola\Concerns\ResolvesSchoolAccess;
use App\Models\Student;
use Illuminate\View\View;

// Elenco studenti della PROPRIA scuola (tenancy via ResolvesSchoolAccess).
class StudentController extends Controller
{
    use ResolvesSchoolAccess;

    public function index(): View
    {
        $school = $this->currentSchool();

        $students = Student::where('school_id', $school->id)
            ->where('role', 'student')
            ->with(['classEnrollments' => fn ($q) => $q->where('status', 'active')->with('schoolClass:id,name')])
            ->orderBy('name')
            ->get();

        return view('scuola.studenti.index', compact('students'));
    }
}
