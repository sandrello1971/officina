@extends('layouts.intranet')
@section('title', 'Infrastruttura VPS')
@section('content')

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
    <h1 style="font-size:1.25rem; font-weight:700; color:#1A1F1F;">🖥 Infrastruttura VPS</h1>
    @if(session('intranet_user')['is_admin'] ?? false)
    <button onclick="document.getElementById('add-server-form').classList.toggle('hidden')"
            style="padding:8px 20px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.875rem; font-weight:600; cursor:pointer;">
        + Aggiungi server
    </button>
    @endif
</div>

@if(session('intranet_user')['is_admin'] ?? false)
<div id="add-server-form" class="hidden" style="background:white; border-radius:12px; padding:24px; margin-bottom:24px;">
    <h3 style="font-weight:700; color:#1A1F1F; margin-bottom:16px;">Aggiungi server</h3>
    <form method="POST" action="/intranet/servers">
        @csrf
        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; margin-bottom:12px;">
            <div>
                <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:4px;">Nome *</label>
                <input type="text" name="name" required placeholder="Es: CRM"
                       style="width:100%; padding:8px 12px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.875rem; outline:none;">
            </div>
            <div>
                <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:4px;">IP</label>
                <input type="text" name="ip_address" placeholder="Es: 91.134.242.201"
                       style="width:100%; padding:8px 12px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.875rem; outline:none;">
            </div>
            <div>
                <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:4px;">Provider</label>
                <select name="provider" style="width:100%; padding:8px 12px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.875rem; outline:none;">
                    <option value="OVH">OVH</option>
                    <option value="ARUBA">ARUBA</option>
                    <option value="AWS">AWS</option>
                    <option value="Hetzner">Hetzner</option>
                    <option value="Altro">Altro</option>
                </select>
            </div>
            <div>
                <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:4px;">URL</label>
                <input type="url" name="url" placeholder="https://..."
                       style="width:100%; padding:8px 12px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.875rem; outline:none;">
            </div>
            <div>
                <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:4px;">GitHub</label>
                <input type="url" name="github_url" placeholder="https://github.com/..."
                       style="width:100%; padding:8px 12px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.875rem; outline:none;">
            </div>
            <div>
                <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:4px;">Servizio</label>
                <input type="text" name="service" placeholder="Cosa fa questo server"
                       style="width:100%; padding:8px 12px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.875rem; outline:none;">
            </div>
            <div>
                <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:4px;">OS</label>
                <input type="text" name="os" placeholder="Es: Ubuntu 25.04"
                       style="width:100%; padding:8px 12px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.875rem; outline:none;">
            </div>
            <div>
                <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:4px;">Specs</label>
                <input type="text" name="specs" placeholder="Es: 6 CPU · 11GB RAM"
                       style="width:100%; padding:8px 12px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.875rem; outline:none;">
            </div>
            <div>
                <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:4px;">Status</label>
                <select name="status" style="width:100%; padding:8px 12px; border:1px solid #C8D0D0; border-radius:6px; font-size:0.875rem; outline:none;">
                    <option value="active">✅ Attivo</option>
                    <option value="maintenance">🔧 Manutenzione</option>
                    <option value="offline">❌ Offline</option>
                </select>
            </div>
        </div>
        <button type="submit"
                style="padding:8px 24px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.875rem; font-weight:700; cursor:pointer;">
            + Aggiungi
        </button>
    </form>
</div>
@endif

<div style="display:grid; gap:12px;">
    @foreach($servers as $server)
    <div style="background:white; border-radius:12px; padding:20px; border-left:4px solid
        {{ $server->status === 'active' ? '#55B1AE' : ($server->status === 'maintenance' ? '#E28A53' : '#8A9696') }};">

        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px;">
            <div style="flex:1;">
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:8px;">
                    <h3 style="font-weight:700; color:#1A1F1F; font-size:1rem;">{{ $server->name }}</h3>
                    <span style="font-size:0.7rem; padding:2px 8px; border-radius:4px; font-weight:700;
                        background:{{ $server->status === 'active' ? '#E8F5F5' : ($server->status === 'maintenance' ? '#fff3ec' : '#F5F7F7') }};
                        color:{{ $server->status === 'active' ? '#3A8C89' : ($server->status === 'maintenance' ? '#c97a45' : '#8A9696') }};">
                        {{ $server->status === 'active' ? '✅ Attivo' : ($server->status === 'maintenance' ? '🔧 Manutenzione' : '❌ Offline') }}
                    </span>
                    <span style="font-size:0.75rem; padding:2px 8px; background:#F5F7F7; color:#8A9696; border-radius:4px;">
                        {{ $server->provider }}
                    </span>
                </div>

                <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:8px; margin-bottom:10px;">
                    @if($server->ip_address)
                    <div>
                        <div style="font-size:0.7rem; color:#8A9696; text-transform:uppercase; font-weight:700;">IP</div>
                        <div style="font-size:0.875rem; color:#1A1F1F; font-family:monospace;">{{ $server->ip_address }}</div>
                    </div>
                    @endif
                    @if($server->hostname)
                    <div>
                        <div style="font-size:0.7rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Hostname</div>
                        <div style="font-size:0.8rem; color:#4A5252; font-family:monospace;">{{ $server->hostname }}</div>
                    </div>
                    @endif
                    @if($server->os)
                    <div>
                        <div style="font-size:0.7rem; color:#8A9696; text-transform:uppercase; font-weight:700;">OS</div>
                        <div style="font-size:0.8rem; color:#4A5252;">{{ $server->os }}</div>
                    </div>
                    @endif
                    @if($server->specs)
                    <div>
                        <div style="font-size:0.7rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Specs</div>
                        <div style="font-size:0.8rem; color:#4A5252;">{{ $server->specs }}</div>
                    </div>
                    @endif
                    @if($server->service)
                    <div style="grid-column:1/-1;">
                        <div style="font-size:0.7rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Servizio</div>
                        <div style="font-size:0.875rem; color:#1A1F1F;">{{ $server->service }}</div>
                    </div>
                    @endif
                </div>

                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    @if($server->url)
                    <a href="{{ $server->url }}" target="_blank"
                       style="font-size:0.8rem; color:#55B1AE; text-decoration:none; display:flex; align-items:center; gap:4px;">
                        🌐 {{ $server->url }}
                    </a>
                    @endif
                    @if($server->github_url)
                    <a href="{{ $server->github_url }}" target="_blank"
                       style="font-size:0.8rem; color:#8A9696; text-decoration:none; display:flex; align-items:center; gap:4px;">
                        📦 GitHub
                    </a>
                    @endif
                </div>

                @if($server->notes)
                <div style="margin-top:10px; padding:8px 12px; background:#F5F7F7; border-radius:6px; font-size:0.8rem; color:#4A5252; font-style:italic;">
                    {{ $server->notes }}
                </div>
                @endif
            </div>

            @if(session('intranet_user')['is_admin'] ?? false)
            <div style="display:flex; gap:8px; flex-shrink:0;">
                <form method="POST" action="/intranet/servers/{{ $server->id }}" onsubmit="return confirm('Eliminare {{ $server->name }}?')">
                    @csrf @method('DELETE')
                    <button type="submit"
                            style="padding:5px 12px; background:#fff3ec; color:#E28A53; border:1px solid #E28A53; border-radius:6px; font-size:0.75rem; cursor:pointer;">
                        Elimina
                    </button>
                </form>
            </div>
            @endif
        </div>
    </div>
    @endforeach
</div>

@endsection
