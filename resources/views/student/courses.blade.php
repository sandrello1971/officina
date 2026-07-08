@extends('layouts.student')
@section('title', 'I miei corsi')
@section('breadcrumb', 'I miei corsi')

@section('content')
<div style="max-width:1100px; margin:0 auto;">

    <div style="margin-bottom:24px;">
        <h1 style="font-size:1.5rem; font-weight:700; color:#1A1F1F;">I miei corsi</h1>
        <p style="color:#8A9696; font-size:0.875rem;">Tutti i corsi a cui hai accesso.</p>
    </div>

    @if($courses->isEmpty())
    <div style="background:white; border-radius:12px; padding:40px; text-align:center; border:2px dashed #C8D0D0;">
        <div style="font-size:3rem; margin-bottom:12px;">&#128218;</div>
        <h2 style="color:#1A1F1F; margin-bottom:8px;">Nessun corso attivo</h2>
        <p style="color:#8A9696; font-size:0.875rem;">
            Non hai ancora corsi assegnati.<br>
            Scrivi a <a href="mailto:{{ atheneum_setting('contact_email', 'rumore@effettoglitch.it') }}" style="color:#55B1AE;">{{ atheneum_setting('contact_email', 'rumore@effettoglitch.it') }}</a> per attivare il tuo percorso.
        </p>
    </div>
    @else
    {{-- Raggruppamento per categoria (tassonomia). Graceful degradation: con un
         unico gruppo "Altri corsi" non mostro intestazioni. --}}
    @php $showCategoryHeadings = !($coursesByCategory->count() === 1 && $coursesByCategory->keys()->first() === 'Altri corsi'); @endphp
    @foreach($coursesByCategory as $categoryName => $groupCourses)
    @if($showCategoryHeadings)
    <div style="margin:20px 0 10px; color:#4A5252; font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.12em;">{{ $categoryName }}</div>
    @endif
    <div style="display:grid; gap:16px; margin-bottom:8px;">
        @foreach($groupCourses as $course)
            @include('student.partials._course-card', ['course' => $course, 'student' => $student])
        @endforeach
    </div>
    @endforeach
    @endif

</div>
@endsection
