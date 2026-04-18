@extends('layouts.admin')
@section('title', 'Modifica Modulo')
@section('content')

<div style="max-width:900px;">
    <div style="margin-bottom:20px;">
        <a href="/admin/courses/{{ $course->id }}/modules" style="color:#8A9696; font-size:0.8rem;">
            &larr; {{ $course->name }}
        </a>
        <h2 style="font-size:1.25rem; font-weight:700; color:#1A1F1F; margin-top:4px;">
            Modifica: {{ $module->title }}
        </h2>
    </div>

    <form method="POST" action="/admin/courses/{{ $course->id }}/modules/{{ $module->id }}">
        @csrf @method('PUT')

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:20px;">
            <div style="background:white; border-radius:10px; padding:20px;">
                <h3 style="font-weight:700; color:#1A1F1F; margin-bottom:16px; font-size:0.9rem;">Info modulo</h3>
                <div style="display:flex; flex-direction:column; gap:12px;">
                    <div>
                        <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:4px;">Titolo *</label>
                        <input type="text" name="title" value="{{ $module->title }}" required
                               style="width:100%; padding:8px 12px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.875rem; outline:none;">
                    </div>
                    <div>
                        <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:4px;">Descrizione</label>
                        <textarea name="description" rows="3"
                                  style="width:100%; padding:8px 12px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.875rem; outline:none;">{{ $module->description }}</textarea>
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                        <div>
                            <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:4px;">Durata (min)</label>
                            <input type="number" name="duration_minutes" value="{{ $module->duration_minutes }}"
                                   style="width:100%; padding:8px 12px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.875rem; outline:none;">
                        </div>
                        <div>
                            <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:4px;">Ordine</label>
                            <input type="number" name="sort_order" value="{{ $module->sort_order }}"
                                   style="width:100%; padding:8px 12px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.875rem; outline:none;">
                        </div>
                    </div>
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                        <input type="checkbox" name="is_active" value="1" {{ $module->is_active ? 'checked' : '' }}>
                        <span style="font-size:0.875rem; color:#4A5252;">Modulo attivo</span>
                    </label>
                </div>
            </div>

            <div style="background:white; border-radius:10px; padding:20px;">
                <h3 style="font-weight:700; color:#1A1F1F; margin-bottom:16px; font-size:0.9rem;">Materiali ({{ $module->materials->count() }})</h3>
                @foreach($module->materials as $mat)
                <div style="display:flex; justify-content:space-between; align-items:center; padding:6px 0; border-bottom:1px solid #F5F7F7; font-size:0.8rem;">
                    <span style="color:#4A5252;">{{ \Illuminate\Support\Str::limit($mat->title, 35) }}</span>
                    <span style="color:#8A9696;">{{ strtoupper($mat->file_type) }}</span>
                </div>
                @endforeach
                <a href="/admin/courses/{{ $course->id }}/modules/{{ $module->id }}/materials/create"
                   style="display:block; text-align:center; margin-top:12px; padding:6px; background:#E8F5F5; color:#55B1AE; border-radius:6px; font-size:0.8rem; text-decoration:none; font-weight:600;">
                    + Aggiungi materiale
                </a>
            </div>
        </div>

        {{-- EDITOR TIPTAP COMPLETO --}}
        <div id="content" style="background:white; border-radius:10px; overflow:hidden; margin-bottom:20px;">
            <div style="padding:16px 20px; border-bottom:1px solid #E8F5F5;">
                <h3 style="font-weight:700; color:#1A1F1F; font-size:0.9rem;">Contenuto del modulo</h3>
            </div>

            <div id="toolbar" style="padding:8px 16px; background:#F5F7F7; border-bottom:1px solid #E8F5F5; display:flex; flex-wrap:wrap; gap:4px; align-items:center;">
                <button type="button" data-cmd="bold" title="Grassetto" style="padding:4px 8px; border:1px solid #C8D0D0; border-radius:4px; background:white; cursor:pointer; font-size:0.8rem; font-weight:bold;">B</button>
                <button type="button" data-cmd="italic" title="Corsivo" style="padding:4px 8px; border:1px solid #C8D0D0; border-radius:4px; background:white; cursor:pointer; font-size:0.8rem; font-style:italic;">I</button>
                <button type="button" data-cmd="underline" title="Sottolineato" style="padding:4px 8px; border:1px solid #C8D0D0; border-radius:4px; background:white; cursor:pointer; font-size:0.8rem; text-decoration:underline;">U</button>

                <div style="width:1px; height:20px; background:#C8D0D0; margin:0 4px;"></div>

                <button type="button" data-cmd="h2" style="padding:4px 8px; border:1px solid #C8D0D0; border-radius:4px; background:white; cursor:pointer; font-size:0.75rem; font-weight:bold;">H2</button>
                <button type="button" data-cmd="h3" style="padding:4px 8px; border:1px solid #C8D0D0; border-radius:4px; background:white; cursor:pointer; font-size:0.75rem; font-weight:bold;">H3</button>
                <button type="button" data-cmd="paragraph" style="padding:4px 8px; border:1px solid #C8D0D0; border-radius:4px; background:white; cursor:pointer; font-size:0.75rem;">P</button>

                <div style="width:1px; height:20px; background:#C8D0D0; margin:0 4px;"></div>

                <button type="button" data-cmd="bulletList" title="Lista puntata" style="padding:4px 8px; border:1px solid #C8D0D0; border-radius:4px; background:white; cursor:pointer; font-size:0.8rem;">&#8801;</button>
                <button type="button" data-cmd="orderedList" title="Lista numerata" style="padding:4px 8px; border:1px solid #C8D0D0; border-radius:4px; background:white; cursor:pointer; font-size:0.8rem;">1.</button>
                <button type="button" data-cmd="blockquote" title="Citazione" style="padding:4px 8px; border:1px solid #C8D0D0; border-radius:4px; background:white; cursor:pointer; font-size:0.8rem;">"</button>
                <button type="button" data-cmd="hardBreak" title="A capo" style="padding:4px 8px; border:1px solid #C8D0D0; border-radius:4px; background:white; cursor:pointer; font-size:0.75rem;">&crarr;</button>

                <div style="width:1px; height:20px; background:#C8D0D0; margin:0 4px;"></div>

                <button type="button" data-cmd="alignLeft" title="Allinea sinistra" style="padding:4px 8px; border:1px solid #C8D0D0; border-radius:4px; background:white; cursor:pointer; font-size:0.8rem;">&larr;</button>
                <button type="button" data-cmd="alignCenter" title="Centra" style="padding:4px 8px; border:1px solid #C8D0D0; border-radius:4px; background:white; cursor:pointer; font-size:0.8rem;">&harr;</button>
                <button type="button" data-cmd="alignRight" title="Allinea destra" style="padding:4px 8px; border:1px solid #C8D0D0; border-radius:4px; background:white; cursor:pointer; font-size:0.8rem;">&rarr;</button>

                <div style="width:1px; height:20px; background:#C8D0D0; margin:0 4px;"></div>

                <button type="button" id="insert-image-btn" title="Inserisci immagine" style="padding:4px 10px; border:1px solid #C8D0D0; border-radius:4px; background:white; cursor:pointer; font-size:0.8rem;">&#128247; Immagine</button>
                <input type="file" id="image-upload" accept="image/*" style="display:none;">

                <button type="button" data-cmd="link" title="Inserisci link" style="padding:4px 8px; border:1px solid #C8D0D0; border-radius:4px; background:white; cursor:pointer; font-size:0.8rem;">&#128279;</button>

                <div style="width:1px; height:20px; background:#C8D0D0; margin:0 4px;"></div>

                <button type="button" data-cmd="undo" style="padding:4px 8px; border:1px solid #C8D0D0; border-radius:4px; background:white; cursor:pointer; font-size:0.8rem;">&#8630;</button>
                <button type="button" data-cmd="redo" style="padding:4px 8px; border:1px solid #C8D0D0; border-radius:4px; background:white; cursor:pointer; font-size:0.8rem;">&#8631;</button>

                <div style="margin-left:auto;">
                    <button type="button" id="toggle-source" style="padding:4px 12px; border:1px solid #55B1AE; border-radius:4px; background:white; color:#55B1AE; cursor:pointer; font-size:0.75rem;">
                        &lt;/&gt; HTML
                    </button>
                </div>
            </div>

            <div id="editor-container" style="min-height:500px; padding:0;">
                <div id="tiptap-editor" style="min-height:500px; padding:24px; outline:none; font-family:'Calibri',system-ui,sans-serif; font-size:0.95rem; line-height:1.8; color:#1A1F1F;"></div>
            </div>

            <div id="source-container" style="display:none;">
                <textarea id="source-editor" style="width:100%; min-height:500px; padding:16px; border:none; outline:none; font-family:monospace; font-size:0.8rem; line-height:1.6; resize:vertical; color:#1A1F1F;"></textarea>
            </div>

            <input type="hidden" name="content" id="content-hidden" value="{{ $module->content }}">
        </div>

        <style>
        #tiptap-editor:focus { outline: none; }
        #tiptap-editor h2 { font-size:1.3rem; font-weight:700; color:#1A1F1F; margin:1.5rem 0 0.75rem; border-bottom:2px solid #E8F5F5; padding-bottom:0.4rem; }
        #tiptap-editor h3 { font-size:1.05rem; font-weight:700; color:#3A8C89; margin:1.2rem 0 0.5rem; }
        #tiptap-editor p { margin:0.6rem 0; }
        #tiptap-editor ul { margin:0.5rem 0 0.5rem 1.5rem; list-style:disc; }
        #tiptap-editor ol { margin:0.5rem 0 0.5rem 1.5rem; list-style:decimal; }
        #tiptap-editor li { margin:0.3rem 0; }
        #tiptap-editor blockquote { margin:1rem 0; padding:0.75rem 1.25rem; background:#E8F5F5; border-left:4px solid #55B1AE; border-radius:0 8px 8px 0; color:#3A8C89; font-style:italic; }
        #tiptap-editor img { max-width:100%; border-radius:8px; margin:0.5rem 0; cursor:pointer; }
        #tiptap-editor img.selected { outline:3px solid #55B1AE; }
        #tiptap-editor a { color:#55B1AE; text-decoration:underline; }
        #tiptap-editor [style*="text-align: center"] { text-align:center; }
        #tiptap-editor [style*="text-align: right"] { text-align:right; }
        .toolbar-btn-active { background:#E8F5F5 !important; color:#55B1AE !important; border-color:#55B1AE !important; }
        </style>

        <div style="display:flex; gap:12px; justify-content:flex-end;">
            <a href="/admin/courses/{{ $course->id }}/modules"
               style="padding:10px 20px; border:1px solid #C8D0D0; color:#4A5252; border-radius:8px; font-size:0.875rem; text-decoration:none;">
                Annulla
            </a>
            <button type="submit"
                    style="padding:10px 24px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.875rem; font-weight:700; cursor:pointer;">
                Salva modifiche
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script type="module">
import { Editor } from 'https://esm.sh/@tiptap/core@2.1.13'
import StarterKit from 'https://esm.sh/@tiptap/starter-kit@2.1.13'
import Image from 'https://esm.sh/@tiptap/extension-image@2.1.13'
import TextAlign from 'https://esm.sh/@tiptap/extension-text-align@2.1.13'
import Link from 'https://esm.sh/@tiptap/extension-link@2.1.13'
import Underline from 'https://esm.sh/@tiptap/extension-underline@2.1.13'

const initialContent = document.getElementById('content-hidden').value || '<p></p>';

const editor = new Editor({
    element: document.getElementById('tiptap-editor'),
    extensions: [
        StarterKit.configure({ hardBreak: { keepMarks: true } }),
        Image.configure({ inline: false, allowBase64: true }),
        TextAlign.configure({ types: ['heading', 'paragraph'] }),
        Link.configure({ openOnClick: false }),
        Underline,
    ],
    content: initialContent,
    editorProps: {
        attributes: { style: 'min-height:500px; padding:24px; outline:none;' }
    },
    onUpdate({ editor }) {
        document.getElementById('content-hidden').value = editor.getHTML();
    }
});

document.querySelectorAll('[data-cmd]').forEach(btn => {
    btn.addEventListener('click', () => {
        const cmd = btn.dataset.cmd;
        const chain = editor.chain().focus();
        if (cmd === 'bold') chain.toggleBold().run();
        else if (cmd === 'italic') chain.toggleItalic().run();
        else if (cmd === 'underline') chain.toggleUnderline().run();
        else if (cmd === 'h2') chain.toggleHeading({ level: 2 }).run();
        else if (cmd === 'h3') chain.toggleHeading({ level: 3 }).run();
        else if (cmd === 'paragraph') chain.setParagraph().run();
        else if (cmd === 'bulletList') chain.toggleBulletList().run();
        else if (cmd === 'orderedList') chain.toggleOrderedList().run();
        else if (cmd === 'blockquote') chain.toggleBlockquote().run();
        else if (cmd === 'hardBreak') chain.setHardBreak().run();
        else if (cmd === 'alignLeft') chain.setTextAlign('left').run();
        else if (cmd === 'alignCenter') chain.setTextAlign('center').run();
        else if (cmd === 'alignRight') chain.setTextAlign('right').run();
        else if (cmd === 'undo') chain.undo().run();
        else if (cmd === 'redo') chain.redo().run();
        else if (cmd === 'link') {
            const url = prompt('URL del link:');
            if (url) chain.setLink({ href: url }).run();
        }
        updateToolbarState();
    });
});

function updateToolbarState() {
    document.querySelectorAll('[data-cmd]').forEach(btn => {
        const cmd = btn.dataset.cmd;
        let active = false;
        if (cmd === 'bold') active = editor.isActive('bold');
        else if (cmd === 'italic') active = editor.isActive('italic');
        else if (cmd === 'underline') active = editor.isActive('underline');
        else if (cmd === 'h2') active = editor.isActive('heading', { level: 2 });
        else if (cmd === 'h3') active = editor.isActive('heading', { level: 3 });
        else if (cmd === 'bulletList') active = editor.isActive('bulletList');
        else if (cmd === 'orderedList') active = editor.isActive('orderedList');
        else if (cmd === 'blockquote') active = editor.isActive('blockquote');
        else if (cmd === 'alignLeft') active = editor.isActive({ textAlign: 'left' });
        else if (cmd === 'alignCenter') active = editor.isActive({ textAlign: 'center' });
        else if (cmd === 'alignRight') active = editor.isActive({ textAlign: 'right' });
        btn.classList.toggle('toolbar-btn-active', active);
    });
}

editor.on('selectionUpdate', updateToolbarState);
editor.on('transaction', updateToolbarState);

document.getElementById('insert-image-btn').addEventListener('click', () => {
    document.getElementById('image-upload').click();
});

document.getElementById('image-upload').addEventListener('change', async (e) => {
    const file = e.target.files[0];
    if (!file) return;
    const formData = new FormData();
    formData.append('image', file);
    formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
    try {
        const res = await fetch('/admin/upload-image', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.url) {
            editor.chain().focus().setImage({ src: data.url }).run();
        } else {
            throw new Error('no url');
        }
    } catch(err) {
        const reader = new FileReader();
        reader.onload = (ev) => editor.chain().focus().setImage({ src: ev.target.result }).run();
        reader.readAsDataURL(file);
    }
    e.target.value = '';
});

let sourceMode = false;
document.getElementById('toggle-source').addEventListener('click', () => {
    sourceMode = !sourceMode;
    const editorContainer = document.getElementById('editor-container');
    const sourceContainer = document.getElementById('source-container');
    const sourceEditor = document.getElementById('source-editor');
    if (sourceMode) {
        sourceEditor.value = editor.getHTML();
        editorContainer.style.display = 'none';
        sourceContainer.style.display = 'block';
        document.getElementById('toggle-source').textContent = 'Visual';
    } else {
        editor.commands.setContent(sourceEditor.value);
        document.getElementById('content-hidden').value = sourceEditor.value;
        editorContainer.style.display = 'block';
        sourceContainer.style.display = 'none';
        document.getElementById('toggle-source').textContent = '</> HTML';
    }
});

document.getElementById('source-editor').addEventListener('input', (e) => {
    document.getElementById('content-hidden').value = e.target.value;
});
</script>
@endpush
@endsection
