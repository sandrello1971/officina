<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">

    <link rel="icon" type="image/png" href="/images/logo.png">
    <link rel="shortcut icon" type="image/png" href="/images/logo.png">

    <title>@yield('title', 'Dashboard') — Noscite Admin</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#ecfeff', 100: '#cffafe', 200: '#a5f3fc', 300: '#67e8f9',
                            400: '#22d3ee', 500: '#06b6d4', 600: '#0e7490', 700: '#0e6478',
                            800: '#155e75', 900: '#164e63',
                        },
                        secondary: {
                            50: '#fffbeb', 100: '#fef3c7', 200: '#fde68a', 300: '#fcd34d',
                            400: '#fbbf24', 500: '#f59e0b', 600: '#d97706', 700: '#b45309',
                            800: '#92400e', 900: '#78350f',
                        },
                    },
                    fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] },
                },
            },
        }
    </script>

    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    @livewireStyles

    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="font-sans antialiased bg-gray-100" x-data="{ sidebarOpen: false }">

    <div class="flex h-screen overflow-hidden">

        {{-- ===== SIDEBAR ===== --}}
        {{-- Overlay mobile --}}
        <div x-show="sidebarOpen" x-cloak @click="sidebarOpen = false"
             class="fixed inset-0 z-30 bg-black/50 lg:hidden"
             x-transition:enter="transition-opacity duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        </div>

        <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
               class="fixed inset-y-0 left-0 z-40 w-64 bg-gray-900 transform transition-transform duration-200 ease-in-out lg:translate-x-0 lg:static lg:inset-auto lg:z-auto flex flex-col">

            {{-- Logo --}}
            <div class="flex items-center justify-between h-16 px-6 border-b border-gray-800">
                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-primary-600 rounded-lg flex items-center justify-center text-white font-bold text-sm">N</div>
                    <span class="text-white font-semibold text-lg">Noscite</span>
                </a>
                <button @click="sidebarOpen = false" class="lg:hidden text-gray-400 hover:text-white">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Nav --}}
            <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
                <a href="{{ route('admin.dashboard') }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                          {{ request()->routeIs('admin.dashboard') ? 'bg-primary-600/20 text-primary-400' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    Dashboard
                </a>

                <a href="{{ route('admin.dashboard') }}#blog"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-gray-300 hover:bg-gray-800 hover:text-white transition-colors">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/></svg>
                    Blog
                </a>

                <a href="{{ route('admin.contacts') }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                          {{ request()->routeIs('admin.contacts') ? 'bg-primary-600/20 text-primary-400' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    Messaggi
                </a>

                <a href="{{ route('admin.dashboard') }}#newsletter"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-gray-300 hover:bg-gray-800 hover:text-white transition-colors">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                    Newsletter
                </a>
            </nav>

            {{-- Bottom --}}
            <div class="px-3 py-4 border-t border-gray-800">
                <a href="{{ route('home') }}" target="_blank"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-gray-400 hover:bg-gray-800 hover:text-white transition-colors">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    Vedi sito
                </a>
                <form method="POST" action="/nosciteadmin/logout">
                    @csrf
                    <button type="submit"
                            class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-gray-400 hover:bg-red-900/30 hover:text-red-400 transition-colors">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        Logout
                    </button>
                </form>
            </div>
        </aside>

        {{-- ===== MAIN ===== --}}
        <div class="flex-1 flex flex-col overflow-hidden">

            {{-- Top header --}}
            <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-4 sm:px-6 lg:px-8 flex-shrink-0">
                <button @click="sidebarOpen = true" class="lg:hidden text-gray-500 hover:text-gray-700">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>

                <h1 class="text-lg font-semibold text-gray-900 hidden lg:block">@yield('page-title', 'Dashboard')</h1>

                <div class="flex items-center gap-4">
                    @php $adminUser = session('admin_user'); @endphp
                    <div class="flex items-center gap-2">
                        @if($adminUser['avatar'] ?? null)
                        <img src="{{ $adminUser['avatar'] }}" alt="" class="w-8 h-8 rounded-full object-cover">
                        @else
                        <div class="w-8 h-8 bg-primary-100 text-primary-700 rounded-full flex items-center justify-center text-sm font-semibold">
                            {{ strtoupper(substr($adminUser['name'] ?? 'A', 0, 1)) }}
                        </div>
                        @endif
                        <span class="text-sm font-medium text-gray-700 hidden sm:block">{{ $adminUser['name'] ?? 'Admin' }}</span>
                    </div>
                </div>
            </header>

            {{-- Content --}}
            <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
                @yield('content')
            </main>
        </div>
    </div>

    @livewireScripts
    @stack('scripts')
</body>
</html>
