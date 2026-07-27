@extends('layouts.student')
@section('title', 'News AI')
@section('content')

<div style="max-width:860px; margin:0 auto;">
    <h1 style="font-size:1.4rem; font-weight:700; color:#1A1F1F; margin-bottom:6px;">News AI</h1>
    <p style="font-size:0.9rem; color:#8A9696; margin-bottom:20px;">
        Le principali novità sull'intelligenza artificiale, aggiornate ogni settimana da fonti autorevoli.
    </p>

    {{-- Filtro per argomento --}}
    @if($allTags->isNotEmpty())
        <div style="display:flex; flex-wrap:wrap; gap:8px; margin-bottom:22px;">
            <a href="{{ route('student.news.index') }}"
               style="text-decoration:none; padding:5px 12px; border-radius:14px; font-size:0.8rem; font-weight:600;
                      {{ empty($activeTag) ? 'background:#55B1AE; color:white;' : 'background:#F5F7F7; color:#4A5252;' }}">
                Tutte
            </a>
            @foreach($allTags as $tag)
                <a href="{{ route('student.news.index', ['tag' => $tag]) }}"
                   style="text-decoration:none; padding:5px 12px; border-radius:14px; font-size:0.8rem; font-weight:600;
                          {{ $activeTag === $tag ? 'background:#55B1AE; color:white;' : 'background:#F5F7F7; color:#4A5252;' }}">
                    {{ $tag }}
                </a>
            @endforeach
        </div>
    @endif

    @forelse($items as $item)
        <article style="background:white; border:1px solid #E8ECEC; border-radius:12px; padding:18px 20px; margin-bottom:14px;">
            <h2 style="font-size:1.05rem; font-weight:700; color:#1A1F1F; margin-bottom:8px;">{{ $item->title }}</h2>
            <p style="font-size:0.9rem; color:#4A5252; line-height:1.55; white-space:pre-wrap; margin-bottom:12px;">{{ $item->summary }}</p>
            <div style="display:flex; flex-wrap:wrap; align-items:center; gap:8px; font-size:0.78rem; color:#8A9696;">
                @if($item->source_url)
                    <a href="{{ $item->source_url }}" target="_blank" rel="noopener noreferrer" style="color:#3A8C89; font-weight:600;">
                        {{ $item->source_name ?: 'Fonte' }} ↗
                    </a>
                @elseif($item->source_name)
                    <span>{{ $item->source_name }}</span>
                @endif
                @if($item->source_published_at)
                    <span>· {{ $item->source_published_at->format('d/m/Y') }}</span>
                @endif
                @if(is_array($item->tags))
                    <span style="flex-basis:100%; height:0;"></span>
                    @foreach($item->tags as $tag)
                        <a href="{{ route('student.news.index', ['tag' => $tag]) }}"
                           style="text-decoration:none; background:#E8F5F5; color:#3A8C89; border-radius:10px; padding:2px 9px; font-size:0.72rem;">{{ $tag }}</a>
                    @endforeach
                @endif
            </div>
        </article>
    @empty
        <div style="background:#F5F7F7; border-radius:12px; padding:30px; text-align:center; color:#8A9696; font-size:0.9rem;">
            @if($activeTag)
                Nessuna news per l'argomento «{{ $activeTag }}».
            @else
                Ancora nessuna news pubblicata. Torna a trovarci nei prossimi giorni.
            @endif
        </div>
    @endforelse
</div>
@endsection
