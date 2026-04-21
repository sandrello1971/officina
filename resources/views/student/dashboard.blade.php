@extends('layouts.student')
@section('title', 'Dashboard')
@section('breadcrumb', 'Dashboard')

@section('content')
<div style="max-width:900px;">

    <div style="margin-bottom:24px;">
        <h1 style="font-size:1.5rem; font-weight:700; color:#1A1F1F;">
            Benvenuto, {{ $student->name }} &#128075;
        </h1>
        <p style="color:#8A9696; font-size:0.875rem;">
            {{ now()->locale('it')->isoFormat('dddd D MMMM YYYY') }}
        </p>
    </div>

    @if($courses->isEmpty())
    <div style="background:white; border-radius:12px; padding:40px; text-align:center; border:2px dashed #C8D0D0;">
        <div style="font-size:3rem; margin-bottom:12px;">&#128218;</div>
        <h2 style="color:#1A1F1F; margin-bottom:8px;">Nessun corso attivo</h2>
        <p style="color:#8A9696; font-size:0.875rem;">
            Non hai ancora corsi assegnati.<br>
            Scrivi a <a href="mailto:info@noscite.it" style="color:#55B1AE;">info@noscite.it</a> per attivare il tuo percorso.
        </p>
    </div>

    @else
    <div style="display:grid; gap:16px;">
        @foreach($courses as $course)
        <div style="background:white; border-radius:12px; overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,0.06);">
            <div style="background:{{ $course->color }}; padding:16px 20px; display:flex; align-items:center; justify-content:space-between;">
                <div style="display:flex; align-items:center; gap:12px;">
                    <span style="font-size:1.5rem;">{{ $course->icon }}</span>
                    <div>
                        <div style="color:white; font-weight:700;">{{ $course->name }}</div>
                        @unless($student->is_demo)
                        <div style="color:rgba(255,255,255,0.8); font-size:0.75rem;">{{ $course->short_description }}</div>
                        @endunless
                    </div>
                </div>
                @unless($student->is_demo)
                <div style="color:white; font-size:1.25rem; font-weight:700;">{{ $course->progress_pct }}%</div>
                @endunless
            </div>

            <div style="padding:16px 20px;">
                @if($student->is_demo)
                <div style="display:flex; align-items:center; justify-content:flex-end;">
                    <a href="/learn/course/{{ $course->slug }}"
                       style="padding:6px 16px; background:#55B1AE; color:white; border-radius:6px; font-size:0.8rem; font-weight:600; text-decoration:none;">
                        Entra nel corso &rarr;
                    </a>
                </div>
                @else
                <div class="progress-bar" style="margin-bottom:12px;">
                    <div class="progress-fill" style="width:{{ $course->progress_pct }}%"></div>
                </div>
                <div style="display:flex; align-items:center; justify-content:space-between;">
                    <div style="color:#8A9696; font-size:0.8rem;">
                        {{ $course->modules_done }} di {{ $course->modules_total }} moduli completati
                    </div>
                    <div style="display:flex; gap:8px;">
                        @if($course->progress_pct >= 100)
                        <span style="padding:6px 12px; background:#E8F5F5; color:#3A8C89; border-radius:6px; font-size:0.8rem; font-weight:600;">
                            &#10003; Completato
                        </span>
                        @endif
                        <a href="/learn/course/{{ $course->slug }}"
                           style="padding:6px 16px; background:#55B1AE; color:white; border-radius:6px; font-size:0.8rem; font-weight:600; text-decoration:none;">
                            {{ $course->progress_pct == 0 ? 'Inizia' : ($course->progress_pct >= 100 ? 'Rivedi' : 'Continua') }} &rarr;
                        </a>
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endforeach
    </div>
    @endif

</div>
@endsection
