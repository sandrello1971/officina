<div x-data="blogEditor()" class="min-h-screen" style="background:#F5F7F7">

    {{-- TOP BAR --}}
    <div class="sticky top-0 z-40 px-6 py-3 flex items-center justify-between shadow-sm" style="background:white;border-bottom:1px solid #C8D0D0">
        <div class="flex items-center gap-4">
            <a href="/nosciteadmin" class="text-sm" style="color:#8A9696">&larr; Dashboard</a>
            <span style="color:#C8D0D0">|</span>
            <span class="text-sm font-semibold" style="color:#1A1F1F">
                {{ $postId ? 'Modifica articolo' : 'Nuovo articolo' }}
            </span>
        </div>
        <div class="flex items-center gap-3">
            @if($postId)
            <a href="/commentarium/{{ $slug }}" target="_blank" class="text-sm px-3 py-1 rounded border" style="border-color:#C8D0D0;color:#4A5252">
                Anteprima &rarr;
            </a>
            @endif
            <div class="flex items-center gap-2">
                <input type="checkbox" wire:model="published" id="pub" class="w-4 h-4">
                <label for="pub" class="text-sm font-medium" style="color:#1A1F1F">Pubblicato</label>
            </div>
            <button wire:click="save" class="px-5 py-2 rounded-lg text-sm font-semibold text-white transition-colors" style="background:#55B1AE" onmouseover="this.style.background='#3A8C89'" onmouseout="this.style.background='#55B1AE'">
                <span wire:loading.remove wire:target="save">Salva</span>
                <span wire:loading wire:target="save">Salvataggio...</span>
            </button>
        </div>
    </div>

    @if($saved)
    <div class="mx-6 mt-4 p-3 rounded-lg text-sm" style="background:#E8F5F5;color:#3A8C89">
        &#10003; Articolo salvato con successo!
        <button wire:click="$set('saved', false)" class="ml-2 text-xs">&#10005;</button>
    </div>
    @endif

    <div class="max-w-5xl mx-auto px-6 py-8 grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- COLONNA PRINCIPALE --}}
        <div class="lg:col-span-2 flex flex-col gap-4">

            {{-- TITOLO --}}
            <div class="p-4 rounded-xl bg-white shadow-sm">
                <input type="text" wire:model.live="title" placeholder="Titolo dell'articolo..." class="w-full text-2xl font-bold border-none outline-none" style="color:#1A1F1F">
                <div class="flex items-center gap-2 mt-2">
                    <span class="text-xs" style="color:#8A9696">Slug:</span>
                    <input type="text" wire:model="slug" class="text-xs border-none outline-none flex-1" style="color:#55B1AE">
                </div>
                @error('title') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- EDITOR RICH TEXT --}}
            <div class="rounded-xl bg-white shadow-sm overflow-hidden">
                {{-- TOOLBAR --}}
                <div class="px-4 py-2 border-b flex flex-wrap gap-1" style="border-color:#E8F5F5">
                    <button type="button" @click="editor.chain().focus().toggleBold().run()" :class="{'ring-2 ring-teal-400': editor && editor.isActive('bold')}" class="p-1 rounded text-sm font-bold w-7 h-7 flex items-center justify-center hover:bg-gray-100"><strong>B</strong></button>
                    <button type="button" @click="editor.chain().focus().toggleItalic().run()" :class="{'ring-2 ring-teal-400': editor && editor.isActive('italic')}" class="p-1 rounded text-sm italic w-7 h-7 flex items-center justify-center hover:bg-gray-100"><em>I</em></button>
                    <button type="button" @click="editor.chain().focus().toggleUnderline().run()" :class="{'ring-2 ring-teal-400': editor && editor.isActive('underline')}" class="p-1 rounded text-sm underline w-7 h-7 flex items-center justify-center hover:bg-gray-100">U</button>
                    <div class="w-px mx-1" style="background:#C8D0D0"></div>
                    <button type="button" @click="editor.chain().focus().toggleHeading({level:2}).run()" :class="{'ring-2 ring-teal-400': editor && editor.isActive('heading',{level:2})}" class="p-1 rounded text-xs font-bold px-2 h-7 flex items-center justify-center hover:bg-gray-100">H2</button>
                    <button type="button" @click="editor.chain().focus().toggleHeading({level:3}).run()" :class="{'ring-2 ring-teal-400': editor && editor.isActive('heading',{level:3})}" class="p-1 rounded text-xs font-bold px-2 h-7 flex items-center justify-center hover:bg-gray-100">H3</button>
                    <div class="w-px mx-1" style="background:#C8D0D0"></div>
                    <button type="button" @click="editor.chain().focus().toggleBulletList().run()" :class="{'ring-2 ring-teal-400': editor && editor.isActive('bulletList')}" class="p-1 rounded text-xs px-2 h-7 flex items-center justify-center hover:bg-gray-100">&#8801;</button>
                    <button type="button" @click="editor.chain().focus().toggleOrderedList().run()" :class="{'ring-2 ring-teal-400': editor && editor.isActive('orderedList')}" class="p-1 rounded text-xs px-2 h-7 flex items-center justify-center hover:bg-gray-100">1.</button>
                    <button type="button" @click="editor.chain().focus().toggleBlockquote().run()" :class="{'ring-2 ring-teal-400': editor && editor.isActive('blockquote')}" class="p-1 rounded text-xs px-2 h-7 flex items-center justify-center hover:bg-gray-100">"</button>
                    <div class="w-px mx-1" style="background:#C8D0D0"></div>
                    <button type="button" @click="editor.chain().focus().setTextAlign('left').run()" class="p-1 rounded text-xs px-2 h-7 flex items-center justify-center hover:bg-gray-100">&larr;</button>
                    <button type="button" @click="editor.chain().focus().setTextAlign('center').run()" class="p-1 rounded text-xs px-2 h-7 flex items-center justify-center hover:bg-gray-100">&harr;</button>
                    <button type="button" @click="editor.chain().focus().setTextAlign('right').run()" class="p-1 rounded text-xs px-2 h-7 flex items-center justify-center hover:bg-gray-100">&rarr;</button>
                    <div class="w-px mx-1" style="background:#C8D0D0"></div>
                    <button type="button" @click="$refs.inlineImageInput.click()" class="p-1 rounded text-xs px-2 h-7 flex items-center justify-center hover:bg-gray-100" title="Inserisci immagine">&#128247;</button>
                    <button type="button" @click="addLink()" :class="{'ring-2 ring-teal-400': editor && editor.isActive('link')}" class="p-1 rounded text-xs px-2 h-7 flex items-center justify-center hover:bg-gray-100">&#128279;</button>
                    <button type="button" @click="editor.chain().focus().unsetLink().run()" x-show="editor && editor.isActive('link')" class="p-1 rounded text-xs px-2 h-7 flex items-center justify-center hover:bg-gray-100">&#10005;&#128279;</button>
                    <div class="w-px mx-1" style="background:#C8D0D0"></div>
                    <button type="button" @click="editor.chain().focus().undo().run()" class="p-1 rounded text-xs px-2 h-7 flex items-center justify-center hover:bg-gray-100">&#8630;</button>
                    <button type="button" @click="editor.chain().focus().redo().run()" class="p-1 rounded text-xs px-2 h-7 flex items-center justify-center hover:bg-gray-100">&#8631;</button>
                </div>

                {{-- AREA EDITOR --}}
                <div x-ref="editorEl" class="p-4 min-h-96 prose max-w-none" style="outline:none;color:#1A1F1F;line-height:1.8"></div>

                {{-- Input nascosto per immagini inline --}}
                <input type="file" x-ref="inlineImageInput" accept="image/*" class="hidden" @change="insertInlineImage($event)">

                {{-- Campo hidden per sync con Livewire --}}
                <input type="hidden" wire:model="content" x-ref="contentField">
            </div>

            @error('content') <p class="text-red-500 text-xs">{{ $message }}</p> @enderror
        </div>

        {{-- COLONNA LATERALE --}}
        <div class="flex flex-col gap-4">

            {{-- IMMAGINE COPERTINA --}}
            <div class="p-4 rounded-xl bg-white shadow-sm">
                <h3 class="text-sm font-bold mb-3" style="color:#1A1F1F">Immagine copertina</h3>
                @if($cover_image_url)
                <img src="{{ $cover_image_url }}" class="w-full h-32 object-cover rounded-lg mb-3">
                @endif
                <label class="block w-full cursor-pointer">
                    <div class="border-2 border-dashed rounded-lg p-4 text-center transition-colors" style="border-color:#C8D0D0" onmouseover="this.style.borderColor='#55B1AE'" onmouseout="this.style.borderColor='#C8D0D0'">
                        <div class="text-2xl mb-1">&#128247;</div>
                        <p class="text-xs" style="color:#8A9696">Clicca per caricare<br>JPG, PNG, WebP — max 5MB</p>
                    </div>
                    <input type="file" wire:model="cover_image" accept="image/*" class="hidden">
                </label>
                @if($cover_image)
                <p class="text-xs mt-2" style="color:#55B1AE">&#10003; Immagine selezionata</p>
                @endif
                <div class="mt-3">
                    <label class="text-xs font-medium" style="color:#4A5252">Oppure URL esterno</label>
                    <input type="text" wire:model="cover_image_url" placeholder="https://..." class="w-full mt-1 px-2 py-1 text-xs border rounded" style="border-color:#C8D0D0">
                </div>
            </div>

            {{-- CATEGORIA E DATA --}}
            <div class="p-4 rounded-xl bg-white shadow-sm">
                <h3 class="text-sm font-bold mb-3" style="color:#1A1F1F">Dettagli</h3>
                <div class="flex flex-col gap-3">
                    <div>
                        <label class="text-xs font-medium" style="color:#4A5252">Categoria</label>
                        <input type="text" wire:model="category" placeholder="Es: AI, Formazione..." class="w-full mt-1 px-2 py-1 text-xs border rounded" style="border-color:#C8D0D0">
                    </div>
                    <div>
                        <label class="text-xs font-medium" style="color:#4A5252">Data pubblicazione</label>
                        <input type="datetime-local" wire:model="published_at" class="w-full mt-1 px-2 py-1 text-xs border rounded" style="border-color:#C8D0D0">
                    </div>
                    <div>
                        <label class="text-xs font-medium" style="color:#4A5252">Estratto</label>
                        <textarea wire:model="excerpt" rows="3" placeholder="Breve descrizione dell'articolo..." class="w-full mt-1 px-2 py-1 text-xs border rounded" style="border-color:#C8D0D0"></textarea>
                    </div>
                </div>
            </div>

            {{-- SEO --}}
            <div class="p-4 rounded-xl bg-white shadow-sm">
                <h3 class="text-sm font-bold mb-3" style="color:#1A1F1F">SEO</h3>
                <div class="flex flex-col gap-3">
                    <div>
                        <label class="text-xs font-medium" style="color:#4A5252">Meta title</label>
                        <input type="text" wire:model="meta_title" class="w-full mt-1 px-2 py-1 text-xs border rounded" style="border-color:#C8D0D0">
                        <p class="text-xs mt-1" style="color:#8A9696">{{ strlen($meta_title) }}/60</p>
                    </div>
                    <div>
                        <label class="text-xs font-medium" style="color:#4A5252">Meta description</label>
                        <textarea wire:model="meta_description" rows="3" class="w-full mt-1 px-2 py-1 text-xs border rounded" style="border-color:#C8D0D0"></textarea>
                        <p class="text-xs mt-1" style="color:#8A9696">{{ strlen($meta_description) }}/160</p>
                    </div>
                </div>
            </div>

            {{-- GENERA CON AI --}}
            <livewire:admin.ai-post-generator />
        </div>
    </div>
</div>

@push('scripts')
<script type="module">
import { Editor } from 'https://esm.sh/@tiptap/core@2.1.13'
import StarterKit from 'https://esm.sh/@tiptap/starter-kit@2.1.13'
import Image from 'https://esm.sh/@tiptap/extension-image@2.1.13'
import TextAlign from 'https://esm.sh/@tiptap/extension-text-align@2.1.13'
import Link from 'https://esm.sh/@tiptap/extension-link@2.1.13'
import Underline from 'https://esm.sh/@tiptap/extension-underline@2.1.13'

window.blogEditor = function() {
    return {
        editor: null,

        init() {
            const initialContent = @json($content);

            this.editor = new Editor({
                element: this.$refs.editorEl,
                extensions: [
                    StarterKit,
                    Image.configure({ inline: true, allowBase64: true }),
                    TextAlign.configure({ types: ['heading', 'paragraph', 'image'] }),
                    Link.configure({ openOnClick: false }),
                    Underline,
                ],
                content: initialContent || '',
                editorProps: {
                    attributes: {
                        class: 'outline-none min-h-96',
                        style: 'min-height:400px'
                    }
                },
                onUpdate: ({ editor }) => {
                    const html = editor.getHTML();
                    this.$refs.contentField.value = html;
                    @this.set('content', html);
                }
            });

            window.currentEditor = this.editor;

            window.addEventListener('ai-content-generated', (event) => {
                const d = event.detail;
                if (window.currentEditor) {
                    window.currentEditor.commands.setContent(d.content);
                }
                @this.set('title', d.title);
                @this.set('slug', d.slug);
                @this.set('content', d.content);
                @this.set('excerpt', d.excerpt);
                @this.set('meta_title', d.meta_title);
                @this.set('meta_description', d.meta_description);
                @this.set('category', d.category);
            });
        },

        addLink() {
            const url = prompt('URL del link:');
            if (url) {
                this.editor.chain().focus().setLink({ href: url }).run();
            }
        },

        async insertInlineImage(event) {
            const file = event.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = (e) => {
                this.editor.chain().focus().setImage({ src: e.target.result }).run();
            };
            reader.readAsDataURL(file);
            event.target.value = '';
        },

        destroy() {
            if (this.editor) this.editor.destroy();
        }
    }
}
</script>
@endpush
