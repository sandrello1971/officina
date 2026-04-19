@extends('layouts.intranet')
@section('title', $document->title ?? $document->file_stem)
@section('content')
<div style="max-width:800px;">
    <a href="/intranet/kb" style="color:#8A9696; font-size:0.8rem;">← Knowledge Base</a>

    <div style="background:white; border-radius:12px; padding:28px; margin-top:16px;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:20px;">
            <div>
                <h1 style="font-size:1.25rem; font-weight:700; color:#1A1F1F; margin-bottom:8px;">
                    {{ $document->title ?? $document->file_stem }}
                </h1>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    @if($document->tipo_documento)
                    <span style="font-size:0.75rem; background:#F5F7F7; color:#8A9696; padding:3px 10px; border-radius:4px;">
                        {{ $document->tipo_documento }}
                    </span>
                    @endif
                    @if($document->lingua)
                    <span style="font-size:0.75rem; background:#F5F7F7; color:#8A9696; padding:3px 10px; border-radius:4px;">
                        🌐 {{ $document->lingua }}
                    </span>
                    @endif
                    @if($document->data_catalogazione)
                    <span style="font-size:0.75rem; background:#F5F7F7; color:#8A9696; padding:3px 10px; border-radius:4px;">
                        📅 {{ $document->data_catalogazione->format('d/m/Y') }}
                    </span>
                    @endif
                </div>
            </div>
            @if($document->file_path && file_exists($document->file_path))
            <a href="/intranet/kb/{{ $document->id }}/download"
               style="padding:8px 20px; background:#55B1AE; color:white; border-radius:8px; font-size:0.875rem; font-weight:600; text-decoration:none; flex-shrink:0;">
                ⬇ Scarica
            </a>
            @endif
        </div>

        @if($document->sommario)
        <div style="background:#F5F7F7; border-left:4px solid #55B1AE; border-radius:0 8px 8px 0; padding:16px; margin-bottom:20px;">
            <p style="color:#4A5252; font-size:0.9rem; line-height:1.7; margin:0;">{{ $document->sommario }}</p>
        </div>
        @endif

        @if(!empty($document->argomenti))
        <div style="margin-bottom:16px;">
            <h3 style="font-weight:700; color:#1A1F1F; font-size:0.85rem; margin-bottom:8px;">🔗 Argomenti correlati</h3>
            <div style="display:flex; flex-wrap:wrap; gap:6px;">
                @foreach($document->argomenti as $arg)
                <span style="font-size:0.8rem; background:#E8F5F5; color:#3A8C89; padding:4px 12px; border-radius:6px;">
                    {{ $arg }}
                </span>
                @endforeach
            </div>
        </div>
        @endif

        @if(!empty($document->tags))
        <div>
            <h3 style="font-weight:700; color:#1A1F1F; font-size:0.85rem; margin-bottom:8px;">🏷️ Tag</h3>
            <div style="display:flex; flex-wrap:wrap; gap:6px;">
                @foreach($document->tags as $tag)
                <a href="/intranet/kb?tag={{ $tag }}"
                   style="font-size:0.8rem; background:#E8F5F5; color:#3A8C89; padding:4px 12px; border-radius:6px; text-decoration:none;">
                    #{{ $tag }}
                </a>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
