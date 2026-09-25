<?php

namespace Tests\Feature\Attendance;

use App\Models\Admin;
use App\Models\AttendanceRecord;
use App\Models\Course;
use App\Models\CourseEdition;
use App\Models\Student;
use App\Services\AttendanceRegisterPdfBuilder;
use App\Services\AttendanceService;
use App\Services\CourseEditionService;
use App\Services\EditionRegisterCsv;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Edizioni di corso, pianificazione giornate e registro presenze giornaliero. */
class CourseEditionRegisterTest extends TestCase
{
    use RefreshDatabase;

    private function course(): Course
    {
        return Course::create(['name' => 'Claude Code', 'slug' => 'cc-' . uniqid(), 'is_active' => true, 'sort_order' => 1]);
    }

    private function student(string $name, string $role = 'student'): Student
    {
        return Student::create(['name' => $name, 'email' => strtolower($name) . uniqid() . '@e.it', 'password' => bcrypt('x'),
            'role' => $role, 'is_active' => true, 'must_change_password' => false]);
    }

    /** Edizione di 5 giornate × 4h dal lunedì 5/10/2026, alle 9:00. */
    private function edition(?Course $course = null): CourseEdition
    {
        $edition = CourseEdition::create(['course_id' => ($course ?? $this->course())->id, 'name' => 'Ottobre', 'modality' => 'in_person']);
        app(CourseEditionService::class)->planDays($edition, '2026-10-05', 5, '09:00', 4);

        return $edition;
    }

    public function test_pianificazione_salta_il_weekend_e_numera_le_giornate(): void
    {
        $edition = CourseEdition::create(['course_id' => $this->course()->id, 'name' => 'X', 'modality' => 'in_person']);
        // Da venerdì 9/10/2026: ven, lun, mar.
        $created = app(CourseEditionService::class)->planDays($edition, '2026-10-09', 3, '14:00', 3.5);

        $days = $edition->days()->get();
        $this->assertSame(3, $created);
        $this->assertSame(['2026-10-09 14:00', '2026-10-12 14:00', '2026-10-13 14:00'], $days->map(fn ($d) => $d->scheduled_at->format('Y-m-d H:i'))->all());
        $this->assertSame([1, 2, 3], $days->pluck('day_number')->all());
        $this->assertSame(210, $days->first()->duration_minutes);
        $this->assertSame('Giornata 2', $days[1]->title);
    }

    public function test_un_discente_sta_in_una_sola_edizione_per_corso_e_viene_iscritto(): void
    {
        $course = $this->course();
        $a = $this->edition($course);
        $b = CourseEdition::create(['course_id' => $course->id, 'name' => 'Novembre', 'modality' => 'in_person']);
        $anna = $this->student('Anna');
        $svc = app(CourseEditionService::class);

        $this->assertSame(['added' => 1, 'skipped' => []], $svc->addStudents($a, [$anna->id]));
        $this->assertTrue($course->students()->where('students.id', $anna->id)->exists());

        $this->assertSame(['added' => 0, 'skipped' => [$anna->id]], $svc->addStudents($b, [$anna->id]));
        $this->assertFalse($b->students()->where('students.id', $anna->id)->exists());
    }

    public function test_ore_con_ritardo_uscita_anticipata_e_correzione_manuale(): void
    {
        $edition = $this->edition();
        [$anna, $bruno, $carla, $dario] = [$this->student('Anna'), $this->student('Bruno'), $this->student('Carla'), $this->student('Dario')];
        app(CourseEditionService::class)->addStudents($edition, [$anna->id, $bruno->id, $carla->id, $dario->id]);
        $day = $edition->days()->first(); // 09:00–13:00

        $present = app(AttendanceService::class)->markSessionAttendance($day, [
            $anna->id => ['status' => 'presente', 'arrived_at' => '09:30', 'left_at' => '12:00'], // 2,5h
            $bruno->id => ['status' => 'presente', 'arrived_at' => '08:40'],                       // prima dell'inizio: 4h
            $carla->id => ['status' => 'presente', 'hours' => '3'],                                // correzione: 3h
            $dario->id => ['status' => 'assente_giustificato', 'arrived_at' => '10:00', 'note' => 'certificato medico'],
        ], 'doc@e.it');

        $hours = fn (Student $s) => (float) AttendanceRecord::where('student_id', $s->id)->where('course_session_id', $day->id)->value('hours_credited');
        $this->assertSame(3, $present);
        $this->assertEquals(2.5, $hours($anna));
        $this->assertEquals(4.0, $hours($bruno));
        $this->assertEquals(3.0, $hours($carla));
        $this->assertEquals(0.0, $hours($dario));
        $dr = AttendanceRecord::where('student_id', $dario->id)->first();
        $this->assertNull($dr->arrived_at); // gli orari valgono solo per i presenti
        $this->assertSame('certificato medico', $dr->note);
        $this->assertSame('doc@e.it', $dr->marked_by);
    }

    public function test_un_solo_appello_per_discente_e_giornata(): void
    {
        $edition = $this->edition();
        $anna = $this->student('Anna');
        app(CourseEditionService::class)->addStudents($edition, [$anna->id]);
        $day = $edition->days()->first();
        app(AttendanceService::class)->markSessionAttendance($day, [$anna->id => ['status' => 'presente']]);

        $this->expectException(QueryException::class);
        AttendanceRecord::create(['student_id' => $anna->id, 'course_id' => $edition->course_id, 'course_session_id' => $day->id,
            'type' => 'sync_session', 'source' => 'instructor_mark', 'status' => 'assente', 'occurred_at' => now()]);
    }

    public function test_registro_griglia_percentuali_csv_e_pdf(): void
    {
        $edition = $this->edition();
        [$anna, $bruno] = [$this->student('Anna'), $this->student('Bruno')];
        app(CourseEditionService::class)->addStudents($edition, [$anna->id, $bruno->id]);
        $svc = app(AttendanceService::class);
        $days = $edition->days()->get();

        foreach ($days as $i => $day) {
            $svc->markSessionAttendance($day, [
                $anna->id => ['status' => 'presente'],
                $bruno->id => $i === 0 ? ['status' => 'assente'] : ['status' => 'presente', 'arrived_at' => $i === 1 ? '09:20' : null],
            ]);
        }

        $register = $svc->editionRegister($edition);
        $annaRow = $register['rows']->firstWhere('student.id', $anna->id);
        $brunoRow = $register['rows']->firstWhere('student.id', $bruno->id);

        $this->assertEquals(20.0, $register['planned_hours']);
        $this->assertEquals(100.0, $annaRow['percent']);
        $this->assertEqualsWithDelta(4 * 4 - 20 / 60, $brunoRow['hours'], 0.01); // 4 giornate, 20 minuti di ritardo
        $this->assertSame(1, $brunoRow['totals']['absent']);
        $this->assertSame(1, $brunoRow['totals']['late']);
        $this->assertSame(1, $register['present_per_day'][$days[0]->id]);

        $csv = app(EditionRegisterCsv::class)->build($edition, $register);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Discente;Email;"G1 05/10/2026"', $csv);
        $this->assertStringContainsString(';A;"R 09:20";P;P;P;', $csv);

        $pdf = app(AttendanceRegisterPdfBuilder::class)->buildEditionRegister($edition->load('course'), $register);
        $this->assertStringStartsWith('%PDF', $pdf);
    }

    public function test_admin_gestisce_e_formatore_di_altro_corso_riceve_403(): void
    {
        $edition = $this->edition();
        $course = $edition->course;
        Admin::create(['name' => 'A', 'email' => 'adm@e.it', 'password' => 'x', 'is_active' => true]);
        $admin = ['admin_logged_in' => true, 'admin_email' => 'adm@e.it'];

        $this->withSession($admin)->get(route('admin.courses.editions.show', [$course, $edition]))->assertOk()->assertSee('Ottobre');
        $this->withSession($admin)->get(route('admin.courses.editions.day', [$course, $edition, $edition->days()->first()]))->assertOk();
        $this->withSession($admin)->get(route('admin.courses.editions.csv', [$course, $edition]))->assertOk();

        $altro = $this->student('Altro', 'instructor');
        $altro->taughtCourses()->attach($this->course()->id);
        $this->withSession(['student_id' => $altro->id])
            ->get(route('student.course.editions.show', [$course->slug, $edition]))->assertForbidden();
    }

    public function test_giornata_di_un_altra_edizione_da_404(): void
    {
        $course = $this->course();
        $a = $this->edition($course);
        $b = $this->edition($course);
        Admin::create(['name' => 'A', 'email' => 'adm@e.it', 'password' => 'x', 'is_active' => true]);

        $this->withSession(['admin_logged_in' => true, 'admin_email' => 'adm@e.it'])
            ->get(route('admin.courses.editions.day', [$course, $a, $b->days()->first()]))->assertNotFound();
    }

    public function test_togliere_un_discente_cancella_i_suoi_appelli_e_lascia_l_iscrizione(): void
    {
        $edition = $this->edition();
        $anna = $this->student('Anna');
        $svc = app(CourseEditionService::class);
        $svc->addStudents($edition, [$anna->id]);
        app(AttendanceService::class)->markSessionAttendance($edition->days()->first(), [$anna->id => ['status' => 'presente']]);

        $svc->removeStudent($edition, $anna->id);

        $this->assertSame(0, AttendanceRecord::where('student_id', $anna->id)->count());
        $this->assertTrue($edition->course->students()->where('students.id', $anna->id)->exists());
    }
}
