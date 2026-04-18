<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Intranet') — Noscite</title>
    <link rel="icon" type="image/png" href="/images/logo.png">
    <script src="https://cdn.tailwindcss.com/3.4.1"></script>
    <style>
        body { font-family: 'Calibri', system-ui, sans-serif; background:#F5F7F7; }
        .sidebar { width:240px; min-height:100vh; background:#1A1F1F; position:fixed; left:0; top:0; z-index:40; }
        .main { margin-left:240px; min-height:100vh; }
        .nav-item { display:flex; align-items:center; gap:10px; padding:10px 20px; color:#8A9696; font-size:0.875rem; text-decoration:none; border-radius:6px; margin:2px 8px; transition:all 0.2s; }
        .nav-item:hover, .nav-item.active { background:rgba(85,177,174,0.15); color:#55B1AE; }
        .card { background:white; border-radius:12px; padding:20px; box-shadow:0 1px 4px rgba(0,0,0,0.06); }
        .tool-card { background:white; border-radius:12px; padding:20px; border:1px solid #E8F5F5; transition:all 0.2s; text-decoration:none; display:block; }
        .tool-card:hover { border-color:#55B1AE; box-shadow:0 4px 12px rgba(85,177,174,0.15); transform:translateY(-2px); }
    </style>
</head>
<body>

<aside class="sidebar">
    <div style="padding:20px; border-bottom:1px solid rgba(85,177,174,0.2);">
        <img src="/images/logo.png" alt="Noscite" style="height:32px; filter:brightness(0) invert(1); margin-bottom:8px;">
        <div style="color:#55B1AE; font-size:0.7rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em;">Intranet</div>
    </div>

    @php $intranetUser = session('intranet_user'); @endphp
    <div style="padding:16px 20px; border-bottom:1px solid rgba(85,177,174,0.1);">
        <div style="display:flex; align-items:center; gap:10px;">
            @if($intranetUser['avatar'] ?? null)
            <img src="{{ $intranetUser['avatar'] }}" style="width:32px; height:32px; border-radius:50%; object-fit:cover;">
            @else
            <div style="width:32px; height:32px; border-radius:50%; background:#55B1AE; display:flex; align-items:center; justify-content:center; color:white; font-weight:700; font-size:0.875rem;">
                {{ strtoupper(substr($intranetUser['name'] ?? 'U', 0, 1)) }}
            </div>
            @endif
            <div>
                <div style="color:#E8EDED; font-size:0.8rem; font-weight:600;">{{ $intranetUser['name'] ?? '' }}</div>
                <div style="color:#8A9696; font-size:0.7rem;">{{ $intranetUser['email'] ?? '' }}</div>
            </div>
        </div>
    </div>

    <nav style="padding:12px 0;">
        <a href="/intranet" class="nav-item {{ request()->routeIs('intranet.dashboard') ? 'active' : '' }}">
            🏠 Dashboard
        </a>
        <a href="/intranet/tools" class="nav-item {{ request()->routeIs('intranet.tools') ? 'active' : '' }}">
            🔧 Strumenti
        </a>
        <a href="/intranet/poc" class="nav-item {{ request()->routeIs('intranet.poc') ? 'active' : '' }}">
            🧪 POC & Demo
        </a>

        @if($intranetUser['is_admin'] ?? false)
        <div style="margin:16px 8px 4px; padding:0 12px;">
            <div style="color:#4A5252; font-size:0.65rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em;">Admin</div>
        </div>
        <a href="/intranet/manage" class="nav-item {{ request()->routeIs('intranet.manage') ? 'active' : '' }}">
            ⚙ Gestione strumenti
        </a>
        @endif

        <div style="margin:16px 8px 4px; padding:0 12px;">
            <div style="color:#4A5252; font-size:0.65rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em;">Link rapidi</div>
        </div>
        <a href="https://teams.microsoft.com" target="_blank" class="nav-item">📞 Teams</a>
        <a href="https://outlook.office.com" target="_blank" class="nav-item">📧 Outlook</a>
        <a href="https://noscite.sharepoint.com" target="_blank" class="nav-item">📁 SharePoint</a>
        <a href="https://atheneum.noscite.it/admin" target="_blank" class="nav-item">🎓 Atheneum Admin</a>
        <a href="https://noscite.it/nosciteadmin" target="_blank" class="nav-item">🌐 Sito Admin</a>
    </nav>

    <div style="position:absolute; bottom:0; left:0; right:0; padding:16px 20px; border-top:1px solid rgba(85,177,174,0.1);">
        <form method="POST" action="/intranet/logout">
            @csrf
            <button type="submit" style="width:100%; padding:7px; background:rgba(226,138,83,0.1); color:#E28A53; border:1px solid rgba(226,138,83,0.3); border-radius:6px; font-size:0.8rem; cursor:pointer;">
                Esci
            </button>
        </form>
    </div>
</aside>

<div class="main">
    <div style="background:white; padding:12px 24px; border-bottom:1px solid #C8D0D0; display:flex; align-items:center; justify-content:space-between;">
        <div style="font-size:0.875rem; font-weight:600; color:#1A1F1F;">@yield('title', 'Dashboard')</div>
        <div style="font-size:0.8rem; color:#8A9696;">{{ now()->locale('it')->isoFormat('dddd D MMMM YYYY') }}</div>
    </div>

    @if(session('error'))
    <div style="margin:16px 24px; padding:12px 16px; background:#fff3ec; border-left:4px solid #E28A53; border-radius:6px; color:#c97a45; font-size:0.875rem;">
        ⚠ {{ session('error') }}
    </div>
    @endif

    @if(session('success'))
    <div style="margin:16px 24px; padding:12px 16px; background:#E8F5F5; border-left:4px solid #55B1AE; border-radius:6px; color:#3A8C89; font-size:0.875rem;">
        ✓ {{ session('success') }}
    </div>
    @endif

    <div style="padding:24px;">
        @yield('content')
    </div>
</div>

</body>
</html>
