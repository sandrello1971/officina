<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Course;
use App\Models\CourseEdition;
use App\Models\CourseSession;
use App\Models\Student;
use App\Services\AttendanceRegisterPdfBuilder;
use App\Services\AttendanceService;
use App\Services\CourseEditionService;
use App\Services\EditionRegisterCsv;
use App\Support\EditionRoutes;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Edizioni di un corso e registro presenze giornaliero. Logica unica per
 * l'area admin e per il formatore: le sottoclassi dicono solo chi può
 * accedere, con quale layout e con quali rotte.
 */
abstract class EditionController extends Controller
{
    public function __construct(
        protected CourseEditionService $editions,
        protected AttendanceService $attendance,
    ) {}

    /** Blocca chi non può gestire le edizioni del corso. */
    abstract protected function authorizeCourse(Course $course): void;

    abstract protected function layout(): string;

    abstract protected function routes(Course $course): EditionRoutes;

    /** Chi sta compilando (per created_by / marked_by). */
    abstract protected function actor(): ?string;

    public function index(Course $course)
    {
        $this->authorizeCourse($course);
        $editions = $course->editions()->withCount(['days', 'students'])
            ->with(['days' => fn ($q) => $q->select('id', 'course_edition_id', 'scheduled_at', 'duration_minutes'), 'instructor'])
            ->get();

        return $this->view('index', $course, compact('editions'));
    }

    public function create(Course $course)
    {
        $this->authorizeCourse($course);

        return $this->view('create', $course, ['instructors' => $this->instructorOptions()]);
    }

    public function store(Request $request, Course $course)
    {
        $this->authorizeCourse($course);
        $data = $request->validate($this->editionRules($course) + $this->planRules());

        $edition = CourseEdition::create(['course_id' => $course->id] + collect($data)->only(['name', 'location', 'modality', 'instructor_id', 'notes'])->all());
        $created = $this->plan($edition, $data);

        return redirect($this->routes($course)->url('show', $edition))
            ->with('success', "Edizione creata con {$created} giornate. Ora associa i discenti.");
    }

    public function show(Request $request, Course $course, CourseEdition $edition)
    {
        $this->authorizeEdition($course, $edition);
        $edition->load(['instructor', 'students']);
        $register = $this->attendance->editionRegister($edition);

        $marked = AttendanceRecord::whereIn('course_session_id', $register['days']->pluck('id'))
            ->where('source', 'instructor_mark')
            ->selectRaw('course_session_id, count(*) as n')
            ->groupBy('course_session_id')
            ->pluck('n', 'course_session_id');

        // Candidati: discenti dell'ente non già in un'altra edizione di questo corso.
        $inOtherEditions = DB::table('course_edition_students')->where('course_id', $course->id)->pluck('student_id');
        $candidates = Student::query()
            ->whereNotIn('id', $inOtherEditions)
            ->where('is_active', true)
            ->where('role', 'student')
            ->where('is_instructor', false)
            ->where('is_demo', false)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'is_instructor']);
        $enrolledIds = $course->students()->pluck('students.id')->all();

        return $this->view('show', $course, [
            'edition' => $edition,
            'register' => $register,
            'marked' => $marked,
            'candidates' => $candidates,
            'enrolledIds' => $enrolledIds,
            'instructors' => $this->instructorOptions(),
            'tab' => in_array($request->query('tab'), ['giornate', 'discenti', 'registro'], true) ? $request->query('tab') : 'registro',
        ]);
    }

    public function update(Request $request, Course $course, CourseEdition $edition)
    {
        $this->authorizeEdition($course, $edition);
        $edition->update($request->validate($this->editionRules($course)));

        return back()->with('success', 'Edizione aggiornata.');
    }

    public function destroy(Request $request, Course $course, CourseEdition $edition)
    {
        $this->authorizeEdition($course, $edition);
        $request->validate(['confirm' => ['required', Rule::in([$edition->name])]], [
            'confirm.in' => "Scrivi esattamente il nome dell'edizione per confermare.",
        ]);

        DB::transaction(function () use ($edition) {
            AttendanceRecord::whereIn('course_session_id', $edition->days()->pluck('id'))->delete();
            $edition->delete(); // giornate e discenti dell'edizione in cascade
        });

        return redirect($this->routes($course)->url('index'))->with('success', 'Edizione eliminata.');
    }

    public function addDays(Request $request, Course $course, CourseEdition $edition)
    {
        $this->authorizeEdition($course, $edition);
        $created = $this->plan($edition, $request->validate($this->planRules()));
        $this->editions->renumberDays($edition);

        return redirect($this->routes($course)->url('show', $edition) . '?tab=giornate')
            ->with('success', "Aggiunte {$created} giornate.");
    }

    public function updateDay(Request $request, Course $course, CourseEdition $edition, CourseSession $day)
    {
        $this->authorizeDay($course, $edition, $day);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'hours' => ['required', 'numeric', 'min:0.25', 'max:24'],
        ]);

        $day->update([
            'title' => $data['title'],
            'scheduled_at' => Carbon::parse($data['date'])->setTimeFromTimeString($data['start_time']),
            'duration_minutes' => (int) round($data['hours'] * 60),
        ]);
        $this->editions->renumberDays($edition);
        $this->recomputeDay($day->fresh());

        return redirect($this->routes($course)->url('show', $edition) . '?tab=giornate')->with('success', 'Giornata aggiornata.');
    }

    public function destroyDay(Course $course, CourseEdition $edition, CourseSession $day)
    {
        $this->authorizeDay($course, $edition, $day);
        $day->attendanceRecords()->delete();
        $day->delete();
        $this->editions->renumberDays($edition);

        return redirect($this->routes($course)->url('show', $edition) . '?tab=giornate')->with('success', 'Giornata eliminata.');
    }

    public function addStudents(Request $request, Course $course, CourseEdition $edition)
    {
        $this->authorizeEdition($course, $edition);
        $data = $request->validate(['student_ids' => ['required', 'array', 'min:1'], 'student_ids.*' => ['uuid', 'exists:students,id']]);

        $result = $this->editions->addStudents($edition, $data['student_ids']);
        $msg = "Aggiunti {$result['added']} discenti.";
        if ($result['skipped']) {
            $msg .= ' ' . count($result['skipped']) . " già presenti in un'altra edizione di questo corso: non aggiunti.";
        }

        return redirect($this->routes($course)->url('show', $edition) . '?tab=discenti')->with('success', $msg);
    }

    public function removeStudent(Course $course, CourseEdition $edition, Student $student)
    {
        $this->authorizeEdition($course, $edition);
        $this->editions->removeStudent($edition, $student->id);

        return redirect($this->routes($course)->url('show', $edition) . '?tab=discenti')
            ->with('success', "{$student->name} tolto dall'edizione (resta iscritto al corso).");
    }

    /** Appello della giornata. */
    public function day(Course $course, CourseEdition $edition, CourseSession $day)
    {
        $this->authorizeDay($course, $edition, $day);
        $students = $edition->students()->get();
        $records = $day->attendanceRecords()->where('source', 'instructor_mark')->get()->keyBy('student_id');

        return $this->view('day', $course, compact('edition', 'day', 'students', 'records'));
    }

    public function mark(Request $request, Course $course, CourseEdition $edition, CourseSession $day)
    {
        $this->authorizeDay($course, $edition, $day);
        $request->validate([
            'marks' => ['array'],
            'marks.*.status' => ['nullable', Rule::in(array_keys(AttendanceRecord::STATUSES))],
            'marks.*.arrived_at' => ['nullable', 'date_format:H:i'],
            'marks.*.left_at' => ['nullable', 'date_format:H:i'],
            'marks.*.note' => ['nullable', 'string', 'max:500'],
            'marks.*.hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
        ]);

        $present = $this->attendance->markSessionAttendance($day, (array) $request->input('marks', []), $this->actor());

        return redirect($this->routes($course)->url('day', $edition, $day))
            ->with('success', "Appello salvato: {$present} presenti.");
    }

    public function pdf(Course $course, CourseEdition $edition, AttendanceRegisterPdfBuilder $builder)
    {
        $this->authorizeEdition($course, $edition);
        $bytes = $builder->buildEditionRegister($edition->load('course', 'instructor'), $this->attendance->editionRegister($edition));

        return response()->streamDownload(fn () => print($bytes), $this->filename($course, $edition, 'pdf'), ['Content-Type' => 'application/pdf']);
    }

    public function csv(Course $course, CourseEdition $edition, EditionRegisterCsv $csv)
    {
        $this->authorizeEdition($course, $edition);
        $content = $csv->build($edition, $this->attendance->editionRegister($edition));

        return response()->streamDownload(fn () => print($content), $this->filename($course, $edition, 'csv'), ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ---------------------------------------------------------------------

    protected function authorizeEdition(Course $course, CourseEdition $edition): void
    {
        $this->authorizeCourse($course);
        abort_unless($edition->course_id === $course->id, 404);
    }

    protected function authorizeDay(Course $course, CourseEdition $edition, CourseSession $day): void
    {
        $this->authorizeEdition($course, $edition);
        abort_unless($day->course_edition_id === $edition->id, 404);
    }

    protected function view(string $name, Course $course, array $data = [])
    {
        return view("attendance.editions.{$name}", $data + [
            'course' => $course,
            'layout' => $this->layout(),
            'nav' => $this->routes($course),
        ]);
    }

    private function plan(CourseEdition $edition, array $data): int
    {
        return $this->editions->planDays(
            $edition,
            $data['first_date'],
            (int) $data['days'],
            $data['start_time'],
            (float) $data['hours_per_day'],
            $data['weekdays'] ?? [1, 2, 3, 4, 5],
            $this->actor(),
        );
    }

    /** Dopo il cambio di orario/durata di una giornata le ore degli appelli vanno ricalcolate. */
    private function recomputeDay(CourseSession $day): void
    {
        foreach ($day->attendanceRecords()->where('source', 'instructor_mark')->where('status', AttendanceRecord::STATUS_PRESENT)->get() as $record) {
            $record->update(['hours_credited' => $this->attendance->sessionHours($day, $record->arrived_at ? substr($record->arrived_at, 0, 5) : null, $record->left_at ? substr($record->left_at, 0, 5) : null)]);
        }
    }

    private function editionRules(Course $course): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'location' => ['nullable', 'string', 'max:255'],
            'modality' => ['required', Rule::in(array_keys(CourseEdition::MODALITIES))],
            'instructor_id' => ['nullable', Rule::in($this->instructorOptions()->pluck('id')->all())],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Formatori selezionabili come responsabili: tutti i formatori attivi
     * dell'ente (in Officina i formatori di piattaforma non sono legati ai
     * singoli corsi).
     */
    private function instructorOptions()
    {
        return Student::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('role', 'instructor')->orWhere('is_instructor', true))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    private function planRules(): array
    {
        return [
            'first_date' => ['required', 'date'],
            'days' => ['required', 'integer', 'min:1', 'max:120'],
            'start_time' => ['required', 'date_format:H:i'],
            'hours_per_day' => ['required', 'numeric', 'min:0.25', 'max:24'],
            'weekdays' => ['nullable', 'array'],
            'weekdays.*' => ['integer', 'between:1,7'],
        ];
    }

    private function filename(Course $course, CourseEdition $edition, string $ext): string
    {
        return 'registro-' . Str::slug($course->name . ' ' . $edition->name) . '.' . $ext;
    }
}
