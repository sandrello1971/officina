@extends('layouts.student')
@section('title', $course->name)
@section('breadcrumb', $course->name)

@section('content')

<div style="max-width:800px;">

    <div style="background:{{ $course->color }}; border-radius:12px; padding:24px; margin-bottom:24px; color:white;">
        <div style="display:flex; align-items:center; gap:16px; margin-bottom:16px;">
            <span style="font-size:2.5rem;">{{ $course->icon }}</span>
            <div>
                <h1 style="font-size:1.5rem; font-weight:700;">{{ $course->name }}</h1>
                <p style="opacity:0.85; font-size:0.875rem;">{{ $course->description }}</p>
            </div>
        </div>
        <div>
            <div style="display:flex; justify-content:space-between; margin-bottom:6px; font-size:0.8rem; opacity:0.9;">
                <span>Progresso complessivo</span>
                <span>{{ $progressPercent }}% ({{ $completedModules }}/{{ $totalModules }})</span>
            </div>
            <div style="height:8px; background:rgba(255,255,255,0.3); border-radius:4px;">
                <div style="height:100%; width:{{ $progressPercent }}%; background:white; border-radius:4px; transition:width 0.3s;"></div>
            </div>
        </div>
    </div>

    <div style="background:linear-gradient(135deg,#1A1F1F,#3A8C89); border-radius:12px; padding:16px 20px; margin-bottom:24px; display:flex; align-items:center; justify-content:space-between;">
        <div>
            <div style="color:#55B1AE; font-weight:700; font-size:0.875rem;">&#10022; Assistente AI — Minerva</div>
            <div style="color:#8A9696; font-size:0.75rem;">Hai dubbi sui contenuti? Chiedimi!</div>
        </div>
        <a href="/learn/chat/{{ $course->slug }}"
           style="padding:8px 16px; background:#E28A53; color:white; border-radius:6px; font-size:0.8rem; font-weight:600; text-decoration:none;">
            Apri chat &rarr;
        </a>
    </div>

    <div style="display:flex; flex-direction:column; gap:8px;">
        @foreach($modules as $index => $module)
        @php
            $mp = $progressByModule[$module->id] ?? null;
            $status = $mp?->status ?? 'not_started';
        @endphp
        <div style="background:white; border-radius:10px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
            <div style="padding:16px 20px; display:flex; align-items:center; gap:16px;">
                <div style="width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:0.875rem; flex-shrink:0;
                    background:{{ $status === 'completed' ? '#E8F5F5' : ($status === 'in_progress' ? '#fff3ec' : '#F5F7F7') }};
                    color:{{ $status === 'completed' ? '#3A8C89' : ($status === 'in_progress' ? '#c97a45' : '#8A9696') }};">
                    {{ $status === 'completed' ? '✓' : ($index + 1) }}
                </div>

                <div style="flex:1;">
                    <div style="font-weight:600; color:#1A1F1F; font-size:0.9rem;">{{ $module->title }}</div>
                    <div style="color:#8A9696; font-size:0.75rem;">
                        @if($module->duration_minutes)
                            @php
                                $mins = $module->duration_minutes;
                                if ($mins >= 60) {
                                    $h = floor($mins / 60);
                                    $m = $mins % 60;
                                    $durationLabel = $h . 'h' . ($m > 0 ? ' ' . $m . "'" : '');
                                } else {
                                    $durationLabel = $mins . "'";
                                }
                            @endphp
                            &#9201; {{ $durationLabel }}
                        @endif
                        @if($module->description)
                            &middot; {{ \Illuminate\Support\Str::limit($module->description, 60) }}
                        @endif
                    </div>
                </div>

                <div style="display:flex; align-items:center; gap:8px;">
                    @if($status === 'completed')
                    <span style="font-size:0.7rem; padding:3px 8px; background:#E8F5F5; color:#3A8C89; border-radius:4px; font-weight:600;">✓ Completato</span>
                    @elseif($status === 'in_progress')
                    <span style="font-size:0.7rem; padding:3px 8px; background:#fff3ec; color:#c97a45; border-radius:4px; font-weight:600;">In corso</span>
                    @else
                    <span style="font-size:0.7rem; padding:3px 8px; background:#F5F7F7; color:#8A9696; border-radius:4px;">Non iniziato</span>
                    @endif

                    <a href="/learn/course/{{ $course->slug }}/module/{{ $module->id }}"
                       style="padding:6px 14px; background:#55B1AE; color:white; border-radius:6px; font-size:0.8rem; font-weight:600; text-decoration:none;">
                        Apri
                    </a>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    @if($progressPercent >= 70 && $finalQuiz)
    <div style="background:linear-gradient(135deg,#1A1F1F,#252B2B); border-radius:16px; padding:24px; margin-top:16px; border:2px solid rgba(85,177,174,0.4); text-align:center;">
        <div style="font-size:2rem; margin-bottom:12px;">🎓</div>
        @if($certificationPassed)
        <h3 style="color:#55B1AE; font-weight:700; margin-bottom:6px;">Esame finale superato!</h3>
        <p style="color:#8A9696; font-size:0.875rem; margin-bottom:8px;">
            Hai ottenuto: <span style="color:#55B1AE; font-weight:600;">{{ $course->certification_name }}</span>
        </p>
        <a href="/learn/certificate/{{ $course->slug }}"
           style="display:inline-block; margin-top:12px; padding:10px 24px; background:#55B1AE; color:white; border-radius:8px; font-size:0.875rem; font-weight:700; text-decoration:none;">
            ⬇ Scarica il tuo certificato
        </a>
        @else
        <h3 style="color:white; font-weight:700; margin-bottom:6px;">Pronto per l'esame finale?</h3>
        <p style="color:#8A9696; font-size:0.875rem; margin-bottom:16px;">
            Hai completato il {{ $progressPercent }}% del corso. Puoi sostenere l'esame finale.
        </p>
        <a href="/learn/quiz/{{ $finalQuiz->id }}"
           style="display:inline-block; padding:12px 32px; background:#55B1AE; color:white; border-radius:8px; font-weight:700; text-decoration:none; font-size:0.9rem;">
            Esame finale →
        </a>
        @endif
    </div>
    @endif

</div>
@endsection
