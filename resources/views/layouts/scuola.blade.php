<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Segreteria') — {{ $branding->instanceName() }}</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <script src="https://cdn.tailwindcss.com/3.4.1"></script>
    @include('layouts.partials._rail-styles')
    <style>
        @keyframes nosc-spin { to { transform: rotate(360deg); } }
        .nosc-spin { display:inline-block; width:13px; height:13px; border:2px solid rgba(255,255,255,0.5); border-top-color:#fff; border-radius:50%; animation: nosc-spin 0.7s linear infinite; vertical-align:-2px; margin-right:6px; }
        button[type="submit"][disabled] { opacity:0.65; cursor:progress; }
    </style>
    @stack('styles')
</head>
<body>

<aside class="rail">
    <a href="{{ route('scuola.dashboard') }}" class="rail-mono" title="{{ $branding->instanceName() }} — Segreteria">GL</a>

    <div class="rail-scroll">
        @include('layouts.partials._rail-item', [
            'href' => route('scuola.dashboard'), 'icon' => 'dashboard', 'title' => 'Dashboard',
            'active' => request()->routeIs('scuola.dashboard'),
        ])
        @include('layouts.partials._rail-item', [
            'href' => route('scuola.docenti.index'), 'icon' => 'teachers', 'title' => 'Docenti',
            'active' => request()->routeIs('scuola.docenti.*'),
        ])
        @include('layouts.partials._rail-item', [
            'href' => route('scuola.studenti.index'), 'icon' => 'students', 'title' => 'Studenti',
            'active' => request()->routeIs('scuola.studenti.*'),
        ])
        @include('layouts.partials._rail-item', [
            'href' => route('scuola.classi.index'), 'icon' => 'classes', 'title' => 'Classi',
            'active' => request()->routeIs('scuola.classi.*') || request()->routeIs('scuola.cattedre.*'),
        ])
        @include('layouts.partials._rail-item', [
            'href' => route('scuola.materiali.index'), 'icon' => 'materials', 'title' => 'Materiali',
            'active' => request()->routeIs('scuola.materiali.*'),
        ])
        @include('layouts.partials._rail-item', [
            'href' => route('scuola.privacy.index'), 'icon' => 'privacy', 'title' => 'Privacy',
            'active' => request()->routeIs('scuola.privacy.*'),
        ])

        <div class="rail-sep"></div>

        @include('layouts.partials._rail-item', [
            'href' => route('scuola.anagrafica.edit'), 'icon' => 'anagrafica', 'title' => 'Anagrafica & branding',
            'active' => request()->routeIs('scuola.anagrafica.*'),
        ])
    </div>{{-- /.rail-scroll --}}

    <div class="rail-footer">
        @if($identity['professor'] ?? false)
            @include('layouts.partials._rail-item', ['href' => route('docente.dashboard'), 'icon' => 'teachers', 'title' => 'Cambia contesto: Area docente'])
        @endif
        @if($identity['courses'] ?? false)
            @include('layouts.partials._rail-item', ['href' => route('student.dashboard'), 'icon' => 'course', 'title' => 'Cambia contesto: I miei corsi'])
        @endif

        <div class="rail-avatar" title="{{ session('student_name') }} · {{ session('student_email') }} (Segreteria)">
            {{ strtoupper(substr(session('student_name', 'S'), 0, 1)) }}
        </div>
        <form method="POST" action="/learn/logout">
            @csrf
            <button type="submit" class="rail-item" title="Esci">
                @include('layouts.partials._icon', ['name' => 'logout'])
            </button>
        </form>
    </div>
</aside>

<div class="main-content">
    <div style="background:white; padding:12px 24px; border-bottom:1px solid #C8D0D0; display:flex; align-items:center; gap:12px;">
        <button onclick="document.querySelector('.rail').classList.toggle('open')" class="mobile-toggle" style="display:none; background:none; border:none; cursor:pointer; color:#55B1AE; font-size:1.2rem;">&#9776;</button>
        <div style="font-size:0.875rem; color:#8A9696;">@yield('breadcrumb', 'Segreteria')</div>
    </div>

    @include('layouts.partials._flash')

    <div style="padding:24px;">
        @yield('content')
    </div>
</div>
<script>
document.addEventListener('submit', function (e) {
    const form = e.target;
    if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-async')) return;
    if (form.dataset.submitting === '1') { e.preventDefault(); return; }
    form.dataset.submitting = '1';
    const btn = form.querySelector('button[type="submit"], button:not([type])');
    if (btn) {
        const label = btn.getAttribute('data-busy-label') || 'Attendere…';
        btn.dataset.originalHtml = btn.innerHTML;
        btn.innerHTML = '<span class="nosc-spin"></span>' + label;
        setTimeout(function () { btn.disabled = true; }, 0);
    }
}, true);
</script>
@stack('scripts')
</body>
</html>
