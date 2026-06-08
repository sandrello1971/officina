@extends('layouts.docente')
@section('title', $lesson->title)
@section('breadcrumb', 'Argomenti / ' . ($lesson->topic->name ?? '') . ' / ' . $lesson->title)
@section('content')
@php
    $readyMaterials = $materials->where('status', 'ready')->filter(fn($m) => trim((string)$m->extracted_text) !== '');
    $canCompose = $readyMaterials->isNotEmpty();
    $meta = (array) $lesson->generation_meta;
    $artifactLabels = [
        'summary' => 'Riassunto', 'mindmap' => 'Mappa mentale', 'conceptmap' => 'Mappa concettuale',
        'quiz' => 'Quiz', 'outline' => 'Scaletta',
    ];
@endphp
<div style="max-width:980px;" x-data="lessonStatus('{{ $lesson->id }}', '{{ $lesson->generation_status }}')">
    <div style="margin-bottom:8px;">
        <a href="{{ route('docente.topics.show', $lesson->topic_id) }}" style="color:#55B1AE; text-decoration:none; font-size:0.82rem;">&larr; {{ $lesson->topic->name ?? 'Argomento' }}</a>
    </div>
    <h1 style="font-size:1.4rem; font-weight:700; color:#1A1F1F;">{{ $lesson->title }}</h1>
    <p style="color:#8A9696; font-size:0.85rem; margin-bottom:16px;">{{ $lesson->topic->subject->name ?? '' }}</p>

    @if(session('success'))<div style="margin-bottom:12px; padding:10px 14px; background:#E8F5F5; border-left:4px solid #55B1AE; border-radius:6px; color:#3A8C89; font-size:0.85rem;">{{ session('success') }}</div>@endif
    @if(session('error'))<div style="margin-bottom:12px; padding:10px 14px; background:#FDECE2; border-left:4px solid #E28A53; border-radius:6px; color:#A8521F; font-size:0.85rem;">{{ session('error') }}</div>@endif
    @if($errors->any())<div style="margin-bottom:12px; padding:10px 14px; background:#FDECE2; border-left:4px solid #E28A53; border-radius:6px; color:#A8521F; font-size:0.85rem;"><ul style="margin:0 0 0 18px;">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    {{-- Materiali sorgente --}}
    <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:16px 18px; margin-bottom:16px;">
        <div style="font-size:0.75rem; font-weight:700; color:#4A5252; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:10px;">Materiali della lezione ({{ $materials->count() }})</div>
        @forelse($materials as $m)
            @php $mb = ['pending'=>['#8A9696','in coda'],'processing'=>['#E28A53','in elaborazione'],'ready'=>['#3A8C89','pronto'],'failed'=>['#A8521F','fallito']]; [$c,$l]=$mb[$m->status]??['#8A9696',$m->status]; @endphp
            <div style="display:flex; align-items:center; gap:8px; padding:6px 0; border-top:1px solid #F0F2F2; font-size:0.82rem;">
                <span style="color:#3A8C89;">&#128196;</span>
                <a href="{{ route('docente.materials.show', $m) }}" style="flex:1; color:#1A1F1F; text-decoration:none;">{{ $m->title }} <span style="color:#8A9696;">· {{ $m->source_type }}</span></a>
                <span style="font-size:0.7rem; font-weight:700; color:{{ $c }}; border:1px solid {{ $c }}; border-radius:4px; padding:1px 8px;">{{ $l }}</span>
            </div>
        @empty
            <p style="color:#8A9696; font-size:0.85rem;">Nessun materiale assegnato. Vai all'<a href="{{ route('docente.topics.show', $lesson->topic_id) }}" style="color:#55B1AE;">argomento</a> per classificarne.</p>
        @endforelse
    </div>

    {{-- Stato composizione (polling) --}}
    <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:16px 18px; margin-bottom:16px;">
        <div style="display:flex; align-items:center; gap:12px;">
            <div style="font-size:0.75rem; font-weight:700; color:#4A5252; text-transform:uppercase; letter-spacing:0.05em; flex:1;">Corpo della lezione</div>
            <template x-if="status==='generating'">
                <span style="display:flex; align-items:center; gap:8px; color:#E28A53; font-size:0.85rem; font-weight:600;">
                    <span style="width:10px;height:10px;border-radius:50%;background:#E28A53;display:inline-block;animation:pulse 1s infinite;"></span>
                    <span>Composizione in corso…</span>
                </span>
            </template>
            <template x-if="status==='ready'"><span style="color:#3A8C89; font-weight:700; font-size:0.85rem;">&#10003; Pronta</span></template>
            <template x-if="status==='failed'"><span style="color:#A8521F; font-weight:700; font-size:0.85rem;">&#10007; Composizione fallita</span></template>
            <template x-if="status==='draft'"><span style="color:#8A9696; font-weight:600; font-size:0.85rem;">Bozza</span></template>
        </div>

        @if($lesson->generation_status === 'failed' && ($meta['failure_reason'] ?? null))
            <p style="margin-top:8px; font-size:0.82rem; color:#A8521F;">{{ $meta['failure_reason'] }}</p>
        @endif

        @if($lesson->generation_status === 'ready' && !empty($meta))
            <div style="margin-top:8px; font-size:0.75rem; color:#8A9696;">
                @isset($meta['model']) modello: {{ $meta['model'] }} @endisset
                @isset($meta['tokens_in']) · token in/out: {{ $meta['tokens_in'] }}/{{ $meta['tokens_out'] ?? 0 }} @endisset
                @isset($meta['sources_count']) · fonti: {{ $meta['sources_count'] }} @endisset
                @if($meta['segments_preserved'] ?? false) · <span title="riferimenti temporali audio/video conservati">timestamp conservati</span> @endif
            </div>
        @endif

        {{-- Azioni di composizione (Feedback UX: data-async, anti-doppio-submit) --}}
        <div style="margin-top:12px; display:flex; gap:8px; flex-wrap:wrap;" x-show="status!=='generating'">
            @if($lesson->generation_status === 'draft' || $lesson->generation_status === 'failed')
                <form method="POST" action="{{ route('docente.lessons.generate', $lesson) }}" data-async>
                    @csrf
                    <button @disabled(!$canCompose) data-busy-label="Composizione in corso…"
                        style="padding:9px 16px; background:{{ $canCompose ? '#55B1AE' : '#C8D0D0' }}; color:white; border:none; border-radius:8px; font-size:0.85rem; font-weight:600; cursor:{{ $canCompose ? 'pointer' : 'not-allowed' }};">
                        {{ $lesson->generation_status === 'failed' ? 'Riprova composizione' : 'Componi lezione' }}
                    </button>
                </form>
                @unless($canCompose)<span style="font-size:0.78rem; color:#8A9696; align-self:center;">Serve almeno un materiale pronto con testo.</span>@endunless
            @elseif($lesson->generation_status === 'ready')
                <form method="POST" action="{{ route('docente.lessons.regenerate', $lesson) }}" data-async
                      onsubmit="return confirm('Ricomporre la lezione? Il contenuto attuale (comprese le modifiche manuali) verrà sovrascritto.');">
                    @csrf
                    <button data-busy-label="Ricomposizione…" style="padding:9px 16px; background:white; color:#E28A53; border:1px solid #E28A53; border-radius:8px; font-size:0.85rem; font-weight:600; cursor:pointer;">Ricomponi (sovrascrive)</button>
                </form>
            @endif
        </div>
    </div>

    {{-- Editor + anteprima del corpo (sempre modificabile dopo la generazione) --}}
    @if($lesson->generation_status === 'ready' || !empty($lesson->content))
        <div x-data="{tab:'edit'}" style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:18px; margin-bottom:16px;">
            <div style="display:flex; gap:8px; margin-bottom:12px;">
                <button type="button" @click="tab='edit'" :style="tab==='edit' ? 'background:#1A1F1F;color:white' : 'background:#F0F2F2;color:#4A5252'" style="padding:6px 14px; border:none; border-radius:6px; font-size:0.8rem; cursor:pointer;">Modifica</button>
                <button type="button" @click="tab='preview'" :style="tab==='preview' ? 'background:#1A1F1F;color:white' : 'background:#F0F2F2;color:#4A5252'" style="padding:6px 14px; border:none; border-radius:6px; font-size:0.8rem; cursor:pointer;">Anteprima</button>
            </div>

            <div x-show="tab==='edit'">
                <form method="POST" action="{{ route('docente.lessons.content', $lesson) }}">
                    @csrf @method('PATCH')
                    <textarea name="content" rows="22" style="width:100%; padding:12px; border:1px solid #C8D0D0; border-radius:8px; font-family:ui-monospace,monospace; font-size:0.82rem; line-height:1.5; color:#1A1F1F;">{{ $lesson->content }}</textarea>
                    <div style="margin-top:10px;"><button style="padding:9px 16px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.85rem; font-weight:600; cursor:pointer;">Salva modifiche</button></div>
                </form>
            </div>

            <div x-show="tab==='preview'" style="display:none;">
                <div class="md-body" style="font-size:0.9rem; line-height:1.65; color:#1A1F1F;">{!! schola_markdown($lesson->content) !!}</div>
            </div>
        </div>

        {{-- Artefatti a livello di lezione --}}
        @if($lesson->generation_status === 'ready')
        <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:16px 18px; margin-bottom:16px;">
            <div style="font-size:0.75rem; font-weight:700; color:#4A5252; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:10px;">Genera dalla lezione</div>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                @foreach(['summary'=>'Riassunto','outline'=>'Scaletta','mindmap'=>'Mappa mentale','conceptmap'=>'Mappa concettuale'] as $t=>$lab)
                    <form method="POST" action="{{ route('docente.lessons.artifacts.generate', $lesson) }}" data-async>
                        @csrf<input type="hidden" name="type" value="{{ $t }}">
                        <button data-busy-label="Genero…" style="padding:8px 14px; background:white; color:#3A8C89; border:1px solid #55B1AE; border-radius:8px; font-size:0.8rem; font-weight:600; cursor:pointer;">{{ $lab }}</button>
                    </form>
                @endforeach
                <form method="POST" action="{{ route('docente.lessons.artifacts.generate', $lesson) }}" data-async style="display:flex; gap:6px; align-items:center;">
                    @csrf<input type="hidden" name="type" value="quiz">
                    <input type="number" name="num_questions" value="10" min="3" max="20" style="width:60px; padding:7px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.8rem;">
                    <button data-busy-label="Genero…" style="padding:8px 14px; background:white; color:#3A8C89; border:1px solid #55B1AE; border-radius:8px; font-size:0.8rem; font-weight:600; cursor:pointer;">Quiz</button>
                </form>
            </div>

            @if($artifacts->isNotEmpty())
            <div style="margin-top:12px;">
                @foreach($artifacts as $a)
                    <div x-data="artifactRow('{{ $a->id }}','{{ $a->status }}')" style="display:flex; align-items:center; gap:8px; padding:7px 0; border-top:1px solid #F0F2F2; font-size:0.82rem;">
                        <span style="flex:1;"><a href="{{ route('docente.artifacts.show', $a) }}" style="color:#1A1F1F; text-decoration:none;">{{ $artifactLabels[$a->type] ?? $a->type }} — {{ $a->title }}</a></span>
                        <template x-if="status==='generating'"><span style="color:#E28A53; font-size:0.75rem; font-weight:600;">in corso…</span></template>
                        <template x-if="status==='ready'"><span style="color:#3A8C89; font-size:0.75rem; font-weight:700;">&#10003;</span></template>
                        <template x-if="status==='failed'"><span style="color:#A8521F; font-size:0.75rem; font-weight:700;">&#10007;</span></template>
                    </div>
                @endforeach
            </div>
            @endif
        </div>
        @endif
    @endif
</div>

@push('styles')<style>@keyframes pulse{0%,100%{opacity:1}50%{opacity:.3}}</style>@endpush
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script>
function lessonStatus(id, initial) {
    return {
        status: initial,
        init() { if (this.status === 'generating') this.poll(); },
        poll() {
            const timer = setInterval(async () => {
                try {
                    const r = await fetch(`/docente/lezioni/${id}/stato`, {headers: {'X-Requested-With':'XMLHttpRequest'}});
                    const d = await r.json();
                    this.status = d.status;
                    if (d.status === 'ready' || d.status === 'failed') {
                        clearInterval(timer);
                        window.location.reload();
                    }
                } catch(e) {}
            }, 5000);
        },
    };
}
function artifactRow(id, initial) {
    return {
        status: initial,
        init() { if (this.status === 'generating') this.poll(); },
        poll() {
            const timer = setInterval(async () => {
                try {
                    const r = await fetch(`/docente/artefatti/${id}/stato`, {headers: {'X-Requested-With':'XMLHttpRequest'}});
                    const d = await r.json();
                    this.status = d.status;
                    if (d.status === 'ready' || d.status === 'failed') clearInterval(timer);
                } catch(e) {}
            }, 5000);
        },
    };
}
</script>
@endpush
@endsection
