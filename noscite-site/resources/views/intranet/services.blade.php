@extends('layouts.intranet')
@section('title', 'Servizi')
@section('content')
<div style="max-width:960px;">
    <h1 style="font-size:1.25rem; font-weight:700; color:#1A1F1F; margin-bottom:24px;">🏢 Servizi</h1>

    <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:16px;">
        @forelse($services as $service)
        <a href="{{ $service->url }}" target="_blank" class="tool-card">
            <div style="font-size:2rem; margin-bottom:10px;">{{ $service->icon }}</div>
            <div style="font-weight:700; color:#1A1F1F; margin-bottom:4px;">{{ $service->name }}</div>
            @if($service->description)
            <div style="font-size:0.8rem; color:#8A9696; line-height:1.5; margin-bottom:8px;">{{ $service->description }}</div>
            @endif
            @if($service->label)
            <div style="font-size:0.75rem; color:#55B1AE; font-weight:600;">{{ $service->label }} →</div>
            @endif
        </a>
        @empty
        <div style="grid-column:1/-1; background:white; border-radius:10px; padding:32px; text-align:center; color:#8A9696;">
            Nessun servizio configurato.
            @if(session('intranet_user')['is_admin'] ?? false)
            <a href="/intranet/manage" style="color:#55B1AE; display:block; margin-top:8px;">
                Aggiungi da Gestione strumenti →
            </a>
            @endif
        </div>
        @endforelse
    </div>
</div>
@endsection
