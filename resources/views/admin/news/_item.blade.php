<div style="background:white; border-radius:10px; padding:16px; margin-bottom:12px; border:1px solid #E8ECEC;">
    <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px;">
        <div style="flex:1;">
            <div style="font-weight:700; color:#1A1F1F; font-size:0.95rem;">{{ $item->title }}</div>
            <div style="font-size:0.85rem; color:#4A5252; margin-top:6px; white-space:pre-wrap;">{{ $item->summary }}</div>
            <div style="font-size:0.75rem; color:#8A9696; margin-top:8px;">
                @if($item->source_name || $item->source_url)
                    Fonte:
                    @if($item->source_url)
                        <a href="{{ $item->source_url }}" target="_blank" rel="noopener noreferrer" style="color:#3A8C89;">{{ $item->source_name ?: $item->source_url }}</a>
                    @else
                        {{ $item->source_name }}
                    @endif
                    @if($item->source_published_at) · {{ $item->source_published_at->format('d/m/Y') }} @endif
                    ·
                @endif
                @if(is_array($item->tags))
                    @foreach($item->tags as $tag)
                        <span style="display:inline-block; background:#E8F5F5; color:#3A8C89; border-radius:10px; padding:1px 8px; font-size:0.7rem; margin-right:4px;">{{ $tag }}</span>
                    @endforeach
                @endif
            </div>
        </div>
        <div style="display:flex; flex-direction:column; gap:6px;">
            @if($draft)
                <form method="POST" action="{{ route('admin.news.publish', $item) }}">@csrf @method('PATCH')
                    <button type="submit" style="background:#55B1AE; color:white; border:none; border-radius:6px; padding:6px 12px; font-size:0.78rem; font-weight:600; cursor:pointer; width:100%;">Pubblica</button>
                </form>
            @endif
            <form method="POST" action="{{ route('admin.news.reject', $item) }}" onsubmit="return confirm('Scartare questa news?');">@csrf @method('PATCH')
                <button type="submit" style="background:white; color:#c0392b; border:1px solid #E0B4AC; border-radius:6px; padding:6px 12px; font-size:0.78rem; cursor:pointer; width:100%;">Scarta</button>
            </form>
        </div>
    </div>
</div>
