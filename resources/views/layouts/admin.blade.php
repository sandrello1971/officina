<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') — {{ atheneum_setting('instance_name', 'Officina') }} Admin</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <meta name="robots" content="noindex, nofollow">
    <script src="https://cdn.tailwindcss.com/3.4.1"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>
        [x-cloak] { display: none !important; }
        body { font-family: 'Calibri', system-ui, sans-serif; }
        /* Flex column: header fisso in alto, nav scrollabile, footer (email+Esci)
           pinnato in fondo con flex-shrink:0 — così con la nav lunga "Esci" non
           copre più "Impostazioni" (prima footer position:absolute). */
        .sidebar { width:240px; height:100vh; background:#1A1F1F; position:fixed; left:0; top:0; bottom:0; z-index:40; display:flex; flex-direction:column; }
        .sidebar-scroll { flex:1; overflow-y:auto; min-height:0; }
        .sidebar-footer { flex-shrink:0; padding:16px 20px; border-top:1px solid rgba(85,177,174,0.1); }
        .main-content { margin-left:240px; min-height:100vh; background:#F5F7F7; }
        .nav-item { display:flex; align-items:center; gap:10px; padding:10px 20px; color:#8A9696; font-size:0.85rem; transition:all 0.2s; border-radius:6px; margin:2px 8px; text-decoration:none; }
        .nav-item:hover, .nav-item.active { background:rgba(85,177,174,0.15); color:#55B1AE; }
        .nav-subitem { padding-left:42px; font-size:0.8rem; }
    </style>
    @livewireStyles
    @stack('styles')
</head>
<body>
<aside class="sidebar">
    <div style="padding:20px; border-bottom:1px solid rgba(85,177,174,0.2);">
        <div style="font-family:'JetBrains Mono',ui-monospace,SFMono-Regular,Menlo,monospace; font-weight:800; letter-spacing:0.2em; line-height:1; color:#f2efe9; font-size:1.2rem; margin-bottom:6px;">GLITCH</div>
        <div style="color:#55B1AE; font-size:0.7rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em;">Admin Panel</div>
    </div>
    <div class="sidebar-scroll">
    <nav style="padding:12px 0;">
        <a href="/admin" class="nav-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">&#128202; Dashboard</a>
        <a href="/admin/students" class="nav-item {{ request()->routeIs('admin.students.*') ? 'active' : '' }}">&#128101; Discenti</a>
        <a href="{{ route('admin.instructors.index') }}" class="nav-item {{ request()->routeIs('admin.instructors.*') ? 'active' : '' }}">&#127979; Formatori</a>
        <a href="{{ route('admin.scuole.index') }}" class="nav-item {{ request()->routeIs('admin.scuole.*') ? 'active' : '' }}">&#127979; Scuole</a>
        <a href="/admin/certificates/signatures" class="nav-item {{ request()->routeIs('admin.certificates.signatures.*') ? 'active' : '' }}">&#9997; Firma Certificati</a>
        {{-- Gruppo Corsi: espandibile (Alpine), auto-aperto se la rotta corrente
             è una di quelle raggruppate, così l'utente vede dov'è. --}}
        @php
            $coursesGroupActive = request()->routeIs('admin.courses.*')
                || request()->routeIs('admin.course-categories.*')
                || request()->routeIs('admin.course-tags.*')
                || request()->routeIs('admin.freshness.*')
                || request()->routeIs('admin.sources.*')
                || request()->routeIs('admin.coverage.*');
        @endphp
        <div x-data="{ open: {{ $coursesGroupActive ? 'true' : 'false' }} }">
            <button type="button" @click="open = !open"
                    class="nav-item {{ $coursesGroupActive ? 'active' : '' }}"
                    style="width:calc(100% - 16px); background:none; border:none; cursor:pointer; justify-content:space-between;">
                <span style="display:flex; align-items:center; gap:10px;">&#128218; Corsi</span>
                <span x-show="!open" style="font-size:0.7rem;">&#9656;</span>
                <span x-show="open" x-cloak style="font-size:0.7rem;">&#9662;</span>
            </button>
            <div x-show="open" x-cloak>
                <a href="/admin/courses" class="nav-item nav-subitem {{ request()->routeIs('admin.courses.*') ? 'active' : '' }}">Tutti i corsi</a>
                <a href="{{ route('admin.course-categories.index') }}" class="nav-item nav-subitem {{ request()->routeIs('admin.course-categories.*') ? 'active' : '' }}">Categorie</a>
                <a href="{{ route('admin.course-tags.index') }}" class="nav-item nav-subitem {{ request()->routeIs('admin.course-tags.*') ? 'active' : '' }}">Tag</a>
                <a href="{{ route('admin.freshness.proposals.index') }}" class="nav-item nav-subitem {{ request()->routeIs('admin.freshness.*') ? 'active' : '' }}">Aggiornamenti</a>
                @if(config('services.p26.enabled'))
                <a href="{{ route('admin.sources.index') }}" class="nav-item nav-subitem {{ request()->routeIs('admin.sources.*') ? 'active' : '' }}">Fonti attendibili</a>
                <a href="{{ route('admin.coverage.index') }}" class="nav-item nav-subitem {{ request()->routeIs('admin.coverage.*') ? 'active' : '' }}">Copertura</a>
                @endif
            </div>
        </div>
        <a href="/admin/quizzes" class="nav-item {{ request()->routeIs('admin.quizzes.*') ? 'active' : '' }}">&#128221; Quiz</a>
        <a href="/admin/rag" class="nav-item {{ request()->routeIs('admin.rag.*') ? 'active' : '' }}">&#129504; Documenti AI</a>
        <a href="/admin/knowledge-base" class="nav-item {{ request()->routeIs('admin.knowledge-base.*') ? 'active' : '' }}">📓 Knowledge Base</a>
        <a href="/admin/analytics" class="nav-item {{ request()->routeIs('admin.analytics') ? 'active' : '' }}">&#128200; Analytics</a>
        <a href="{{ route('admin.admins.index') }}" class="nav-item {{ request()->routeIs('admin.admins.*') ? 'active' : '' }}">&#128737; Amministratori</a>
        <a href="{{ route('admin.security.2fa.show') }}" class="nav-item {{ request()->routeIs('admin.security.2fa.*') ? 'active' : '' }}">&#128274; Sicurezza 2FA</a>
        <a href="{{ route('admin.settings.index') }}" class="nav-item {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">&#9881; Impostazioni</a>
    </nav>
    </div>{{-- /.sidebar-scroll --}}
    <div class="sidebar-footer">
        <div style="color:#8A9696; font-size:0.75rem; margin-bottom:8px;">{{ session('admin_email') }}</div>
        <form method="POST" action="/admin/logout">
            @csrf
            <button type="submit" style="width:100%; padding:7px; background:rgba(226,138,83,0.1); color:#E28A53; border:1px solid rgba(226,138,83,0.3); border-radius:6px; font-size:0.8rem; cursor:pointer;">
                Esci
            </button>
        </form>
    </div>
</aside>

<div class="main-content">
    <div style="background:white; padding:12px 24px; border-bottom:1px solid #C8D0D0; display:flex; align-items:center; justify-content:space-between;">
        <div style="font-size:0.9rem; font-weight:600; color:#1A1F1F;">@yield('title', 'Dashboard')</div>
        <div style="font-size:0.8rem; color:#8A9696;">{{ atheneum_setting('instance_name', 'Officina') }} — Area Admin</div>
    </div>

    @if(session('success'))
    <div style="margin:16px 24px; padding:12px 16px; background:#E8F5F5; border-left:4px solid #55B1AE; border-radius:6px; color:#3A8C89; font-size:0.875rem;">
        &#10003; {{ session('success') }}
    </div>
    @endif

    @if(session('error'))
    <div style="margin:16px 24px; padding:12px 16px; background:#fff3ec; border-left:4px solid #E28A53; border-radius:6px; color:#c97a45; font-size:0.875rem;">
        &#9888; {{ session('error') }}
    </div>
    @endif

    <div style="padding:24px;">
        @yield('content')
    </div>
</div>

@livewireScripts
@stack('scripts')
</body>
</html>
