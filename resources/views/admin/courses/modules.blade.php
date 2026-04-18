@extends('layouts.admin')
@section('title', $course->name . ' — Moduli')
@section('content')

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
    <div>
        <a href="/admin/courses" style="color:#8A9696; font-size:0.8rem;">&larr; Corsi</a>
        <h2 style="font-size:1.25rem; font-weight:700; color:#1A1F1F; margin-top:4px;">
            {{ $course->icon }} {{ $course->name }} — Moduli
        </h2>
    </div>
    <a href="/admin/courses/{{ $course->id }}/modules/create"
       style="padding:8px 20px; background:#55B1AE; color:white; border-radius:8px; font-size:0.875rem; font-weight:600; text-decoration:none;">
        + Nuovo modulo
    </a>
</div>

<div style="display:flex; flex-direction:column; gap:8px;">
    @forelse($modules as $module)
    <div style="background:white; border-radius:10px; overflow:hidden;">
        <div style="padding:16px 20px; display:flex; align-items:center; justify-content:space-between;">
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="width:32px; height:32px; border-radius:50%; background:#E8F5F5; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:0.875rem; color:#55B1AE;">
                    {{ $module->sort_order }}
                </div>
                <div>
                    <div style="font-weight:600; color:#1A1F1F;">{{ $module->title }}</div>
                    <div style="font-size:0.75rem; color:#8A9696;">
                        {{ $module->duration_minutes ? $module->duration_minutes.' min' : '' }}
                        &middot; {{ $module->content ? 'Contenuto presente' : '&#9888; Nessun contenuto' }}
                        &middot; {{ $module->materials->count() }} materiali
                    </div>
                </div>
            </div>
            <div style="display:flex; gap:8px;">
                <a href="/admin/courses/{{ $course->id }}/modules/{{ $module->id }}/edit"
                   style="padding:6px 14px; background:#55B1AE; color:white; border-radius:6px; font-size:0.8rem; font-weight:600; text-decoration:none;">
                    Modifica
                </a>
                <a href="/admin/courses/{{ $course->id }}/modules/{{ $module->id }}/edit#content"
                   style="padding:6px 14px; background:#E8F5F5; color:#3A8C89; border-radius:6px; font-size:0.8rem; font-weight:600; text-decoration:none;">
                    Contenuto
                </a>
            </div>
        </div>
    </div>
    @empty
    <div style="background:white; border-radius:10px; padding:32px; text-align:center; color:#8A9696;">
        Nessun modulo. <a href="/admin/courses/{{ $course->id }}/modules/create" style="color:#55B1AE;">Crea il primo &rarr;</a>
    </div>
    @endforelse
</div>

<div style="background:linear-gradient(135deg,#1A1F1F,#3A8C89); border-radius:10px; padding:20px; margin-top:16px; display:flex; align-items:center; justify-content:space-between;">
    <div>
        <div style="color:#55B1AE; font-weight:700;">&#10022; Genera quiz con Claude AI</div>
        <div style="color:#8A9696; font-size:0.8rem;">Crea automaticamente domande basate sul contenuto dei moduli</div>
    </div>
    <form method="POST" action="/admin/courses/{{ $course->id }}/generate-quiz">
        @csrf
        <div style="display:flex; align-items:center; gap:10px;">
            <select name="num_questions" style="padding:6px 10px; border-radius:6px; border:none; font-size:0.8rem;">
                <option value="5">5 domande</option>
                <option value="10" selected>10 domande</option>
                <option value="15">15 domande</option>
            </select>
            <button type="submit" style="padding:8px 20px; background:#E28A53; color:white; border:none; border-radius:6px; font-size:0.875rem; font-weight:700; cursor:pointer;">
                Genera quiz &rarr;
            </button>
        </div>
    </form>
</div>
@endsection
