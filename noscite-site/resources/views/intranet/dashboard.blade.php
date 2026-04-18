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
                <a href="https://claude.ai" target="_blank" style="display:flex; align-items:center; gap:10px; padding:10px; background:#F5F7F7; border-radius:8px; text-decoration:none;">
                    <span style="font-size:1.2rem;">✦</span>
                    <div>
                        <div style="font-size:0.85rem; font-weight:600; color:#1A1F1F;">Claude</div>
                        <div style="font-size:0.75rem; color:#8A9696;">AI assistant principale</div>
                    </div>
                </a>
                <a href="https://perplexity.ai" target="_blank" style="display:flex; align-items:center; gap:10px; padding:10px; background:#F5F7F7; border-radius:8px; text-decoration:none;">
                    <span style="font-size:1.2rem;">🔍</span>
                    <div>
                        <div style="font-size:0.85rem; font-weight:600; color:#1A1F1F;">Perplexity</div>
                        <div style="font-size:0.75rem; color:#8A9696;">Ricerca verificata con fonti</div>
                    </div>
                </a>
                <a href="https://chatgpt.com" target="_blank" style="display:flex; align-items:center; gap:10px; padding:10px; background:#F5F7F7; border-radius:8px; text-decoration:none;">
                    <span style="font-size:1.2rem;">🤖</span>
                    <div>
                        <div style="font-size:0.85rem; font-weight:600; color:#1A1F1F;">ChatGPT</div>
                        <div style="font-size:0.75rem; color:#8A9696;">Output e formattazione</div>
                    </div>
                </a>
            </div>
        </div>

        <div class="card">
            <h3 style="font-weight:700; color:#1A1F1F; margin-bottom:16px; display:flex; align-items:center; gap:8px;">
                🧪 <span>POC Attivi</span>
                <a href="/intranet/poc" style="margin-left:auto; font-size:0.75rem; color:#55B1AE; font-weight:400;">Vedi tutti →</a>
            </h3>
            <div style="display:flex; flex-direction:column; gap:8px;">
                <a href="https://mcphub.noscite.it" target="_blank" style="display:flex; align-items:center; gap:10px; padding:10px; background:#F5F7F7; border-radius:8px; text-decoration:none;">
                    <span style="font-size:1.2rem;">⚡</span>
                    <div>
                        <div style="font-size:0.85rem; font-weight:600; color:#1A1F1F;">MCPHub Noscite</div>
                        <div style="font-size:0.75rem; color:#8A9696;">Server MCP aziendale</div>
                    </div>
                </a>
                <div style="padding:10px; background:#F5F7F7; border-radius:8px; border:1px dashed #C8D0D0;">
                    <div style="font-size:0.85rem; color:#8A9696; text-align:center;">+ Aggiungi POC</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card" style="margin-top:16px;">
        <h3 style="font-weight:700; color:#1A1F1F; margin-bottom:16px; display:flex; align-items:center; gap:8px;">
            🖥 <span>VPS Attivi</span>
            <a href="/intranet/servers" style="margin-left:auto; font-size:0.75rem; color:#55B1AE; font-weight:400;">Vedi tutti →</a>
        </h3>
        @php $servers = \App\Models\IntranetServer::orderBy('sort_order')->get(); @endphp
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
