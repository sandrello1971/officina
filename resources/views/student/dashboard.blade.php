@extends('layouts.student')
@section('title', 'Dashboard')
@section('breadcrumb', 'Dashboard')

@section('content')
<div style="max-width:1100px; margin:0 auto;">

    <div style="margin-bottom:24px;">
        <h1 style="font-size:1.5rem; font-weight:700; color:#1A1F1F;">
            Benvenuto, {{ $student->name }} &#128075;
        </h1>
        <p style="color:#8A9696; font-size:0.875rem;">
            {{ now()->locale('it')->isoFormat('dddd D MMMM YYYY') }}
        </p>
    </div>

    {{-- Caso senza corsi né classi: messaggio dedicato, niente statistiche vuote. --}}
    @if($courses->isEmpty() && (empty($myClasses) || $myClasses->isEmpty()))
    <div style="background:white; border-radius:12px; padding:40px; text-align:center; border:2px dashed #C8D0D0;">
        <div style="font-size:3rem; margin-bottom:12px;">&#128218;</div>
        <h2 style="color:#1A1F1F; margin-bottom:8px;">Nessun corso attivo</h2>
        <p style="color:#8A9696; font-size:0.875rem;">
            Non hai ancora corsi assegnati.<br>
            Scrivi a <a href="mailto:{{ atheneum_setting('contact_email', 'rumore@effettoglitch.it') }}" style="color:#55B1AE;">{{ atheneum_setting('contact_email', 'rumore@effettoglitch.it') }}</a> per attivare il tuo percorso.
        </p>
    </div>
    @else

    {{-- Riga KPI: numeri in inchiostro, un solo accento (teal). --}}
    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); gap:16px; margin-bottom:22px;">
        <div style="background:white; border-radius:12px; padding:18px 20px; box-shadow:0 1px 4px rgba(0,0,0,0.06);">
            <div style="color:#8A9696; font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; margin-bottom:8px;">Corsi attivi</div>
            <div style="color:#1A1F1F; font-size:1.9rem; font-weight:800; line-height:1;">{{ $stats['courses'] }}</div>
        </div>
        <div style="background:white; border-radius:12px; padding:18px 20px; box-shadow:0 1px 4px rgba(0,0,0,0.06);">
            <div style="color:#8A9696; font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; margin-bottom:8px;">Moduli completati</div>
            <div style="color:#1A1F1F; font-size:1.9rem; font-weight:800; line-height:1;">{{ $stats['modules_completed'] }}</div>
        </div>
        <div style="background:white; border-radius:12px; padding:18px 20px; box-shadow:0 1px 4px rgba(0,0,0,0.06);">
            <div style="color:#8A9696; font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; margin-bottom:8px;">Quiz superati</div>
            <div style="color:#1A1F1F; font-size:1.9rem; font-weight:800; line-height:1;">{{ $stats['quizzes_passed'] }}</div>
        </div>
        <div style="background:white; border-radius:12px; padding:18px 20px; box-shadow:0 1px 4px rgba(0,0,0,0.06);">
            <div style="color:#8A9696; font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; margin-bottom:8px;">Progresso medio</div>
            <div style="display:flex; align-items:baseline; gap:4px;">
                <span style="color:#1A1F1F; font-size:1.9rem; font-weight:800; line-height:1;">{{ $stats['overall_progress'] }}</span>
                <span style="color:#8A9696; font-size:1rem; font-weight:700;">%</span>
            </div>
            <div class="progress-bar" style="margin-top:10px;"><div class="progress-fill" style="width:{{ $stats['overall_progress'] }}%"></div></div>
        </div>
    </div>

    {{-- Riprendi da dove eri --}}
    @if($lastModule)
    <div style="background:#E8F5F5; border:1px solid #55B1AE; border-radius:12px; padding:16px 20px; margin-bottom:22px; display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap;">
        <div>
            <div style="color:#3A8C89; font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; margin-bottom:2px;">Riprendi da dove eri</div>
            <div style="color:#1A1F1F; font-weight:700;">{{ $lastModule['title'] }}</div>
            <div style="color:#8A9696; font-size:0.8rem;">{{ $lastModule['course_name'] }}</div>
        </div>
        <a href="/learn/course/{{ $lastModule['course_slug'] }}/module/{{ $lastModule['module_id'] }}"
           style="padding:9px 18px; background:#55B1AE; color:white; border-radius:8px; font-size:0.85rem; font-weight:700; text-decoration:none; white-space:nowrap;">
            Riprendi &rarr;
        </a>
    </div>
    @endif

    {{-- Due colonne: anteprima corsi + classi/scorciatoie. --}}
    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(300px,1fr)); gap:20px; align-items:start;">

        {{-- Anteprima corsi (compatta, distinta dalla griglia di /learn/corsi). --}}
        @if($coursePreview->isNotEmpty())
        <div style="background:white; border-radius:12px; padding:20px; box-shadow:0 1px 4px rgba(0,0,0,0.06);">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:14px;">
                <h2 style="font-size:1rem; font-weight:700; color:#1A1F1F;">I tuoi corsi</h2>
                <a href="{{ route('student.courses.index') }}" style="font-size:0.8rem; color:#55B1AE; text-decoration:none; font-weight:600;">Vedi tutti &rarr;</a>
            </div>
            @foreach($coursePreview as $course)
            <a href="/learn/course/{{ $course->slug }}" style="display:block; text-decoration:none; padding:10px 0; border-top:1px solid #F0F2F2;">
                <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:6px;">
                    <span style="color:#1A1F1F; font-weight:600; font-size:0.9rem;">{{ $course->name }}</span>
                    @if(!empty($course->is_teaching))
                        <span style="color:#E28A53; font-size:0.68rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em;">insegni</span>
                    @else
                        <span style="color:#8A9696; font-size:0.8rem; font-weight:700;">{{ $course->progress_pct }}%</span>
                    @endif
                </div>
                @unless(!empty($course->is_teaching))
                <div class="progress-bar"><div class="progress-fill" style="width:{{ $course->progress_pct }}%"></div></div>
                @endunless
            </a>
            @endforeach
        </div>
        @endif

        {{-- Le tue classi (Schola) oppure scorciatoie. --}}
        <div style="background:white; border-radius:12px; padding:20px; box-shadow:0 1px 4px rgba(0,0,0,0.06);">
            @if(!empty($myClasses) && $myClasses->isNotEmpty())
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:14px;">
                <h2 style="font-size:1rem; font-weight:700; color:#1A1F1F;">Le mie classi</h2>
                <a href="{{ route('student.classes.index') }}" style="font-size:0.8rem; color:#55B1AE; text-decoration:none; font-weight:600;">Vedi tutte &rarr;</a>
            </div>
            @foreach($myClasses as $myClass)
            <div style="padding:10px 0; border-top:1px solid #F0F2F2; display:flex; align-items:center; gap:10px;">
                <div style="flex:1;">
                    <span style="font-weight:600; color:#1A1F1F; font-size:0.9rem;">{{ $myClass->name }}</span>
                    <span style="color:#8A9696; font-size:0.78rem;">· {{ $myClass->subject->name ?? '—' }} · {{ $myClass->school_year }}</span>
                </div>
                @if($myClass->pivot->status === 'pending')
                    <span style="font-size:0.68rem; font-weight:700; color:#E28A53; background:#FDECE2; border:1px solid #E28A53; border-radius:4px; padding:2px 8px;">In attesa</span>
                @else
                    <span style="font-size:0.68rem; font-weight:700; color:#3A8C89; background:#E8F5F5; border:1px solid #55B1AE; border-radius:4px; padding:2px 8px;">Attiva</span>
                @endif
            </div>
            @endforeach
            @else
            <h2 style="font-size:1rem; font-weight:700; color:#1A1F1F; margin-bottom:14px;">Scorciatoie</h2>
            <a href="{{ route('student.courses.index') }}" style="display:block; padding:10px 0; border-top:1px solid #F0F2F2; color:#4A5252; font-size:0.9rem; text-decoration:none;">&#128218; I miei corsi</a>
            <a href="{{ route('student.documents.index') }}" style="display:block; padding:10px 0; border-top:1px solid #F0F2F2; color:#4A5252; font-size:0.9rem; text-decoration:none;">&#128196; I miei documenti</a>
            <a href="{{ route('student.messages.index') }}" style="display:block; padding:10px 0; border-top:1px solid #F0F2F2; color:#4A5252; font-size:0.9rem; text-decoration:none;">&#9993; Messaggi</a>
            <a href="{{ route('student.announcements.index') }}" style="display:block; padding:10px 0; border-top:1px solid #F0F2F2; color:#4A5252; font-size:0.9rem; text-decoration:none;">&#128227; Annunci</a>
            @endif
        </div>

    </div>

    @endif

</div>
@endsection
