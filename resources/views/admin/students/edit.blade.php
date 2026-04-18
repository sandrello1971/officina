@extends('layouts.admin')
@section('title', 'Modifica studente — ' . $student->name)
@section('content')

<div style="max-width:700px;">
    <div style="margin-bottom:20px;">
        <a href="/admin/students/{{ $student->id }}" style="color:#8A9696; font-size:0.8rem; text-decoration:none;">&larr; Dettaglio studente</a>
        <h2 style="font-size:1.25rem; font-weight:700; color:#1A1F1F; margin-top:4px;">Modifica: {{ $student->name }}</h2>
    </div>

    @if(session('success'))
    <div style="background:#E8F5F5; border-left:4px solid #55B1AE; padding:12px 16px; border-radius:8px; margin-bottom:16px; color:#3A8C89; font-size:0.875rem;">
        {{ session('success') }}
    </div>
    @endif

    <div style="background:white; border-radius:10px; padding:24px; margin-bottom:16px;">
        <h3 style="font-size:1rem; font-weight:700; color:#1A1F1F; margin-bottom:16px;">Dati anagrafici</h3>
        <form method="POST" action="/admin/students/{{ $student->id }}">
            @csrf @method('PUT')
            <div style="display:grid; gap:16px;">
                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Nome completo *</label>
                    <input type="text" name="name" value="{{ old('name', $student->name) }}" required
                           style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; color:#1A1F1F; outline:none;">
                    @error('name')<p style="color:#E28A53; font-size:0.75rem; margin-top:4px;">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Email *</label>
                    <input type="email" name="email" value="{{ old('email', $student->email) }}" required
                           style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; color:#1A1F1F; outline:none;">
                    @error('email')<p style="color:#E28A53; font-size:0.75rem; margin-top:4px;">{{ $message }}</p>@enderror
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                    <div>
                        <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Telefono</label>
                        <input type="text" name="phone" value="{{ old('phone', $student->phone) }}"
                               style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
                    </div>
                    <div>
                        <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Azienda</label>
                        <input type="text" name="company" value="{{ old('company', $student->company) }}"
                               style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
                    </div>
                </div>

                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Ruolo</label>
                    <input type="text" name="role" value="{{ old('role', $student->role) }}"
                           style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
                </div>

                <label style="display:flex; align-items:center; gap:10px; cursor:pointer; padding:10px; background:#F5F7F7; border-radius:8px;">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $student->is_active) ? 'checked' : '' }}>
                    <div>
                        <div style="font-size:0.875rem; font-weight:600; color:#1A1F1F;">Studente attivo</div>
                        <div style="font-size:0.75rem; color:#8A9696;">Se disattivato, non potrà accedere alla piattaforma</div>
                    </div>
                </label>

                <div style="display:flex; gap:12px; justify-content:flex-end; margin-top:8px;">
                    <a href="/admin/students/{{ $student->id }}" style="padding:10px 20px; border:1px solid #C8D0D0; color:#4A5252; border-radius:8px; font-size:0.875rem; text-decoration:none;">Annulla</a>
                    <button type="submit" style="padding:10px 24px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.875rem; font-weight:700; cursor:pointer;">
                        Salva modifiche
                    </button>
                </div>
            </div>
        </form>
    </div>

    <div style="background:white; border-radius:10px; padding:24px; margin-bottom:16px;">
        <h3 style="font-size:1rem; font-weight:700; color:#1A1F1F; margin-bottom:16px;">Corsi assegnati</h3>

        @php $assignedIds = $student->courses->pluck('id')->toArray(); @endphp

        @if(count($assignedIds) > 0)
        <div style="display:flex; flex-direction:column; gap:8px; margin-bottom:16px;">
            @foreach($student->courses as $c)
            <div style="display:flex; align-items:center; justify-content:space-between; padding:10px 14px; background:#F5F7F7; border-radius:8px;">
                <div style="display:flex; align-items:center; gap:10px;">
                    <span>{{ $c->icon }}</span>
                    <div>
                        <div style="font-size:0.875rem; font-weight:600; color:#1A1F1F;">{{ $c->name }}</div>
                        <div style="font-size:0.75rem; color:#8A9696;">Iscritto il {{ $c->pivot->enrolled_at ? \Carbon\Carbon::parse($c->pivot->enrolled_at)->format('d/m/Y') : '—' }}</div>
                    </div>
                </div>
                <form method="POST" action="/admin/students/{{ $student->id }}/courses/{{ $c->id }}">
                    @csrf @method('DELETE')
                    <button type="submit" onclick="return confirm('Rimuovere {{ $c->name }}?')"
                            style="padding:6px 12px; background:#fff3ec; color:#E28A53; border:1px solid #E28A53; border-radius:6px; font-size:0.75rem; cursor:pointer;">
                        Rimuovi
                    </button>
                </form>
            </div>
            @endforeach
        </div>
        @else
        <p style="color:#8A9696; font-size:0.875rem; margin-bottom:16px;">Nessun corso assegnato.</p>
        @endif

        <form method="POST" action="/admin/students/{{ $student->id }}/courses">
            @csrf
            <div style="display:grid; grid-template-columns:1fr auto; gap:10px;">
                <select name="course_id" required
                        style="padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
                    <option value="">— Seleziona corso da assegnare —</option>
                    @foreach($courses as $course)
                        @if(!in_array($course->id, $assignedIds))
                        <option value="{{ $course->id }}">{{ $course->icon }} {{ $course->name }}</option>
                        @endif
                    @endforeach
                </select>
                <button type="submit" style="padding:10px 20px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.875rem; font-weight:700; cursor:pointer;">
                    Assegna
                </button>
            </div>
        </form>
    </div>

    <div style="background:white; border-radius:10px; padding:24px;">
        <h3 style="font-size:1rem; font-weight:700; color:#1A1F1F; margin-bottom:12px;">Credenziali</h3>
        <p style="color:#8A9696; font-size:0.875rem; margin-bottom:16px;">
            Invia nuove credenziali temporanee via email. La vecchia password sarà invalidata.
        </p>
        <form method="POST" action="/admin/students/{{ $student->id }}/send-credentials">
            @csrf
            <button type="submit" onclick="return confirm('Inviare nuove credenziali a {{ $student->email }}?')"
                    style="padding:10px 20px; background:#E28A53; color:white; border:none; border-radius:8px; font-size:0.875rem; font-weight:700; cursor:pointer;">
                &#128231; Invia nuove credenziali
            </button>
        </form>
    </div>
</div>

@endsection
