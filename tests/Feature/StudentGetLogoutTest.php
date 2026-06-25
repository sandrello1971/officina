<?php

namespace Tests\Feature;

use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /learn/logout (cortesia): esegue il logout e redirige al login, invece del
 * 405 di prima. Il logout via form resta POST.
 */
class StudentGetLogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_logout_disconnette_e_redirige_al_login(): void
    {
        $s = Student::create(['name' => 'D', 'email' => 'd' . uniqid() . '@e.it', 'password' => bcrypt('x'),
            'role' => 'student', 'is_active' => true, 'must_change_password' => false]);

        $this->withSession(['student_id' => $s->id, 'student_name' => $s->name, 'student_email' => $s->email])
            ->get('/learn/logout')
            ->assertRedirect(route('student.login'));

        $this->assertNull(session('student_id'));
    }
}
