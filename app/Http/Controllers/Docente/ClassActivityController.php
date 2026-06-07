<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Services\Schola\ClassSignalsService;

// Vista "Attività" della classe: copertura, punti critici dei quiz, attività
// per studente, inattivi >7gg. Tutte le aggregazioni vengono da
// ClassSignalsService (nessuna query inline qui).
class ClassActivityController extends Controller
{
    public function __construct(private ClassSignalsService $signals) {}

    public function index(SchoolClass $class)
    {
        abort_unless($class->teacher_id === session('student_id'), 403);

        return view('docente.classi.attivita', [
            'class' => $class,
            'coverage' => $this->signals->coverageByPublication($class),
            'painPoints' => $this->signals->quizPainPoints($class),
            'activity' => $this->signals->studentActivity($class),
            'inactive' => $this->signals->inactiveStudents($class, 7),
            'openQuestions' => $this->signals->openQuestionsCount($class),
        ]);
    }
}
