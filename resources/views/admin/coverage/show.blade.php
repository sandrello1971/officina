@extends('layouts.admin')
@section('title', 'Copertura — ' . $course->name)
@section('content')

<div style="display:flex; align-items:center; gap:10px; margin-bottom:6px;">
    <a href="{{ route('admin.coverage.index') }}" style="color:#8A9696; text-decoration:none; font-size:0.85rem;">&larr; Copertura</a>
    <h1 style="font-size:1.3rem; color:#1A1F1F; margin:0;">&#129517; {{ $course->name }}</h1>
</div>

@if (session('success'))
    <div data-flash style="display:flex; gap:10px; background:rgba(85,177,174,0.12); border:1px solid #55B1AE; color:#1A1F1F; padding:10px 14px; border-radius:8px; margin:12px 0; font-size:0.85rem;">
        <span style="flex:1;">{{ session('success') }}</span>
        <button type="button" data-dismiss-flash style="background:none; border:none; color:#3A8C89; cursor:pointer; font-size:1rem;">&times;</button>
    </div>
@endif
@if (session('error'))
    <div data-flash style="display:flex; gap:10px; background:#FBEDEC; border:1px solid #C0392B; color:#7B1E1E; padding:10px 14px; border-radius:8px; margin:12px 0; font-size:0.85rem;">
        <span style="flex:1;">{{ session('error') }}</span>
        <button type="button" data-dismiss-flash style="background:none; border:none; color:#C0392B; cursor:pointer; font-size:1rem;">&times;</button>
    </div>
@endif

{{-- Topic + Analizza --}}
<div style="background:white; border-radius:10px; padding:16px; border:1px solid #E6EBEB; margin:14px 0;">
    @php($sugg = session('topic_suggestion'))
    @php($topicValue = $sugg['suggested_topic'] ?? $topic)
    <div style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
        <form method="POST" action="{{ route('admin.coverage.topic', $course) }}" style="display:flex; gap:8px; align-items:flex-end;">
            @csrf
            <div>
                <label style="font-size:0.72rem; color:#8A9696; font-weight:700; display:block;">Topic del corso (riusa un esistente o creane uno)</label>
                <input list="topic-list" name="topic" value="{{ $topicValue }}" required placeholder="es. agenti-ai"
                       style="padding:8px; border:1px solid #E8F5F5; border-radius:6px; font-size:0.82rem; min-width:220px;">
                <datalist id="topic-list">
                    @foreach ($sourceTopics as $t)<option value="{{ $t }}">@endforeach
                </datalist>
            </div>
            <button type="submit" style="padding:8px 14px; background:white; color:#3A8C89; border:1px solid #55B1AE; border-radius:6px; font-size:0.78rem; font-weight:600; cursor:pointer;">Salva topic</button>
        </form>

        <form method="POST" action="{{ route('admin.coverage.topic.suggest', $course) }}">
            @csrf
            <button type="submit" style="padding:8px 12px; background:#FFF8EE; color:#C26A2E; border:1px solid rgba(226,138,83,0.45); border-radius:6px; font-size:0.78rem; font-weight:600; cursor:pointer;">✨ Suggerisci topic</button>
        </form>

        <div style="flex:1;"></div>

        @if ($topic && $hasApprovedSources)
        <form method="POST" action="{{ route('admin.coverage.analyze', $course) }}">
            @csrf
            <button type="submit" style="padding:9px 18px; background:#E28A53; color:white; border:none; border-radius:6px; font-size:0.82rem; font-weight:700; cursor:pointer;">&#128270; Analizza copertura</button>
        </form>
        @endif
    </div>

    @if ($sugg)
    <div style="margin-top:10px; padding:10px 12px; border-radius:8px; font-size:0.8rem;
                background:{{ $sugg['is_existing'] ? 'rgba(85,177,174,0.10)' : '#FFF8EE' }};
                border:1px solid {{ $sugg['is_existing'] ? 'rgba(85,177,174,0.4)' : 'rgba(226,138,83,0.45)' }};">
        @if ($sugg['is_existing'])
            ♻️ <strong>Riusa un topic esistente:</strong> <code>{{ $sugg['suggested_topic'] }}</code>
        @else
            🆕 <strong>Topic nuovo proposto:</strong> <code>{{ $sugg['suggested_topic'] }}</code>
            <span style="color:#8A9696;">(non esisteva: salvandolo entra nel vocabolario per i corsi futuri)</span>
        @endif
        @if (!empty($sugg['alternatives']))
            <span style="color:#8A9696;">· alternative: {{ implode(', ', $sugg['alternatives']) }}</span>
        @endif
        <span style="color:#8A9696;"> — precompilato nel campo: conferma con «Salva topic».</span>
    </div>
    @endif

    @if (!$topic)
        <p style="color:#C26A2E; font-size:0.8rem; margin:12px 0 0;">⚠ Imposta un <strong>topic</strong> per questo corso (scelto tra i topic delle fonti) prima di analizzare.</p>
    @elseif (!$hasApprovedSources)
        <p style="color:#C26A2E; font-size:0.8rem; margin:12px 0 0;">⚠ Nessuna fonte attendibile <strong>approvata</strong> per «{{ $topic }}». <a href="{{ route('admin.sources.index', ['topic' => $topic]) }}" style="color:#3A8C89; font-weight:600;">Aggiungi/approva fonti per questo dominio</a> prima di analizzare.</p>
    @endif

    @if ($lastRun)
        @php($st = $lastRun->status)
        <div style="margin-top:12px; font-size:0.78rem; color:{{ $st === 'failed' ? '#C0392B' : ($st === 'completed' ? '#3A8C89' : '#C26A2E') }};">
            Ultima analisi: <strong>{{ $st === 'failed' ? '✗ fallita' : ($st === 'completed' ? '✓ completata' : '⏳ in corso') }}</strong>
            · {{ optional($lastRun->created_at)->format('d/m H:i') }}
            @if ($st === 'completed') · {{ $lastRun->gaps_found }} gap nuovi @endif
            @if ($st === 'failed')
                <div style="font-family:'JetBrains Mono','SF Mono',monospace; color:#7B1E1E; font-size:0.74rem; margin-top:3px;">{{ $lastRun->failure_reason }}</div>
            @endif
            @if ($st === 'running') <span style="color:#8A9696;">— ricarica la pagina per aggiornare.</span> @endif
        </div>
    @endif
</div>

{{-- Gap candidati --}}
<div style="font-weight:700; color:#1A1F1F; margin:18px 0 8px;">Gap candidati ({{ $gaps->count() }})</div>
@forelse ($gaps as $g)
<div style="background:white; border:1px solid #E6EBEB; border-radius:8px; padding:12px 14px; margin-bottom:8px;">
    <div style="display:flex; gap:10px; align-items:flex-start;">
        <div style="flex:1; min-width:0;">
            <strong style="color:#1A1F1F;">{{ $g->title }}</strong>
            <span style="margin-left:8px; padding:1px 8px; border-radius:10px; font-size:0.7rem; font-weight:700; color:#5A6666; background:#F5F7F7;">conf {{ $g->confidence !== null ? number_format($g->confidence, 2) : 'n/d' }}</span>
            <p style="color:#5A6666; font-size:0.82rem; margin:5px 0;">{{ $g->rationale }}</p>
            @if ($g->source_url)
                <div style="font-size:0.74rem;">Fonte: <a href="{{ $g->source_url }}" target="_blank" rel="noopener" style="color:#3A8C89;">{{ $g->source_label ?: $g->source_url }}</a></div>
            @elseif ($g->source_label)
                <div style="font-size:0.74rem; color:#8A9696;">Fonte: {{ $g->source_label }}</div>
            @endif
        </div>
        <div style="display:flex; gap:6px;">
            <form method="POST" action="{{ route('admin.coverage.accept', $g) }}">@csrf @method('PATCH')
                <button type="submit" style="padding:6px 12px; background:white; color:#3A8C89; border:1px solid #55B1AE; border-radius:6px; font-size:0.75rem; font-weight:600; cursor:pointer;">✓ Accetta</button>
            </form>
            <form method="POST" action="{{ route('admin.coverage.dismiss', $g) }}">@csrf @method('PATCH')
                <button type="submit" style="padding:6px 12px; background:white; color:#C26A2E; border:1px solid #E28A53; border-radius:6px; font-size:0.75rem; font-weight:600; cursor:pointer;">✗ Scarta</button>
            </form>
        </div>
    </div>
</div>
@empty
<div style="color:#8A9696; font-size:0.85rem; padding:20px; text-align:center; background:#F5F7F7; border-radius:8px;">
    Nessun gap candidato. {{ $topic && $hasApprovedSources ? 'Lancia «Analizza copertura».' : '' }}
</div>
@endforelse

{{-- Fase B — gap accettati: generazione/revisione bozze (NESSUN inserimento nel corso) --}}
@if ($accepted->count() > 0)
<div style="font-weight:700; color:#1A1F1F; margin:22px 0 8px;">Gap accettati — bozze ({{ $accepted->count() }})</div>
<p style="color:#8A9696; font-size:0.76rem; margin:0 0 10px;">Le bozze restano qui per la revisione: <strong>non vengono inserite nel corso</strong> (l'inserimento è una fase successiva).</p>
@php($dbadge = ['generating' => ['#C26A2E','⏳ in generazione'], 'draft' => ['#3A8C89','bozza pronta'], 'approved' => ['#1A7F5A','✓ approvata (pronta per inserimento)'], 'discarded' => ['#8A9696','scartata'], 'failed' => ['#C0392B','✗ generazione fallita']])
@foreach ($accepted as $g)
@php($d = $g->draft)
<div style="background:white; border:1px solid #E6EBEB; border-radius:8px; padding:12px 14px; margin-bottom:8px; display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
    <div style="flex:1; min-width:200px;">
        <strong style="color:#1A1F1F;">{{ $g->title }}</strong>
        @if ($d)
            @php($db = $dbadge[$d->status] ?? ['#8A9696', $d->status])
            <span style="margin-left:8px; padding:1px 9px; border-radius:10px; font-size:0.7rem; font-weight:700; color:{{ $db[0] }}; background:#F5F7F7;">{{ $db[1] }}</span>
            @if ($d->status === 'failed')<div style="font-family:'JetBrains Mono',monospace; color:#7B1E1E; font-size:0.72rem; margin-top:3px;">{{ $d->error }}</div>@endif
        @endif
    </div>
    <div style="display:flex; gap:6px;">
        @if (!$d)
            <form method="POST" action="{{ route('admin.coverage.generate', $g) }}">@csrf
                <button type="submit" style="padding:6px 12px; background:#E28A53; color:white; border:none; border-radius:6px; font-size:0.75rem; font-weight:700; cursor:pointer;">&#9998; Genera bozza</button>
            </form>
        @else
            @if (in_array($d->status, ['draft', 'approved', 'failed']))
            <a href="{{ route('admin.coverage.draft', $g) }}" style="padding:6px 12px; background:#55B1AE; color:white; border-radius:6px; text-decoration:none; font-size:0.75rem; font-weight:600;">Vedi bozza</a>
            @endif
            @if ($d->status !== 'generating')
            <form method="POST" action="{{ route('admin.coverage.generate', $g) }}">@csrf
                <button type="submit" style="padding:6px 12px; background:white; color:#C26A2E; border:1px solid #E28A53; border-radius:6px; font-size:0.75rem; font-weight:600; cursor:pointer;">↻ Rigenera</button>
            </form>
            @endif
        @endif
    </div>
</div>
@endforeach
@endif

<script>
document.querySelectorAll('[data-dismiss-flash]').forEach(function (b) { b.addEventListener('click', function () { b.closest('[data-flash]').remove(); }); });
</script>
@endsection
