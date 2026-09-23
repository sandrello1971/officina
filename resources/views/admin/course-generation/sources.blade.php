@extends('layouts.admin')
@section('title', 'Seleziona le fonti — ' . $run->course->name)
@section('content')

<div style="max-width:800px;">
    <div style="display:flex; align-items:center; gap:10px; margin-bottom:20px;">
        <a href="{{ route('admin.courses.show', $run->course) }}" style="color:#8A9696; text-decoration:none; font-size:0.85rem;">&larr; {{ $run->course->name }}</a>
    </div>

    <h2 style="font-size:1.25rem; font-weight:700; color:#1A1F1F; margin-bottom:6px;">Seleziona le fonti</h2>
    <p style="color:#8A9696; font-size:0.85rem; margin:0 0 20px;">
        Argomento: <strong>{{ $run->brief['topic'] ?? '—' }}</strong>. Spunta i documenti da usare per generare
        il corso — quelli più pertinenti all'argomento sono già selezionati. Puoi caricarne altri (anche
        PPTX o video) o proporre fonti esterne prima di continuare.
    </p>

    @if (session('success'))
        <div style="background:rgba(85,177,174,0.12); border:1px solid #55B1AE; color:#1A1F1F; padding:10px 14px; border-radius:8px; margin-bottom:16px; font-size:0.85rem;">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div style="background:#FBEDEC; border:1px solid #C0392B; color:#7B1E1E; padding:10px 14px; border-radius:8px; margin-bottom:16px; font-size:0.85rem;">{{ session('error') }}</div>
    @endif

    {{-- Documenti già presenti sul corso, ordinati per pertinenza --}}
    <div style="background:white; border-radius:10px; padding:20px; margin-bottom:16px;">
        <h3 style="font-weight:700; color:#1A1F1F; font-size:0.95rem; margin-bottom:12px;">Documenti del corso</h3>

        <form method="POST" action="{{ route('admin.course-generation.sources.confirm', $run) }}">
            @csrf

            @if (empty($ranked))
                <p style="font-size:0.82rem; color:#8A9696;">Nessun documento ancora. Caricane uno qui sotto.</p>
            @else
                <div style="display:flex; flex-direction:column; gap:8px; margin-bottom:16px;">
                    @foreach ($ranked as $item)
                    @php($key = $item['source_type'] . ':' . ($item['source_type'] === 'document_rag' ? $item['title'] : $item['id']))
                    <label style="display:flex; gap:10px; align-items:flex-start; padding:10px; border:1px solid #F5F7F7; border-radius:8px; cursor:pointer;">
                        <input type="checkbox" name="sources[]" value="{{ $key }}" @checked($item['suggested']) style="margin-top:3px;">
                        <div style="flex:1;">
                            <div style="font-size:0.85rem; font-weight:600; color:#1A1F1F;">
                                {{ $item['title'] }}
                                @if ($item['suggested'])
                                    <span style="margin-left:6px; padding:2px 8px; background:rgba(85,177,174,0.15); color:#3A8C89; border-radius:10px; font-size:0.68rem; font-weight:700;">pertinente</span>
                                @endif
                                <span style="margin-left:6px; font-size:0.7rem; color:#8A9696;">{{ $item['source_type'] === 'document_rag' ? 'Documenti AI' : 'Materiali Formatore' }}</span>
                            </div>
                            <div style="font-size:0.78rem; color:#8A9696; margin-top:2px;">{{ $item['snippet'] }}{{ mb_strlen($item['snippet']) >= 200 ? '…' : '' }}</div>
                        </div>
                    </label>
                    @endforeach
                </div>

                <button type="submit" data-guard-submit style="padding:10px 24px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.875rem; font-weight:700; cursor:pointer;">
                    Conferma selezione e genera struttura corso &rarr;
                </button>
            @endif
        </form>
    </div>

    {{-- Upload diretto senza uscire dal flusso --}}
    <div style="background:white; border-radius:10px; padding:20px; margin-bottom:16px;">
        <h3 style="font-weight:700; color:#1A1F1F; font-size:0.95rem; margin-bottom:12px;">Carica un altro documento</h3>
        <form method="POST" action="{{ route('admin.rag.upload') }}" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="course_id" value="{{ $run->course_id }}">
            <div style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
                <div style="flex:1; min-width:240px;">
                    <label style="font-size:0.78rem; font-weight:600; color:#4A5252; display:block; margin-bottom:4px;">File (PDF, DOCX, TXT, PPTX, video MP4/MOV/AVI/WEBM)</label>
                    <input type="file" name="files[]" accept=".pdf,.doc,.docx,.txt,.pptx,.mp4,.mov,.avi,.webm" required multiple
                           style="width:100%; padding:8px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.82rem;">
                </div>
                <button type="submit" data-guard-submit style="padding:9px 18px; background:white; color:#3A8C89; border:1px solid #55B1AE; border-radius:6px; font-size:0.82rem; font-weight:600; cursor:pointer;">Carica</button>
            </div>
            <p style="font-size:0.72rem; color:#8A9696; margin-top:6px;">I video vengono trascritti in automatico e compaiono qui sopra dopo qualche minuto (ricarica la pagina).</p>
        </form>
    </div>

    {{-- Fonti esterne proposte in base all'argomento --}}
    @if ($p26Enabled)
    <div style="background:white; border-radius:10px; padding:20px;">
        <h3 style="font-weight:700; color:#1A1F1F; font-size:0.95rem; margin-bottom:12px;">Fonti esterne per «{{ $run->brief['topic'] ?? '' }}»</h3>

        <form method="POST" action="{{ route('admin.course-generation.sources.suggest', $run) }}" style="margin-bottom:14px;">
            @csrf
            <button type="submit" data-guard-submit style="padding:9px 18px; background:white; color:#C26A2E; border:1px solid #E28A53; border-radius:6px; font-size:0.82rem; font-weight:600; cursor:pointer;">
                &#10024; Proponi fonti esterne per questo argomento
            </button>
        </form>

        @forelse ($trustedSources as $source)
        <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; padding:10px; border:1px solid #F5F7F7; border-radius:8px; margin-bottom:8px;">
            <div>
                <div style="font-size:0.83rem; font-weight:600; color:#1A1F1F;">{{ $source->label }}</div>
                <div style="font-size:0.75rem; color:#8A9696;">{{ $source->url_or_domain }} &middot; {{ $source->mode }} &middot; {{ $source->status }}</div>
            </div>
            @if ($source->mode === 'fetch' && $source->status === 'approved')
            <form method="POST" action="{{ route('admin.sources.import', $source) }}">
                @csrf
                <input type="hidden" name="course_id" value="{{ $run->course_id }}">
                <button type="submit" style="padding:6px 12px; background:#55B1AE; color:white; border:none; border-radius:6px; font-size:0.75rem; font-weight:600; cursor:pointer;">Importa come documento</button>
            </form>
            @elseif ($source->status === 'suggested')
            <a href="{{ route('admin.sources.index', ['topic' => $source->topic]) }}" style="font-size:0.75rem; color:#8A9696;">Rivedi/approva in "Fonti attendibili"</a>
            @endif
        </div>
        @empty
        <p style="font-size:0.8rem; color:#8A9696;">Nessuna fonte esterna proposta ancora per questo argomento.</p>
        @endforelse
    </div>
    @endif
</div>
@endsection
