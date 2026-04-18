@extends('layouts.noscite')
@section('title', $post->meta_title ?? $post->title)

@push('meta')
    <x-seo
        :title="$post->meta_title ?? $post->title"
        :description="$post->meta_description ?? $post->excerpt ?? ''"
        :canonical="route('commentarium.show', $post)"
    />
@endpush

@section('content')

<article>
    {{-- Cover image --}}
    @if($post->cover_image_url)
        <div class="max-w-4xl mx-auto px-4 pt-8">
            <img
                src="{{ $post->cover_image_url }}"
                alt="{{ $post->title }}"
                class="w-full rounded-xl"
                style="height:400px; object-fit:cover; object-position:center;"
                loading="lazy"
            >
        </div>
    @else
        <div class="max-w-4xl mx-auto px-4 pt-8">
            <div class="w-full rounded-xl flex items-center justify-center" style="height:400px; background:linear-gradient(135deg, #E8F5F5, #55B1AE)">
                <div class="text-center">
                    <div style="color:#3A8C89;font-size:3rem">&#10022;</div>
                    <div class="text-sm font-bold uppercase mt-2 tracking-widest" style="color:#3A8C89">{{ $post->category ?? 'Commentarium' }}</div>
                </div>
            </div>
        </div>
    @endif

    <div class="max-w-4xl mx-auto px-4 py-12">
        {{-- Categoria --}}
        @if($post->category)
            <span class="inline-block px-3 py-1 text-xs font-semibold rounded-full uppercase tracking-wide" style="background:#E8F5F5;color:#3A8C89">
                {{ $post->category }}
            </span>
        @endif

        {{-- Titolo --}}
        <h1 class="mt-4 text-3xl sm:text-4xl lg:text-5xl font-bold leading-tight" style="color:#1A1F1F">
            {{ $post->title }}
        </h1>

        {{-- Meta: data e autore --}}
        <div class="mt-6 flex flex-wrap items-center gap-4 text-sm" style="color:#8A9696">
            @if($post->published_at)
                <time datetime="{{ $post->published_at->toISOString() }}">
                    {{ $post->published_at->translatedFormat('d F Y') }}
                </time>
            @endif

            @if($post->author_name)
                <span class="flex items-center gap-1.5">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    {{ $post->author_name }}
                </span>
            @endif

            <span class="flex items-center gap-1.5">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                {{ number_format($post->views_count) }} letture
            </span>
        </div>

        {{-- Tags --}}
        @if($post->tags && count($post->tags) > 0)
            <div class="mt-4 flex flex-wrap gap-2">
                @foreach($post->tags as $tag)
                    <span class="px-2.5 py-0.5 text-xs font-medium rounded-full" style="background:#F5F7F7;color:#4A5252">
                        {{ $tag }}
                    </span>
                @endforeach
            </div>
        @endif

        {{-- Separatore --}}
        <hr class="my-8" style="border-color:#C8D0D0">

        {{-- Contenuto --}}
        <div class="prose prose-lg max-w-none
                    prose-headings:font-semibold
                    prose-img:rounded-xl prose-img:shadow-md" style="color:#4A5252">
            {!! $post->content !!}
        </div>

        {{-- Separatore --}}
        <hr class="my-12" style="border-color:#C8D0D0">

        {{-- Navigazione --}}
        <div class="flex items-center justify-between">
            <a href="{{ route('commentarium.index') }}"
               class="inline-flex items-center text-sm font-medium transition-colors" style="color:#55B1AE"
               onmouseover="this.style.color='#3A8C89'" onmouseout="this.style.color='#55B1AE'">
                <svg class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16l-4-4m0 0l4-4m-4 4h18"/>
                </svg>
                Torna al Commentarium
            </a>
        </div>
    </div>
</article>

@endsection
