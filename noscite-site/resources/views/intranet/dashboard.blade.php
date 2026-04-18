@extends('layouts.intranet')
@section('title', 'Dashboard')
@section('content')

<div style="max-width:960px;">
    <div style="margin-bottom:24px;">
        <h1 style="font-size:1.5rem; font-weight:700; color:#1A1F1F;">
            Ciao, {{ explode(' ', $user['name'])[0] }} 👋
        </h1>
        <p style="color:#8A9696; font-size:0.875rem;">Benvenuto nell'intranet Noscite</p>
    </div>

    <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:24px;">
        <a href="https://teams.microsoft.com" target="_blank" class="tool-card" style="text-align:center; padding:16px;">
            <div style="font-size:2rem; margin-bottom:8px;">📞</div>
            <div style="font-weight:600; color:#1A1F1F; font-size:0.875rem;">Teams</div>
            <div style="color:#8A9696; font-size:0.75rem;">Comunicazione</div>
        </a>
        <a href="https://outlook.office.com" target="_blank" class="tool-card" style="text-align:center; padding:16px;">
            <div style="font-size:2rem; margin-bottom:8px;">📧</div>
            <div style="font-weight:600; color:#1A1F1F; font-size:0.875rem;">Outlook</div>
            <div style="color:#8A9696; font-size:0.75rem;">Email aziendale</div>
        </a>
        <a href="https://atheneum.noscite.it/admin" target="_blank" class="tool-card" style="text-align:center; padding:16px;">
            <div style="font-size:2rem; margin-bottom:8px;">🎓</div>
            <div style="font-weight:600; color:#1A1F1F; font-size:0.875rem;">Atheneum</div>
            <div style="color:#8A9696; font-size:0.75rem;">Piattaforma corsi</div>
        </a>
        <a href="https://noscite.it/nosciteadmin" target="_blank" class="tool-card" style="text-align:center; padding:16px;">
            <div style="font-size:2rem; margin-bottom:8px;">🌐</div>
            <div style="font-weight:600; color:#1A1F1F; font-size:0.875rem;">Sito Admin</div>
            <div style="color:#8A9696; font-size:0.75rem;">Gestione contenuti</div>
        </a>
    </div>

    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">

        <div class="card">
            <h3 style="font-weight:700; color:#1A1F1F; margin-bottom:16px; display:flex; align-items:center; gap:8px;">
                🔧 <span>Strumenti AI</span>
                <a href="/intranet/tools" style="margin-left:auto; font-size:0.75rem; color:#55B1AE; font-weight:400;">Vedi tutti →</a>
            </h3>
            <div style="display:flex; flex-direction:column; gap:8px;">
                @foreach($tools->where('section', 'AI Tools')->take(3) as $tool)
                <a href="{{ $tool->url }}" target="_blank" style="display:flex; align-items:center; gap:10px; padding:10px; background:#F5F7F7; border-radius:8px; text-decoration:none;">
                    <span style="font-size:1.2rem;">{{ $tool->icon }}</span>
                    <div>
                        <div style="font-size:0.85rem; font-weight:600; color:#1A1F1F;">{{ $tool->name }}</div>
                        <div style="font-size:0.75rem; color:#8A9696;">{{ \Illuminate\Support\Str::limit($tool->description, 50) }}</div>
                    </div>
                </a>
                @endforeach
            </div>
        </div>

        <div class="card">
            <h3 style="font-weight:700; color:#1A1F1F; margin-bottom:16px; display:flex; align-items:center; gap:8px;">
                🧪 <span>POC Attivi</span>
                <a href="/intranet/poc" style="margin-left:auto; font-size:0.75rem; color:#55B1AE; font-weight:400;">Vedi tutti →</a>
            </h3>
            <div style="display:flex; flex-direction:column; gap:8px;">
                @forelse($poc as $item)
                <a href="{{ $item->url }}" target="_blank"
                   style="display:flex; align-items:center; gap:10px; padding:10px; background:#F5F7F7; border-radius:8px; text-decoration:none;">
                    <span style="font-size:1.2rem;">{{ $item->icon }}</span>
                    <div>
                        <div style="font-size:0.85rem; font-weight:600; color:#1A1F1F;">{{ $item->name }}</div>
                        <div style="font-size:0.75rem; color:#8A9696;">{{ \Illuminate\Support\Str::limit($item->description, 50) }}</div>
                    </div>
                    @if($item->status)
                    <span style="margin-left:auto; font-size:0.7rem; background:#E8F5F5; color:#3A8C89; padding:2px 6px; border-radius:4px; font-weight:700;">{{ $item->status }}</span>
                    @endif
                </a>
                @empty
                <p style="color:#8A9696; font-size:0.8rem; text-align:center; padding:8px;">Nessun POC configurato.</p>
                @endforelse
                @if(session('intranet_user')['is_admin'] ?? false)
                <a href="/intranet/manage"
                   style="display:flex; align-items:center; justify-content:center; padding:10px; background:#F5F7F7; border-radius:8px; border:1px dashed #C8D0D0; text-decoration:none; color:#8A9696; font-size:0.8rem;">
                    + Gestisci POC
                </a>
                @endif
            </div>
        </div>
    </div>

    @if($services->count() > 0)
    <div class="card" style="margin-top:16px;">
        <h3 style="font-weight:700; color:#1A1F1F; margin-bottom:16px; display:flex; align-items:center; gap:8px;">
            🏢 <span>Servizi</span>
            <a href="/intranet/services" style="margin-left:auto; font-size:0.75rem; color:#55B1AE; font-weight:400;">Vedi tutti →</a>
        </h3>
        <div style="display:flex; flex-direction:column; gap:8px;">
            @foreach($services->take(3) as $service)
            <a href="{{ $service->url }}" target="_blank"
               style="display:flex; align-items:center; gap:10px; padding:10px; background:#F5F7F7; border-radius:8px; text-decoration:none;">
                <span style="font-size:1.2rem;">{{ $service->icon }}</span>
                <div>
                    <div style="font-size:0.85rem; font-weight:600; color:#1A1F1F;">{{ $service->name }}</div>
                    <div style="font-size:0.75rem; color:#8A9696;">{{ \Illuminate\Support\Str::limit($service->description, 50) }}</div>
                </div>
            </a>
            @endforeach
        </div>
    </div>
    @endif

    <div class="card" style="margin-top:16px;">
        <h3 style="font-weight:700; color:#1A1F1F; margin-bottom:16px; display:flex; align-items:center; gap:8px;">
            🖥 <span>VPS Attivi</span>
            <a href="/intranet/servers" style="margin-left:auto; font-size:0.75rem; color:#55B1AE; font-weight:400;">Vedi tutti →</a>
        </h3>
        <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:8px;">
            @foreach($servers as $server)
            <a href="{{ $server->url }}" target="_blank"
               style="padding:10px 12px; background:#F5F7F7; border-radius:8px; text-decoration:none; border-left:3px solid
                   {{ $server->status === 'active' ? '#55B1AE' : ($server->status === 'maintenance' ? '#E28A53' : '#8A9696') }};">
                <div style="font-size:0.85rem; font-weight:600; color:#1A1F1F; margin-bottom:2px;">{{ $server->name }}</div>
                <div style="font-size:0.75rem; color:#8A9696;">{{ $server->provider }} · {{ $server->ip_address }}</div>
            </a>
            @endforeach
        </div>
    </div>
</div>
@endsection
