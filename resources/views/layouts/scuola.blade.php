<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Segreteria') — {{ $branding->instanceName() }}</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <script src="https://cdn.tailwindcss.com/3.4.1"></script>
    @include('layouts.partials._topbar-styles')
    <style>
        @keyframes nosc-spin { to { transform: rotate(360deg); } }
        .nosc-spin { display:inline-block; width:13px; height:13px; border:2px solid rgba(255,255,255,0.5); border-top-color:#fff; border-radius:50%; animation: nosc-spin 0.7s linear infinite; vertical-align:-2px; margin-right:6px; }
        button[type="submit"][disabled] { opacity:0.65; cursor:progress; }
    </style>
    @stack('styles')
</head>
<body>

<nav class="topbar">
    <button type="button" class="mobile-toggle" data-toggle="#scuola-nav" title="Menu" aria-label="Menu">
        @include('layouts.partials._icon', ['name' => 'dashboard', 'size' => 22])
    </button>
    <a href="{{ route('scuola.dashboard') }}" class="topbar-brand" title="{{ $branding->instanceName() }} — Segreteria">
        <img src="{{ $branding->logoUrl() }}" alt="{{ $branding->ownerLabel() }}">
    </a>

    <div class="topbar-nav" id="scuola-nav">
        @include('layouts.partials._topbar-item', [
            'href' => route('scuola.dashboard'), 'label' => 'Dashboard', 'icon' => 'dashboard',
            'active' => request()->routeIs('scuola.dashboard'),
        ])
        @include('layouts.partials._topbar-item', [
            'href' => route('scuola.docenti.index'), 'label' => 'Docenti', 'icon' => 'teachers',
            'active' => request()->routeIs('scuola.docenti.*'),
        ])
        @include('layouts.partials._topbar-item', [
            'href' => route('scuola.studenti.index'), 'label' => 'Studenti', 'icon' => 'students',
            'active' => request()->routeIs('scuola.studenti.*'),
        ])
        @include('layouts.partials._topbar-item', [
            'href' => route('scuola.classi.index'), 'label' => 'Classi', 'icon' => 'classes',
            'active' => request()->routeIs('scuola.classi.*') || request()->routeIs('scuola.cattedre.*'),
        ])
        @include('layouts.partials._topbar-item', [
            'href' => route('scuola.materiali.index'), 'label' => 'Materiali', 'icon' => 'materials',
            'active' => request()->routeIs('scuola.materiali.*'),
        ])
        @include('layouts.partials._topbar-item', [
            'href' => route('scuola.privacy.index'), 'label' => 'Privacy', 'icon' => 'privacy',
            'active' => request()->routeIs('scuola.privacy.*'),
        ])
        @include('layouts.partials._topbar-item', [
            'href' => route('scuola.anagrafica.edit'), 'label' => 'Anagrafica', 'icon' => 'anagrafica',
            'active' => request()->routeIs('scuola.anagrafica.*'),
        ])
    </div>

    <div class="topbar-actions">
        <div class="topbar-usermenu">
            <button type="button" class="topbar-avatar" data-toggle="#scuola-usermenu" title="{{ session('student_name') }}">
                {{ strtoupper(substr(session('student_name', 'S'), 0, 1)) }}
            </button>
            <div id="scuola-usermenu" class="topbar-menu">
                <div class="um-head">
                    <div class="um-name">{{ session('student_name') }}</div>
                    <div class="um-email">{{ session('student_email') }} · Segreteria</div>
                </div>
                @if(($identity['professor'] ?? false) || ($identity['courses'] ?? false))
                <div class="um-label">Cambia contesto</div>
                @if($identity['professor'] ?? false)
                <a href="{{ route('docente.dashboard') }}">@include('layouts.partials._icon', ['name' => 'teachers', 'size' => 18]) Area docente</a>
                @endif
                @if($identity['courses'] ?? false)
                <a href="{{ route('student.dashboard') }}">@include('layouts.partials._icon', ['name' => 'course', 'size' => 18]) I miei corsi</a>
                @endif
                @endif
                <div class="um-label">Account</div>
                <form method="POST" action="/learn/logout">
                    @csrf
                    <button type="submit" class="um-logout">@include('layouts.partials._icon', ['name' => 'logout', 'size' => 18]) Esci</button>
                </form>
            </div>
        </div>
    </div>
</nav>

<div class="main-content">
    <div style="background:white; padding:12px 24px; border-bottom:1px solid #C8D0D0; display:flex; align-items:center; gap:12px;">
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
@include('layouts.partials._topbar-scripts')
</body>
</html>
