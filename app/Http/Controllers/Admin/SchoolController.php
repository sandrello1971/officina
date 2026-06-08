<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

// Platform admin: CRUD scuole (l'unico attore che attraversa le scuole, §2) +
// nomina del primo school_admin (segreteria). L'area /scuola arriva in P12.
class SchoolController extends Controller
{
    public function index()
    {
        $schools = School::withCount(['schoolAdmins', 'teachers', 'students', 'schoolClasses'])
            ->orderBy('name')
            ->get();

        return view('admin.scuole.index', compact('schools'));
    }

    public function create()
    {
        return view('admin.scuole.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|alpha_dash|unique:schools,slug',
            'type' => 'required|in:liceo,istituto_tecnico,altro',
            'city' => 'nullable|string|max:255',
            'allow_professor_create_classes' => 'sometimes|boolean',
        ]);

        $school = School::create([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['slug'] ?? Str::slug($data['name'])),
            'type' => $data['type'],
            'city' => $data['city'] ?? null,
            'allow_professor_create_classes' => (bool) ($data['allow_professor_create_classes'] ?? false),
            'status' => 'active',
        ]);

        return redirect()->route('admin.scuole.show', $school)
            ->with('success', 'Scuola creata. Ora nomina la segreteria.');
    }

    public function show(School $school)
    {
        $school->loadCount(['schoolAdmins', 'teachers', 'students', 'schoolClasses']);
        $admins = $school->schoolAdmins()->orderBy('name')->get();

        return view('admin.scuole.show', compact('school', 'admins'));
    }

    public function update(Request $request, School $school)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:liceo,istituto_tecnico,altro',
            'city' => 'nullable|string|max:255',
            'status' => 'required|in:active,suspended',
            'allow_professor_create_classes' => 'sometimes|boolean',
            'dpa_signed' => 'sometimes|boolean',
        ]);

        $school->update([
            'name' => $data['name'],
            'type' => $data['type'],
            'city' => $data['city'] ?? null,
            'status' => $data['status'],
            'allow_professor_create_classes' => (bool) ($data['allow_professor_create_classes'] ?? false),
            'dpa_signed_at' => $request->boolean('dpa_signed')
                ? ($school->dpa_signed_at ?? now())
                : null,
        ]);

        return redirect()->route('admin.scuole.show', $school)
            ->with('success', 'Scuola aggiornata.');
    }

    /**
     * Crea/nomina il PRIMO school_admin (segreteria) della scuola: account
     * con password temporanea (cambio obbligatorio al primo accesso).
     */
    public function nominateAdmin(Request $request, School $school)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('students', 'email')],
        ]);

        $tempPassword = 'Nsc2024!' . Str::upper(Str::random(4));

        Student::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $tempPassword,           // cast 'hashed' su Student
            'role' => 'school_admin',
            'school_id' => $school->id,
            'is_active' => true,
            'must_change_password' => true,
        ]);

        return redirect()->route('admin.scuole.show', $school)
            ->with('success', "Segreteria nominata: {$data['email']}")
            ->with('temp_password', $tempPassword);
    }

    private function uniqueSlug(string $base): string
    {
        $slug = Str::slug($base) ?: 'scuola';
        $candidate = $slug;
        $i = 1;
        while (School::where('slug', $candidate)->exists()) {
            $candidate = $slug . '-' . (++$i);
        }

        return $candidate;
    }
}
