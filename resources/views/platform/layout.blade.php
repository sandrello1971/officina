<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Enti') — Officina piattaforma</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <script src="https://cdn.tailwindcss.com/3.4.1"></script>
    <style>body { font-family: 'Calibri', system-ui, sans-serif; background:#F5F7F7; color:#1A1F1F; }</style>
</head>
<body>
<header style="background:#1A1F1F;" class="px-6 py-3 flex items-center justify-between">
    <a href="{{ route('platform.tenants.index') }}" class="text-sm font-bold tracking-widest" style="color:#55B1AE;">OFFICINA · PIATTAFORMA</a>
    @if(session('platform_user_id'))
    <form method="POST" action="{{ route('platform.logout') }}">
        @csrf
        <button class="text-xs" style="color:#8A9696;">Esci</button>
    </form>
    @endif
</header>
<main class="max-w-5xl mx-auto px-4 py-8">
    @if(session('success'))
        <div class="mb-4 rounded-md px-4 py-3 text-sm" style="background:#E6F4F3; color:#2B6F6C;">{{ session('success') }}</div>
    @endif
    @if(session('credentials'))
        <div class="mb-4 rounded-md px-4 py-3 text-sm" style="background:#FFF4E5; color:#7A4A12;">
            <strong>Credenziali admin (mostrate una sola volta):</strong>
            <span class="font-mono">{{ session('credentials')['email'] }}</span> /
            <span class="font-mono select-all">{{ session('credentials')['password'] }}</span>
        </div>
    @endif
    @if($errors->any())
        <div class="mb-4 rounded-md px-4 py-3 text-sm" style="background:#FDECEC; color:#8A1F1F;">
            @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
    @endif
    @yield('content')
</main>
</body>
</html>
