@extends('layouts.admin')
@section('title', 'Documenti AI (RAG)')
@section('content')

<div style="display:grid; grid-template-columns:1fr 1fr; gap:24px;">

    <div style="background:white; border-radius:10px; padding:24px;">
        <h3 style="font-weight:700; color:#1A1F1F; margin-bottom:16px;">Carica documento</h3>
        <form method="POST" action="/rag/upload" enctype="multipart/form-data">
            @csrf
            <div style="display:flex; flex-direction:column; gap:14px;">
                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Titolo (opzionale)</label>
                    <input type="text" name="title" placeholder="Lascia vuoto per usare il nome del file"
                           style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
                </div>
                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Corso *</label>
                    <select name="course_id" required
                            style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
                        <option value="">— Seleziona corso —</option>
                        @foreach($courses as $course)
                        <option value="{{ $course->id }}" @selected($selectedCourseId === $course->id)>{{ $course->icon }} {{ $course->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">File (PDF, DOCX, TXT, PPTX, video MP4/MOV/AVI/WEBM) *</label>
                    <input type="file" name="files[]" accept=".pdf,.doc,.docx,.txt,.pptx,.mp4,.mov,.avi,.webm" required multiple
                           style="width:100%; padding:10px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem;">
                    <p style="font-size:0.75rem; color:#8A9696; margin-top:4px;">
                        Puoi selezionare piu file tenendo premuto Cmd (Mac) o Ctrl (Windows). I video vengono trascritti in automatico e compaiono nell'elenco dopo qualche minuto.
                    </p>
                </div>
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer; padding:10px; background:#E8F5F5; border-radius:8px;">
                    <input type="checkbox" name="generate_quiz" value="1">
                    <div>
                        <div style="font-size:0.875rem; font-weight:600; color:#3A8C89;">&#10022; Genera quiz con AI</div>
                        <div style="font-size:0.75rem; color:#8A9696;">Claude creera automaticamente 10 domande dal documento</div>
                    </div>
                </label>
                <button type="submit" style="padding:10px; background:#55B1AE; color:white; border:none; border-radius:8px; font-weight:700; cursor:pointer;">
                    Indicizza documento
                </button>
            </div>
        </form>
    </div>

    <div style="background:white; border-radius:10px; padding:24px;">
        <h3 style="font-weight:700; color:#1A1F1F; margin-bottom:16px;">Documenti indicizzati ({{ $documents->total() }})</h3>
        <div style="display:flex; flex-direction:column; gap:8px; max-height:500px; overflow-y:auto;">
            @foreach($documents as $doc)
            <div style="display:flex; justify-content:space-between; align-items:center; padding:10px; background:#F5F7F7; border-radius:8px;">
                <div>
                    <div style="font-size:0.85rem; font-weight:600; color:#1A1F1F;">{{ \Illuminate\Support\Str::limit($doc->title, 40) }}</div>
                    <div style="font-size:0.75rem; color:#8A9696;">
                        {{ $doc->course?->name ?? '—' }} &middot; chunk {{ $doc->chunk_index }} &middot; {{ $doc->created_at?->format('d/m/Y') }}
                    </div>
                </div>
                <form method="POST" action="/rag/{{ $doc->id }}">
                    @csrf @method('DELETE')
                    <button type="submit" style="padding:4px 10px; background:#fff3ec; color:#E28A53; border:1px solid #E28A53; border-radius:6px; font-size:0.75rem; cursor:pointer;"
                            onclick="return confirm('Eliminare?')">
                        Rimuovi
                    </button>
                </form>
            </div>
            @endforeach
        </div>
    </div>
</div>

@if($selectedCourseId)
<div style="background:white; border-radius:10px; padding:24px; margin-top:24px;">
    <h3 style="font-weight:700; color:#1A1F1F; margin-bottom:4px;">Riusa un documento già caricato su un altro corso</h3>
    <p style="font-size:0.8rem; color:#8A9696; margin-bottom:14px;">
        Invece di ricaricare lo stesso file, aggiungilo direttamente a questo corso.
    </p>

    @if($existingElsewhere->isEmpty())
        <p style="font-size:0.82rem; color:#8A9696;">Nessun documento presente su altri corsi.</p>
    @else
        <input type="text" id="existing-elsewhere-filter" placeholder="Filtra per titolo o corso..."
               oninput="document.querySelectorAll('[data-existing-row]').forEach(function(row){
                   row.style.display = row.dataset.search.includes(this.value.toLowerCase()) ? '' : 'none';
               }.bind(this))"
               style="width:100%; padding:8px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.85rem; margin-bottom:12px;">

        <div style="display:flex; flex-direction:column; gap:6px; max-height:320px; overflow-y:auto;">
            @foreach($existingElsewhere as $item)
            <div data-existing-row data-search="{{ \Illuminate\Support\Str::lower($item['title'] . ' ' . $item['course_name']) }}"
                 style="display:flex; justify-content:space-between; align-items:center; padding:8px 10px; background:#F5F7F7; border-radius:8px;">
                <div>
                    <div style="font-size:0.83rem; font-weight:600; color:#1A1F1F;">{{ \Illuminate\Support\Str::limit($item['title'], 50) }}</div>
                    <div style="font-size:0.72rem; color:#8A9696;">da: {{ $item['course_name'] }}</div>
                </div>
                <form method="POST" action="{{ route('admin.rag.attach') }}">
                    @csrf
                    <input type="hidden" name="source_course_id" value="{{ $item['course_id'] }}">
                    <input type="hidden" name="title" value="{{ $item['title'] }}">
                    <input type="hidden" name="target_course_id" value="{{ $selectedCourseId }}">
                    <button type="submit" style="padding:5px 12px; background:#55B1AE; color:white; border:none; border-radius:6px; font-size:0.75rem; font-weight:600; cursor:pointer;">
                        Aggiungi a questo corso
                    </button>
                </form>
            </div>
            @endforeach
        </div>
    @endif
</div>
@endif

@endsection
