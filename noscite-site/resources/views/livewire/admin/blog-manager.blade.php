<div>
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-lg font-semibold text-gray-900">Gestione Blog</h2>
        @if(!$showForm)
            <a href="{{ route('admin.blog.new') }}"
               class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-primary-600 rounded-lg hover:bg-primary-700 transition-colors">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Nuovo post
            </a>
        @endif
    </div>

    @if($showForm)
        {{-- ===== FORM ===== --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-6">
                {{ $editing ? 'Modifica post' : 'Nuovo post' }}
            </h3>

            <form wire:submit.prevent="save" class="space-y-5">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                    {{-- Titolo --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Titolo *</label>
                        <input wire:model.live="title" type="text"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        @error('title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- Slug --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Slug *</label>
                        <input wire:model="slug" type="text"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 bg-gray-50">
                        @error('slug') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                    {{-- Categoria --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Categoria</label>
                        <input wire:model="category" type="text"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                               placeholder="es. AI, Strategia, Formazione">
                        @error('category') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- Cover URL --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Cover Image URL</label>
                        <input wire:model="cover_image_url" type="url"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                               placeholder="https://...">
                        @error('cover_image_url') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Estratto --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Estratto</label>
                    <textarea wire:model="excerpt" rows="2"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 resize-y"
                              placeholder="Breve descrizione del post (max 500 caratteri)"></textarea>
                    @error('excerpt') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Contenuto --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Contenuto *</label>
                    <textarea wire:model="content" rows="12"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 resize-y font-mono"
                              placeholder="Contenuto HTML del post..."></textarea>
                    @error('content') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                    {{-- Meta title --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Meta Title</label>
                        <input wire:model="meta_title" type="text"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        @error('meta_title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- Autore --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Autore</label>
                        <input wire:model="author_name" type="text"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        @error('author_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Meta description --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Meta Description</label>
                    <textarea wire:model="meta_description" rows="2"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 resize-y"
                              placeholder="Max 300 caratteri"></textarea>
                    @error('meta_description') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Pubblicato --}}
                <div class="flex items-center gap-3">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input wire:model="published" type="checkbox"
                               class="h-5 w-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                        <span class="text-sm font-medium text-gray-700">Pubblica immediatamente</span>
                    </label>
                </div>

                {{-- Azioni --}}
                <div class="flex items-center gap-3 pt-4 border-t border-gray-200">
                    <button type="submit"
                            wire:loading.attr="disabled"
                            class="inline-flex items-center px-5 py-2.5 text-sm font-semibold text-white bg-primary-600 rounded-lg hover:bg-primary-700 disabled:opacity-50 transition-colors">
                        <span wire:loading.remove wire:target="save">{{ $editing ? 'Aggiorna' : 'Salva' }}</span>
                        <span wire:loading wire:target="save">Salvataggio...</span>
                    </button>
                    <button type="button" wire:click="cancel"
                            class="px-5 py-2.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                        Annulla
                    </button>
                </div>
            </form>
        </div>
    @else
        {{-- ===== TABELLA ===== --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            @if($posts->isEmpty())
                <div class="p-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/></svg>
                    <p class="mt-3 text-sm text-gray-500">Nessun post ancora. Crea il primo!</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Titolo</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600 hidden md:table-cell">Categoria</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Stato</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600 hidden lg:table-cell">Data</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-600">Azioni</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($posts as $post)
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-4 py-3">
                                        <div>
                                            <p class="font-medium text-gray-900 truncate max-w-xs">{{ $post->title }}</p>
                                            <p class="text-xs text-gray-400 mt-0.5">/commentarium/{{ $post->slug }}</p>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 hidden md:table-cell">
                                        @if($post->category)
                                            <span class="inline-block px-2 py-0.5 text-xs font-medium text-gray-600 bg-gray-100 rounded-full">{{ $post->category }}</span>
                                        @else
                                            <span class="text-gray-300">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($post->published)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-semibold text-green-700 bg-green-100 rounded-full">
                                                <span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span>
                                                Pubblicato
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-semibold text-gray-500 bg-gray-100 rounded-full">
                                                <span class="w-1.5 h-1.5 bg-gray-400 rounded-full"></span>
                                                Bozza
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-gray-500 hidden lg:table-cell">
                                        {{ optional($post->created_at ?? $post->published_at ?? $post->updated_at)->format('d/m/Y') ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-1">
                                            <a href="/nosciteadmin/blog/{{ $post->id }}/edit"
                                                    class="p-1.5 text-gray-400 hover:text-primary-600 rounded-lg hover:bg-primary-50 transition-colors inline-flex" title="Modifica">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                            </a>
                                            <button wire:click="togglePublished('{{ $post->id }}')"
                                                    class="p-1.5 text-gray-400 hover:text-secondary-600 rounded-lg hover:bg-secondary-50 transition-colors"
                                                    title="{{ $post->published ? 'Nascondi' : 'Pubblica' }}">
                                                @if($post->published)
                                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                                                @else
                                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                                @endif
                                            </button>
                                            <button wire:click="delete('{{ $post->id }}')"
                                                    wire:confirm="Sei sicuro di voler eliminare questo post?"
                                                    class="p-1.5 text-gray-400 hover:text-red-600 rounded-lg hover:bg-red-50 transition-colors" title="Elimina">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif
</div>
