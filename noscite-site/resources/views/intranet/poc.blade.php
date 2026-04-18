@extends('layouts.intranet')
@section('title', 'POC & Demo')
@section('content')
<div style="max-width:960px;">
    <h1 style="font-size:1.25rem; font-weight:700; color:#1A1F1F; margin-bottom:24px;">🧪 POC & Demo</h1>

    <div style="display:grid; grid-template-columns:repeat(2,1fr); gap:16px;">
        @foreach($poc as $item)
        <a href="{{ $item['url'] }}" target="_blank" class="tool-card">
            <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
                <span style="font-size:1.5rem;">{{ $item['icon'] }}</span>
                <div>
                    <div style="font-weight:700; color:#1A1F1F;">{{ $item['name'] }}</div>
                    @if($item['status'] ?? null)
                    <span style="font-size:0.7rem; background:#E8F5F5; color:#3A8C89; padding:2px 6px; border-radius:4px; font-weight:700;">{{ $item['status'] }}</span>
                    @endif
                </div>
            </div>
            <div style="font-size:0.85rem; color:#8A9696; line-height:1.5; margin-bottom:10px;">{{ $item['description'] }}</div>
            @if($item['credentials'] ?? null)
            <div style="font-size:0.75rem; color:#8A9696; background:#F5F7F7; padding:6px 10px; border-radius:6px; font-family:monospace;">
                📧 {{ $item['credentials'] }}
            </div>
            @endif
        </a>
        @endforeach

        <div style="background:white; border-radius:12px; padding:20px; border:2px dashed #C8D0D0; display:flex; align-items:center; justify-content:center; min-height:120px;">
            <div style="text-align:center; color:#C8D0D0;">
                <div style="font-size:1.5rem; margin-bottom:6px;">+</div>
                <div style="font-size:0.8rem;">Aggiungi in config/intranet.php</div>
            </div>
        </div>
    </div>
</div>
@endsection
