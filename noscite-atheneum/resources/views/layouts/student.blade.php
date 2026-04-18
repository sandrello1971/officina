<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Atheneum') — Atheneum Noscite</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <script src="https://cdn.tailwindcss.com/3.4.1"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>
        [x-cloak] { display: none !important; }
        body { font-family: 'Calibri', system-ui, sans-serif; }
        .sidebar { width: 260px; min-height: 100vh; background: #1A1F1F; position: fixed; left: 0; top: 0; bottom: 0; overflow-y: auto; z-index: 40; }
        .main-content { margin-left: 260px; min-height: 100vh; background: #F5F7F7; }
        .nav-item { display: flex; align-items: center; gap: 10px; padding: 10px 20px; color: #8A9696; font-size: 0.875rem; transition: all 0.2s; border-radius: 6px; margin: 2px 8px; text-decoration:none; }
        .nav-item:hover { background: rgba(85,177,174,0.1); color: #55B1AE; }
        .nav-item.active { background: rgba(85,177,174,0.15); color: #55B1AE; font-weight: 600; }
        .progress-bar { height: 6px; background: #C8D0D0; border-radius: 3px; overflow: hidden; }
        .progress-fill { height: 100%; background: #55B1AE; border-radius: 3px; transition: width 0.3s; }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); transition: transform 0.3s; }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .mobile-toggle { display: inline-flex !important; }
        }
    </style>
    @livewireStyles
</head>
<body>

<aside class="sidebar">
    <div style="padding: 24px 20px; border-bottom: 1px solid rgba(85,177,174,0.2);">
        <img src="/images/logo.png" alt="Noscite" style="height:36px; filter:brightness(0) invert(1); margin-bottom:8px;">
        <div style="color:#55B1AE; font-size:0.75rem; font-weight:700; letter-spacing:0.1em; text-transform:uppercase;">Atheneum</div>
        <div style="color:#8A9696; font-size:0.7rem; font-style:italic;">In digit&#x101;l&#x12B; nova virt&#x16B;s</div>
    </div>

    <div style="padding: 16px 20px; border-bottom: 1px solid rgba(85,177,174,0.1);">
        <div style="width:36px; height:36px; border-radius:50%; background:#55B1AE; display:flex; align-items:center; justify-content:center; color:white; font-weight:700; font-size:0.875rem; margin-bottom:8px;">
            {{ strtoupper(substr(session('student_name', 'S'), 0, 1)) }}
        </div>
        <div style="color:#E8EDED; font-size:0.8rem; font-weight:600;">{{ session('student_name') }}</div>
        <div style="color:#8A9696; font-size:0.7rem;">{{ session('student_email') }}</div>
    </div>

    <nav style="padding: 12px 0;">
        <a href="/learn/dashboard" class="nav-item {{ request()->routeIs('student.dashboard') ? 'active' : '' }}">
            <span>&#9632;</span> Dashboard
        </a>

        @php
            $sidebarStudent = \App\Models\Student::with(['courses' => fn($q) => $q->wherePivot('is_active', true)->orderBy('sort_order')])->find(session('student_id'));
            $firstCourse = $sidebarStudent?->courses->first();
        @endphp

        @if($sidebarStudent)
            @foreach($sidebarStudent->courses as $sidebarCourse)
            <a href="/learn/course/{{ $sidebarCourse->slug }}"
               class="nav-item {{ request()->is('learn/course/'.$sidebarCourse->slug.'*') ? 'active' : '' }}">
                <span>{{ $sidebarCourse->icon }}</span>
                <span>{{ $sidebarCourse->name }}</span>
            </a>
            @endforeach
        @endif

        <div style="margin: 16px 8px 4px; padding: 0 12px;">
            <div style="color:#4A5252; font-size:0.65rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em;">Supporto</div>
        </div>
        @if($firstCourse)
        <a href="/learn/chat/{{ $firstCourse->slug }}"
           class="nav-item {{ request()->routeIs('student.chat.*') ? 'active' : '' }}">
            <span>&#10022;</span> Assistente AI
        </a>
        @endif
    </nav>

    <div style="position:absolute; bottom:0; left:0; right:0; padding:16px 20px; border-top:1px solid rgba(85,177,174,0.1);">
        <form method="POST" action="/learn/logout">
            @csrf
            <button type="submit" style="width:100%; padding:8px; background:rgba(226,138,83,0.1); color:#E28A53; border:1px solid rgba(226,138,83,0.3); border-radius:6px; font-size:0.8rem; cursor:pointer;">
                Esci
            </button>
        </form>
    </div>
</aside>

<div class="main-content">
    <div style="background:white; padding:12px 24px; border-bottom:1px solid #C8D0D0; display:flex; align-items:center; gap:12px;">
        <button onclick="document.querySelector('.sidebar').classList.toggle('open')" class="mobile-toggle" style="display:none; background:none; border:none; cursor:pointer; color:#55B1AE; font-size:1.2rem;">&#9776;</button>
        <div style="font-size:0.875rem; color:#8A9696;">
            @yield('breadcrumb', 'Dashboard')
        </div>
    </div>

    @if(session('success'))
    <div style="margin:16px 24px; padding:12px 16px; background:#E8F5F5; border-left:4px solid #55B1AE; border-radius:6px; color:#3A8C89; font-size:0.875rem;">
        &#10003; {{ session('success') }}
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
