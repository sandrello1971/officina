@extends('layouts.admin')

@section('title', "Modifica contenuto: {$material->title}")

@section('content')
<div style="max-width:1200px; margin:0 auto;">

    @if(session('success'))
    <div style="padding:10px 14px; background:rgba(85,177,174,0.12);
                border:1px solid rgba(85,177,174,0.4); border-radius:8px;
                color:#3A8C89; margin-bottom:14px; font-size:0.85rem;">
        ✅ {{ session('success') }}
    </div>
    @endif

    @if(session('error'))
    <div style="padding:10px 14px; background:rgba(226,82,82,0.12);
                border:1px solid rgba(226,82,82,0.4); border-radius:8px;
                color:#C52A2A; margin-bottom:14px; font-size:0.85rem;">
        ⚠️ {{ session('error') }}
    </div>
    @endif

    <div style="margin-bottom:16px; font-size:0.85rem; color:#8A9696;">
        <a href="{{ route('admin.courses.edit', $course->id) }}"
           style="color:#3A8C89; text-decoration:none;">← Corso: {{ $course->name }}</a>
    </div>

    <div style="background:linear-gradient(135deg, rgba(226,138,83,0.08), rgba(226,138,83,0.12));
                border:1px solid rgba(226,138,83,0.3);
                border-radius:12px; padding:20px; margin-bottom:20px;">
        <div style="display:flex; align-items:center; gap:10px; margin-bottom:8px;">
            <div style="font-size:1.4rem;">✏️</div>
            <div style="font-weight:700; color:#1A1F1F; font-size:1.1rem;">
                Modifica contenuto del manuale formatore
            </div>
            <div style="margin-left:auto; padding:4px 12px;
                        background:rgba(226,138,83,0.2); color:#D87840;
                        border-radius:12px; font-size:0.7rem; font-weight:700;">
                {{ $material->title }}
            </div>
        </div>
        <p style="font-size:0.8rem; color:#5A6464; margin:0 0 6px;">
            Modifica l'HTML del manuale (ciò che i formatori leggono a schermo). Salvando,
            le <strong>sezioni vengono ri-derivate</strong> automaticamente (le mappature-modulo
            manuali sono preservate) e il <strong>RAG re-indicizzato</strong>.
        </p>
        <p style="font-size:0.75rem; color:#B26A3A; margin:0;">
            ⚠️ Il file <code>.docx</code> sorgente NON viene toccato: un successivo "Aggiorna"
            o "Rigenera HTML" da quel file sovrascriverà queste modifiche. Eventuali aggiornamenti
            Freshness applicati solo alle sezioni verranno ri-derivati dal testo qui salvato.
        </p>
    </div>

    <form method="POST"
          action="{{ route('admin.courses.instructor-materials.content.update', [$course->id, $material->id]) }}"
          id="content-form">
        @csrf
        @method('PUT')

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
            <div>
                <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">
                    HTML del manuale
                </label>
                <textarea name="content_html" id="content_html" spellcheck="false"
                          style="width:100%; height:520px; padding:12px 14px; border:1px solid #C8D0D0;
                                 border-radius:8px; font-family:ui-monospace,SFMono-Regular,Menlo,monospace;
                                 font-size:0.8rem; line-height:1.5; outline:none; resize:vertical;">{{ old('content_html', $material->content_html) }}</textarea>
            </div>
            <div>
                <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">
                    Anteprima
                </label>
                <div id="preview"
                     style="width:100%; height:520px; overflow:auto; padding:16px 18px; border:1px solid #E8F5F5;
                            border-radius:8px; background:white; font-size:0.9rem; line-height:1.6; color:#1A1F1F;"></div>
            </div>
        </div>

        <div style="display:flex; gap:12px; justify-content:space-between; align-items:center; flex-wrap:wrap;">
            <a href="{{ route('admin.courses.instructor-materials.sections', [$course->id, $material->id]) }}"
               style="padding:10px 18px; color:#5A6464; text-decoration:none; font-size:0.85rem; font-weight:600;">
                📑 Gestisci sezioni
            </a>
            <button type="submit" id="save-btn"
                    style="padding:10px 24px; background:#55B1AE; color:white; border:none; border-radius:8px;
                           font-size:0.875rem; font-weight:700; cursor:pointer;">
                Salva contenuto
            </button>
        </div>
    </form>
</div>

<script>
(function () {
    var ta = document.getElementById('content_html');
    var preview = document.getElementById('preview');
    var form = document.getElementById('content-form');
    var btn = document.getElementById('save-btn');

    function render() { preview.innerHTML = ta.value; }
    ta.addEventListener('input', render);
    render();

    // Guardia doppio-submit + feedback immediato (regola UX).
    form.addEventListener('submit', function () {
        btn.disabled = true;
        btn.style.opacity = '0.6';
        btn.style.cursor = 'wait';
        btn.textContent = 'Salvataggio…';
    });
})();
</script>
@endsection
