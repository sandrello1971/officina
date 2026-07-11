@extends('layouts.admin')
@section('title', 'Nuovo Quiz')
@section('content')
<div style="max-width:600px;">
    <h2 style="font-size:1.25rem; font-weight:700; color:#1A1F1F; margin-bottom:20px;">Crea nuovo quiz</h2>
    <div style="background:white; border-radius:10px; padding:24px;">
        <form method="POST" action="/admin/quizzes">
            @csrf
            <div style="display:flex; flex-direction:column; gap:16px;">
                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Titolo *</label>
                    <input type="text" name="title" required value="{{ old('title') }}"
                           style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
                </div>
                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Descrizione</label>
                    <textarea name="description" rows="3"
                              style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">{{ old('description') }}</textarea>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                    <div>
                        <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Corso</label>
                        <select name="course_id" style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
                            <option value="">— Seleziona —</option>
                            @foreach($courses as $course)
                            <option value="{{ $course->id }}" {{ old('course_id') == $course->id ? 'selected' : '' }}>
                                {{ $course->icon }} {{ $course->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Soglia superamento %</label>
                        <input type="number" name="passing_score" value="{{ old('passing_score', 70) }}" min="0" max="100"
                               style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
                    </div>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                    <div>
                        <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Tempo limite (minuti, 0=nessuno)</label>
                        <input type="number" name="time_limit_minutes" value="{{ old('time_limit_minutes', 0) }}" min="0"
                               style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
                    </div>
                    <div>
                        <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Max tentativi (0=illimitati)</label>
                        <input type="number" name="max_attempts" value="{{ old('max_attempts', 0) }}" min="0"
                               style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
                    </div>
                </div>
                <div style="border:1px solid #C8D0D0; border-radius:8px; padding:16px; background:#F6F9F9;">
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer; margin-bottom:4px;">
                        <input type="checkbox" name="generate_with_ai" value="1" {{ old('generate_with_ai') ? 'checked' : '' }}>
                        <span style="font-size:0.875rem; font-weight:600; color:#3A8C89;">&#10022; Genera le domande con Claude AI dal contenuto del corso</span>
                    </label>
                    <div style="font-size:0.75rem; color:#8A9696; margin-bottom:12px;">Spunta per generare automaticamente le domande dai moduli del corso, scegliendo quante generarne qui sotto. Senza spunta il quiz viene creato vuoto e aggiungi le domande a mano.</div>
                    <div id="ai-opts" style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                        <div>
                            <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Numero di domande (pool)</label>
                            <select name="num_questions" style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
                                @foreach([5,10,15,20,30,40,50] as $n)
                                <option value="{{ $n }}" {{ (int) old('num_questions', 10) === $n ? 'selected' : '' }}>{{ $n }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Estrai per tentativo (vuoto = tutte)</label>
                            <input type="number" name="questions_per_attempt" value="{{ old('questions_per_attempt') }}" min="1" placeholder="tutte"
                                   style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
                        </div>
                    </div>
                </div>
                <div style="display:flex; gap:16px;">
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                        <input type="checkbox" name="randomize_questions" value="1" {{ old('randomize_questions') ? 'checked' : '' }}>
                        <span style="font-size:0.875rem; color:#4A5252;">Randomizza domande</span>
                    </label>
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                        <input type="checkbox" name="is_active" value="1" checked>
                        <span style="font-size:0.875rem; color:#4A5252;">Attivo</span>
                    </label>
                </div>
                <div style="display:flex; gap:12px; justify-content:flex-end;">
                    <a href="/admin/quizzes" style="padding:10px 20px; border:1px solid #C8D0D0; color:#4A5252; border-radius:8px; font-size:0.875rem; text-decoration:none;">Annulla</a>
                    <button type="submit" style="padding:10px 24px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.875rem; font-weight:700; cursor:pointer;">
                        Crea quiz
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
