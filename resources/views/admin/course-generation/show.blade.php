@extends('layouts.admin')
@section('title', 'Revisione contenuti generati — ' . $run->course->name)
@section('content')

<div style="max-width:900px;">
    <div style="display:flex; align-items:center; gap:10px; margin-bottom:20px;">
        <a href="{{ route('admin.courses.show', $run->course) }}" style="color:#8A9696; text-decoration:none; font-size:0.85rem;">&larr; {{ $run->course->name }}</a>
    </div>

    <h2 style="font-size:1.25rem; font-weight:700; color:#1A1F1F; margin-bottom:6px;">Revisione contenuti generati</h2>
    <p style="color:#8A9696; font-size:0.85rem; margin:0 0 20px;">
        Per ogni modulo: correggi manuale discente e manuale formatore, verifica le slide, poi approva i 3
        artefatti. Il modulo diventa visibile ai discenti solo dopo "Pubblica modulo".
    </p>

    @if (session('success'))
        <div style="background:rgba(85,177,174,0.12); border:1px solid #55B1AE; color:#1A1F1F; padding:10px 14px; border-radius:8px; margin-bottom:16px; font-size:0.85rem;">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div style="background:#FBEDEC; border:1px solid #C0392B; color:#7B1E1E; padding:10px 14px; border-radius:8px; margin-bottom:16px; font-size:0.85rem;">{{ session('error') }}</div>
    @endif

    @if ($run->status === 'running')
        <div id="content-live-banner" style="display:flex; align-items:center; gap:10px; background:#FFF8EE; border:1px solid rgba(226,138,83,0.45); color:#C26A2E; padding:12px 16px; border-radius:8px; margin-bottom:20px; font-size:0.85rem; font-weight:700;">
            <span style="display:inline-block; width:12px; height:12px; border:2px solid rgba(226,138,83,0.35); border-top-color:#C26A2E; border-radius:50%; animation:cg-spin 0.8s linear infinite;"></span>
            Generazione manuali e slide in corso... la pagina si aggiorna da sola.
        </div>
        <style>@keyframes cg-spin { to { transform: rotate(360deg); } }</style>
        <script>
            (function () {
                var poll = setInterval(function () {
                    fetch('{{ route('admin.course-generation.status', $run) }}')
                        .then(function (r) { return r.json(); })
                        .then(function (d) { if (d.status !== 'running') { clearInterval(poll); window.location.reload(); } });
                }, 5000);
            })();
        </script>
    @endif

    @php
        $typeLabels = [
            'student_manual' => '📘 Manuale discente',
            'instructor_manual' => '🎓 Manuale formatore',
            'slides' => '🖼️ Slide',
        ];
        $statusLabels = [
            'draft_ai' => ['label' => 'In generazione / errore', 'color' => '#8A9696'],
            'pending_review' => ['label' => 'Da revisionare', 'color' => '#E28A53'],
            'approved' => ['label' => 'Approvato', 'color' => '#55B1AE'],
            'rejected' => ['label' => 'Rifiutato', 'color' => '#C0392B'],
            'published' => ['label' => 'Pubblicato', 'color' => '#2E8B57'],
        ];
    @endphp

    @forelse ($modules as $module)
        @php
            $artifacts = $module->generationArtifacts->keyBy('artifact_type');
            $allApproved = $artifacts->count() === 3 && $artifacts->every(fn ($a) => $a->status === 'approved');
            $anyPublished = $artifacts->contains(fn ($a) => $a->status === 'published');
        @endphp
        <div style="background:white; border-radius:10px; padding:20px; margin-bottom:20px;">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:14px;">
                <div>
                    <div style="font-size:1.05rem; font-weight:700; color:#1A1F1F;">{{ $module->title }}</div>
                    <div style="font-size:0.8rem; color:#8A9696;">{{ $module->description }}</div>
                </div>
                <div style="display:flex; gap:8px; align-items:center;">
                    @if ($anyPublished)
                        <span style="padding:4px 10px; background:rgba(46,139,87,0.12); color:#2E8B57; border-radius:12px; font-size:0.75rem; font-weight:700;">Pubblicato</span>
                    @else
                        <form method="POST" action="{{ route('admin.course-generation.modules.regenerate', [$run, $module]) }}" onsubmit="return confirm('Rigenerare i 3 artefatti di questo modulo?');">
                            @csrf
                            <button type="submit" style="padding:6px 12px; background:white; color:#3A8C89; border:1px solid #55B1AE; border-radius:6px; font-size:0.75rem; font-weight:600; cursor:pointer;">↻ Rigenera modulo</button>
                        </form>
                        <form method="POST" action="{{ route('admin.course-generation.modules.publish', [$run, $module]) }}">
                            @csrf
                            <button type="submit" {{ $allApproved ? '' : 'disabled' }}
                                    style="padding:6px 14px; background:{{ $allApproved ? '#55B1AE' : '#C8D0D0' }}; color:white; border:none; border-radius:6px; font-size:0.75rem; font-weight:700; cursor:{{ $allApproved ? 'pointer' : 'not-allowed' }};">
                                Pubblica modulo
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            @foreach (['student_manual', 'instructor_manual', 'slides'] as $type)
                @php $artifact = $artifacts->get($type); @endphp
                <div style="border:1px solid #F5F7F7; border-radius:8px; padding:14px; margin-bottom:10px;">
                    <div style="display:flex; align-items:center; justify-content:between; gap:10px; margin-bottom:8px;">
                        <strong style="font-size:0.85rem; color:#1A1F1F;">{{ $typeLabels[$type] }}</strong>
                        @if ($artifact)
                            <span style="margin-left:8px; padding:2px 8px; background:{{ $statusLabels[$artifact->status]['color'] }}22; color:{{ $statusLabels[$artifact->status]['color'] }}; border-radius:10px; font-size:0.7rem; font-weight:700;">
                                {{ $statusLabels[$artifact->status]['label'] }}
                            </span>
                            @if ($artifact->edited_by_human)
                                <span style="font-size:0.7rem; color:#8A9696;">(corretto a mano)</span>
                            @endif
                        @else
                            <span style="font-size:0.75rem; color:#8A9696;">in coda</span>
                        @endif
                    </div>

                    @if ($artifact && !empty($artifact->generation_meta['failure_reason'] ?? null))
                        <div style="background:#FBEDEC; color:#7B1E1E; padding:8px 10px; border-radius:6px; font-size:0.78rem; margin-bottom:8px;">
                            Errore: {{ $artifact->generation_meta['failure_reason'] }}
                        </div>
                    @endif

                    @if ($artifact && $artifact->isReviewable())
                        <form method="POST" action="{{ route('admin.course-generation.artifacts.approve', $artifact) }}">
                            @csrf
                            @if ($type !== 'slides')
                                <textarea name="html" rows="6" style="width:100%; padding:10px; border:1px solid #E8F5F5; border-radius:6px; font-size:0.82rem; font-family:inherit; margin-bottom:8px;">{{ $artifact->content['html'] ?? '' }}</textarea>
                            @else
                                @if ($artifact->presentation && $artifact->presentation->status === 'ready')
                                    <a href="{{ route('admin.courses.modules.presentation.download', [$run->course, $module]) }}" style="font-size:0.8rem; color:#3A8C89;">Scarica bozza .pptx</a>
                                @elseif ($artifact->presentation && $artifact->presentation->status === 'failed')
                                    <span style="font-size:0.8rem; color:#C0392B;">Generazione slide fallita.</span>
                                @else
                                    <span style="font-size:0.8rem; color:#8A9696;">Slide in generazione...</span>
                                @endif
                            @endif
                            <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:8px;">
                                <button type="submit" formaction="{{ route('admin.course-generation.artifacts.reject', $artifact) }}"
                                        style="padding:6px 14px; border:1px solid #C0392B; color:#C0392B; background:white; border-radius:6px; font-size:0.78rem; font-weight:600; cursor:pointer;">Rifiuta</button>
                                <button type="submit" style="padding:6px 14px; background:#55B1AE; color:white; border:none; border-radius:6px; font-size:0.78rem; font-weight:700; cursor:pointer;">Approva</button>
                            </div>
                        </form>
                    @elseif ($artifact && $type !== 'slides' && in_array($artifact->status, ['approved', 'published']))
                        <div style="font-size:0.8rem; color:#4A5252; max-height:120px; overflow:auto; background:#FAFCFC; padding:8px; border-radius:6px;">{!! $artifact->content['html'] ?? '' !!}</div>
                    @endif
                </div>
            @endforeach
        </div>
    @empty
        <p style="color:#8A9696; font-size:0.85rem;">Nessun modulo ancora generato.</p>
    @endforelse
</div>
@endsection
