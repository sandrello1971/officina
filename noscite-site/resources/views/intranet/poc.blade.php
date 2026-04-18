@extends('layouts.intranet')
@section('title', 'POC, Demo & MVP')
@section('content')
<div style="max-width:960px;">
    <h1 style="font-size:1.25rem; font-weight:700; color:#1A1F1F; margin-bottom:24px;">🧪 POC, Demo & MVP</h1>

    @php
    $typeLabels = [
        'poc' => ['emoji' => '🧪', 'label' => 'Proof of Concept'],
        'demo' => ['emoji' => '🎬', 'label' => 'Demo'],
        'mvp' => ['emoji' => '🚀', 'label' => 'MVP — Minimum Viable Product'],
    ];
    @endphp

    @forelse($items as $type => $typeItems)
    @php $tl = $typeLabels[$type] ?? ['emoji' => '📦', 'label' => ucfirst($type)]; @endphp
    <div style="margin-bottom:32px;">
        <h2 style="font-size:0.85rem; font-weight:700; color:#8A9696; text-transform:uppercase; letter-spacing:0.1em; margin-bottom:12px;">
            {{ $tl['emoji'] }} {{ $tl['label'] }}
        </h2>
        <div style="display:grid; grid-template-columns:repeat(2,1fr); gap:16px;">
            @foreach($typeItems as $item)
            <a href="{{ $item->url }}" target="_blank" class="tool-card">
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
                    <span style="font-size:1.5rem;">{{ $item->icon }}</span>
                    <div>
                        <div style="font-weight:700; color:#1A1F1F;">{{ $item->name }}</div>
                        @if($item->status)
                        <span style="font-size:0.7rem; background:#E8F5F5; color:#3A8C89; padding:2px 6px; border-radius:4px; font-weight:700;">{{ $item->status }}</span>
                        @endif
                    </div>
                </div>
                @if($item->description)
                <div style="font-size:0.85rem; color:#8A9696; line-height:1.5; margin-bottom:10px;">{{ $item->description }}</div>
                @endif
                @if($item->credentials)
                <div style="font-size:0.75rem; color:#8A9696; background:#F5F7F7; padding:6px 10px; border-radius:6px; font-family:monospace;">
                    📧 {{ $item->credentials }}
                </div>
                @endif
            </a>
            @endforeach
        </div>
    </div>
    @empty
    <div style="background:white; border-radius:10px; padding:32px; text-align:center; color:#8A9696;">
        Nessun elemento. <a href="/intranet/manage" style="color:#55B1AE;">Aggiungi da Gestione strumenti →</a>
    </div>
    @endforelse

    @if(session('intranet_user')['is_admin'] ?? false)
    <a href="/intranet/manage"
       style="display:flex; align-items:center; justify-content:center; padding:16px; background:white; border-radius:12px; border:2px dashed #55B1AE; text-decoration:none; color:#55B1AE; font-size:0.875rem; font-weight:600; gap:8px;">
        + Aggiungi POC / Demo / MVP da Gestione strumenti
    </a>
    @endif
</div>
@endsection
