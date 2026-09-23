@extends('layouts.admin')
@section('title', 'Genera corso con AI — ' . $course->name)
@section('content')

<div style="max-width:700px;">
    <div style="display:flex; align-items:center; gap:10px; margin-bottom:20px;">
        <a href="{{ route('admin.courses.show', $course) }}" style="color:#8A9696; text-decoration:none; font-size:0.85rem;">&larr; {{ $course->name }}</a>
    </div>

    <h2 style="font-size:1.25rem; font-weight:700; color:#1A1F1F; margin-bottom:6px;">&#10024; Genera moduli con AI</h2>
    <p style="color:#8A9696; font-size:0.85rem; margin:0 0 20px;">
        L'AI propone struttura, manuale discente, manuale formatore e slide per ogni modulo a partire dai
        materiali caricati sul corso. Prima di generare, indica le caratteristiche desiderate: l'AI non
        decide alla cieca. <strong>Nulla sarà visibile ai discenti finché non lo approvi</strong> nella
        revisione che segue.
    </p>

    @if (session('error'))
        <div style="background:#FBEDEC; border:1px solid #C0392B; color:#7B1E1E; padding:10px 14px; border-radius:8px; margin-bottom:16px; font-size:0.85rem;">
            {{ session('error') }}
        </div>
    @endif

    @if ($runningRun)
        <div style="background:#FFF8EE; border:1px solid rgba(226,138,83,0.45); color:#C26A2E; padding:12px 16px; border-radius:8px; margin-bottom:20px; font-size:0.85rem; display:flex; align-items:center; justify-content:space-between; gap:10px;">
            <span>Una generazione è già in corso per questo corso.</span>
            <a href="{{ route('admin.course-generation.show', $runningRun) }}" style="color:#C26A2E; font-weight:700; text-decoration:underline;">Vai alla revisione &rarr;</a>
        </div>
    @else

    <div style="background:white; border-radius:10px; padding:24px;">
        <form method="POST" action="{{ route('admin.course-generation.store', $course) }}">
            @csrf

            @if ($errors->any())
                <div style="background:#FDECE2; border:1px solid #E28A53; color:#A8521F; border-radius:8px; padding:14px 16px; margin-bottom:16px; font-size:0.85rem;">
                    <strong>Correggi questi errori:</strong>
                    <ul style="margin:8px 0 0 18px; padding:0;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div style="display:flex; flex-direction:column; gap:16px;">
                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252;">Pubblico/target e livello</label>
                    <input type="text" name="target" value="{{ old('target') }}" placeholder="es. professionisti sanitari, principianti"
                           style="width:100%; padding:10px; border:1px solid #E8F5F5; border-radius:8px; font-size:0.85rem; margin-top:4px;">
                </div>

                <div style="display:flex; gap:12px;">
                    <div style="flex:1;">
                        <label style="font-size:0.8rem; font-weight:600; color:#4A5252;">Livello</label>
                        <input type="text" name="level" value="{{ old('level') }}" placeholder="es. base, avanzato"
                               style="width:100%; padding:10px; border:1px solid #E8F5F5; border-radius:8px; font-size:0.85rem; margin-top:4px;">
                    </div>
                    <div style="flex:1;">
                        <label style="font-size:0.8rem; font-weight:600; color:#4A5252;">Durata indicativa (ore)</label>
                        <input type="number" name="duration_hours" value="{{ old('duration_hours') }}" min="1" max="1000"
                               style="width:100%; padding:10px; border:1px solid #E8F5F5; border-radius:8px; font-size:0.85rem; margin-top:4px;">
                    </div>
                    <div style="flex:1;">
                        <label style="font-size:0.8rem; font-weight:600; color:#4A5252;">N. moduli desiderato</label>
                        <input type="number" name="modules_count" value="{{ old('modules_count') }}" min="1" max="20" placeholder="lascia decidere all'AI"
                               style="width:100%; padding:10px; border:1px solid #E8F5F5; border-radius:8px; font-size:0.85rem; margin-top:4px;">
                    </div>
                </div>

                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252;">Obiettivi di apprendimento principali</label>
                    <textarea name="objectives" rows="3" placeholder="2-5 punti, uno per riga"
                              style="width:100%; padding:10px; border:1px solid #E8F5F5; border-radius:8px; font-size:0.85rem; margin-top:4px;">{{ old('objectives') }}</textarea>
                </div>

                <div style="display:flex; gap:12px;">
                    <div style="flex:1;">
                        <label style="font-size:0.8rem; font-weight:600; color:#4A5252;">Tono/registro</label>
                        <input type="text" name="tone" value="{{ old('tone') }}" placeholder="es. formale, colloquiale"
                               style="width:100%; padding:10px; border:1px solid #E8F5F5; border-radius:8px; font-size:0.85rem; margin-top:4px;">
                    </div>
                    <div style="flex:1;">
                        <label style="font-size:0.8rem; font-weight:600; color:#4A5252;">Lingua</label>
                        <input type="text" name="language" value="{{ old('language', 'italiano') }}"
                               style="width:100%; padding:10px; border:1px solid #E8F5F5; border-radius:8px; font-size:0.85rem; margin-top:4px;">
                    </div>
                </div>

                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252;">Vincoli espliciti</label>
                    <textarea name="constraints" rows="3" placeholder="argomenti da includere/escludere, ordine obbligato, normative da rispettare"
                              style="width:100%; padding:10px; border:1px solid #E8F5F5; border-radius:8px; font-size:0.85rem; margin-top:4px;">{{ old('constraints') }}</textarea>
                </div>
            </div>

            <div style="display:flex; gap:12px; justify-content:flex-end; margin-top:24px;">
                <a href="{{ route('admin.courses.show', $course) }}" style="padding:10px 20px; border:1px solid #C8D0D0; color:#4A5252; border-radius:8px; font-size:0.875rem; text-decoration:none;">Annulla</a>
                <button type="submit" data-guard-submit style="padding:10px 24px; background:#E28A53; color:white; border:none; border-radius:8px; font-size:0.875rem; font-weight:700; cursor:pointer;">
                    &#10024; Genera struttura corso
                </button>
            </div>
        </form>
    </div>
    @endif
</div>
@endsection
