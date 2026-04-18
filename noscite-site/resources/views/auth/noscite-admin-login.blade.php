<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>Admin Login — Noscite</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: { 500: '#06b6d4', 600: '#0e7490', 700: '#0e6478' },
                    },
                    fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] },
                },
            },
        }
    </script>
</head>
<body class="font-sans antialiased bg-gray-900 min-h-screen flex items-center justify-center px-4">

    <div class="w-full max-w-sm">
        {{-- Logo --}}
        <div class="text-center mb-8">
            <div class="w-14 h-14 mx-auto bg-primary-600 rounded-2xl flex items-center justify-center text-white font-bold text-2xl mb-4">N</div>
            <h1 class="text-2xl font-bold text-white">Noscite Admin</h1>
            <p class="text-gray-500 text-sm mt-1">Accedi al pannello di amministrazione</p>
        </div>

        {{-- Errori sessione --}}
        @if($errors->any())
            <div class="mb-4 p-3 bg-red-900/30 border border-red-800 rounded-lg">
                @foreach($errors->all() as $error)
                    <p class="text-sm text-red-400">{{ $error }}</p>
                @endforeach
            </div>
        @endif

        {{-- Form --}}
        <form method="POST" action="{{ route('admin.login.submit') }}" class="bg-gray-800 rounded-2xl p-6 shadow-xl border border-gray-700 space-y-5">
            @csrf

            <div>
                <label for="email" class="block text-sm font-medium text-gray-300 mb-1">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                       class="w-full px-4 py-2.5 bg-gray-900 border border-gray-700 rounded-lg text-white placeholder-gray-500 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 text-sm">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-300 mb-1">Password</label>
                <input id="password" name="password" type="password" required
                       class="w-full px-4 py-2.5 bg-gray-900 border border-gray-700 rounded-lg text-white placeholder-gray-500 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 text-sm">
            </div>

            <div class="flex items-center">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input name="remember" type="checkbox" class="h-4 w-4 rounded border-gray-600 bg-gray-900 text-primary-600 focus:ring-primary-500">
                    <span class="text-sm text-gray-400">Ricordami</span>
                </label>
            </div>

            <button type="submit"
                    class="w-full px-4 py-2.5 text-sm font-semibold text-white bg-primary-600 rounded-lg hover:bg-primary-700 transition-colors">
                Accedi
            </button>
        </form>

        <p class="text-center mt-6 text-xs text-gray-600">
            <a href="{{ route('home') }}" class="hover:text-gray-400 transition-colors">← Torna al sito</a>
        </p>
    </div>

</body>
</html>
