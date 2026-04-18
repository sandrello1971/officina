@extends('layouts.admin')
@section('title', 'Aggiungi materiale')
@section('content')

<div style="max-width:700px;">
    <div style="margin-bottom:20px;">
        <a href="{{ route('admin.courses.modules.edit', [$course, $module]) }}"
           style="color:#8A9696; font-size:0.8rem;">← {{ $course->name }} › {{ $module->title }}</a>
        <h1 style="font-size:1.25rem; font-weight:700; color:#1A1F1F; margin-top:6px;">Aggiungi materiale</h1>
    </div>

    @if($errors->any())
    <div style="padding:12px 16px; background:#fff3ec; border-left:4px solid #E28A53; border-radius:6px; margin-bottom:16px; color:#c97a45; font-size:0.875rem;">
        {{ $errors->first() }}
    </div>
    @endif

    <div style="background:white; border-radius:12px; padding:24px;">
        <form method="POST"
              action="{{ route('admin.courses.modules.materials.store', [$course, $module]) }}"
              enctype="multipart/form-data">
            @csrf

            <div style="margin-bottom:20px;">
                <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:8px;">Tipo di materiale *</label>
                <div style="display:flex; gap:10px;">
                    <label style="display:flex; align-items:center; gap:8px; padding:10px 16px; border:2px solid #C8D0D0; border-radius:8px; cursor:pointer; flex:1;"
                           onclick="showType('file')" id="tab-file" class="type-tab">
                        <input type="radio" name="type" value="file" checked style="display:none;">
                        <span style="font-size:1.2rem;">📄</span>
                        <span style="font-size:0.875rem; font-weight:600; color:#1A1F1F;">Documento PDF</span>
                    </label>
                    <label style="display:flex; align-items:center; gap:8px; padding:10px 16px; border:2px solid #C8D0D0; border-radius:8px; cursor:pointer; flex:1;"
                           onclick="showType('video')" id="tab-video" class="type-tab">
                        <input type="radio" name="type" value="video" style="display:none;">
                        <span style="font-size:1.2rem;">🎬</span>
                        <span style="font-size:0.875rem; font-weight:600; color:#1A1F1F;">Video</span>
                    </label>
                    <label style="display:flex; align-items:center; gap:8px; padding:10px 16px; border:2px solid #C8D0D0; border-radius:8px; cursor:pointer; flex:1;"
                           onclick="showType('url')" id="tab-url" class="type-tab">
                        <input type="radio" name="type" value="url" style="display:none;">
                        <span style="font-size:1.2rem;">🔗</span>
                        <span style="font-size:0.875rem; font-weight:600; color:#1A1F1F;">Link esterno</span>
                    </label>
                </div>
            </div>

            <div style="margin-bottom:16px;">
                <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Titolo *</label>
                <input type="text" name="title" required value="{{ old('title') }}"
                       placeholder="Es: Manuale del discente"
                       style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
            </div>

            <div style="margin-bottom:16px;">
                <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Descrizione</label>
                <input type="text" name="description" value="{{ old('description') }}"
                       placeholder="Breve descrizione opzionale"
                       style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
            </div>

            <div id="section-file" style="margin-bottom:16px;">
                <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">
                    File (PDF, Word, Excel, PowerPoint — max 200MB)
                </label>
                <input type="file" name="file" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt"
                       style="width:100%; padding:10px; border:1px dashed #C8D0D0; border-radius:8px; font-size:0.875rem; color:#4A5252;">
            </div>

            <div id="section-video" style="display:none; margin-bottom:16px;">
                <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">
                    Video (MP4, MOV, AVI, WebM — max 2GB)
                </label>
                <input type="file" name="video_file" accept="video/*"
                       style="width:100%; padding:10px; border:1px dashed #C8D0D0; border-radius:8px; font-size:0.875rem; color:#4A5252;">
                <p style="color:#8A9696; font-size:0.75rem; margin-top:6px;">
                    Il video verrà trascritto automaticamente con AI e sarà disponibile nel modulo con chat e trascrizione sincronizzata.
                </p>
            </div>

            <div id="section-url" style="display:none; margin-bottom:16px;">
                <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">URL esterno</label>
                <input type="url" name="url" value="{{ old('url') }}"
                       placeholder="https://..."
                       style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
            </div>

            <div style="display:flex; gap:12px; justify-content:flex-end; margin-top:24px;">
                <a href="{{ route('admin.courses.modules.edit', [$course, $module]) }}"
                   style="padding:10px 20px; border:1px solid #C8D0D0; color:#4A5252; border-radius:8px; font-size:0.875rem; text-decoration:none;">
                    Annulla
                </a>
                <button type="submit"
                        style="padding:10px 24px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.875rem; font-weight:700; cursor:pointer;">
                    Carica materiale
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showType(type) {
    ['file','video','url'].forEach(t => {
        document.getElementById('section-' + t).style.display = t === type ? 'block' : 'none';
        document.getElementById('tab-' + t).style.borderColor = t === type ? '#55B1AE' : '#C8D0D0';
    });
    document.querySelector('input[name=type][value=' + type + ']').checked = true;
}
document.getElementById('tab-file').style.borderColor = '#55B1AE';
</script>
@endsection
