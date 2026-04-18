<div>
    {{-- FILTRI --}}
    <div class="flex flex-wrap gap-3 mb-8 items-center">

        {{-- Search --}}
        <div class="flex-1 min-w-48">
            <input type="text"
                   wire:model.live.debounce.300ms="search"
                   placeholder="Cerca nel Commentarium..."
                   class="w-full px-4 py-2 text-sm border rounded-lg outline-none"
                   style="border-color:#C8D0D0;color:#1A1F1F">
        </div>

        {{-- Filtri categoria --}}
        <div class="flex gap-2 flex-wrap">
            <button wire:click="$set('category', '')"
                    class="px-4 py-2 rounded-full text-sm font-medium transition-colors"
                    style="{{ $category === '' ? 'background:#55B1AE;color:white' : 'background:#E8F5F5;color:#3A8C89' }}">
                Tutti
            </button>
            <button wire:click="$set('category', 'Visione')"
                    class="px-4 py-2 rounded-full text-sm font-medium transition-colors"
                    style="{{ $category === 'Visione' ? 'background:#55B1AE;color:white' : 'background:#E8F5F5;color:#3A8C89' }}">
                &#10022; Visione
            </button>
            <button wire:click="$set('category', 'Pratica')"
                    class="px-4 py-2 rounded-full text-sm font-medium transition-colors"
                    style="{{ $category === 'Pratica' ? 'background:#E28A53;color:white' : 'background:#fff3ec;color:#c97a45' }}">
                &#9881; Pratica
            </button>
        </div>
    </div>

    {{-- RISULTATI --}}
    @if($posts->isEmpty())
    <div class="text-center py-16" style="color:#8A9696">
        <div class="text-4xl mb-3">&#10022;</div>
        <p>Nessun articolo trovato.</p>
        @if($search || $category)
        <button wire:click="$set('search', ''); $set('category', '')"
                class="mt-3 text-sm underline" style="color:#55B1AE">
            Rimuovi filtri
        </button>
        @endif
    </div>
    @else
    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($posts as $post)
        <article class="bg-white rounded-xl overflow-hidden shadow-sm hover:shadow-md transition-shadow">

            {{-- Immagine --}}
            @if($post->cover_image_url)
            <a href="/commentarium/{{ $post->slug }}">
                <img src="{{ $post->cover_image_url }}"
                     alt="{{ $post->title }}"
                     class="w-full object-cover"
                     style="height:200px"
                     loading="lazy">
            </a>
            @else
            <a href="/commentarium/{{ $post->slug }}">
                <div class="w-full flex items-center justify-center"
                     style="height:200px;background:linear-gradient(135deg,#E8F5F5,#55B1AE)">
                    <div class="text-center">
                        <div style="color:#3A8C89;font-size:2rem">&#10022;</div>
                    </div>
                </div>
            </a>
            @endif

            <div class="p-5">
                {{-- Badge categoria --}}
                <div class="flex items-center gap-2 mb-3 flex-wrap">
                    @if($post->category === 'Visione')
                    <span class="text-xs font-bold uppercase px-2 py-1 rounded-full"
                          style="background:#E8F5F5;color:#3A8C89">
                        &#10022; Visione
                    </span>
                    @elseif($post->category === 'Pratica')
                    <span class="text-xs font-bold uppercase px-2 py-1 rounded-full"
                          style="background:#fff3ec;color:#c97a45">
                        &#9881; Pratica
                    </span>
                    @endif
                    <span class="text-xs" style="color:#8A9696">
                        {{ $post->published_at?->locale('it')->isoFormat('D MMMM YYYY') }}
                    </span>
                </div>

                {{-- Titolo --}}
                <h2 class="font-bold mb-2 leading-snug" style="color:#1A1F1F">
                    <a href="/commentarium/{{ $post->slug }}"
                       class="hover:underline" style="text-decoration-color:#55B1AE">
                        {{ \Illuminate\Support\Str::title(mb_strtolower($post->title)) }}
                    </a>
                </h2>

                {{-- Estratto --}}
                @if($post->excerpt)
                <p class="text-sm mb-4 line-clamp-3" style="color:#4A5252">
                    {{ $post->excerpt }}
                </p>
                @else
                <p class="text-sm mb-4 line-clamp-3" style="color:#4A5252">
                    {{ \Illuminate\Support\Str::limit(strip_tags($post->content), 120) }}
                </p>
                @endif

                {{-- Link --}}
                <a href="/commentarium/{{ $post->slug }}"
                   class="text-sm font-semibold"
                   style="color:#55B1AE">
                    Leggi &rarr;
                </a>
            </div>
        </article>
        @endforeach
    </div>
    @endif

    {{-- Contatore --}}
    <p class="text-xs mt-6 text-center" style="color:#8A9696">
        {{ $posts->count() }} {{ $posts->count() === 1 ? 'articolo' : 'articoli' }}
        @if($category) nella categoria <strong>{{ $category }}</strong> @endif
        @if($search) per "<strong>{{ $search }}</strong>" @endif
    </p>
</div>
