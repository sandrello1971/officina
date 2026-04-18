<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Accedi — Atheneum Noscite</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body style="font-family:Calibri,system-ui,sans-serif;background:#1A1F1F;color:white;min-height:100vh;position:relative;overflow:hidden">
    <div style="position:absolute;inset:0;background-image:url('/images/atheneum_new.png');background-size:cover;background-position:center;opacity:0.08;z-index:0" aria-hidden="true"></div>

    <div style="position:relative;z-index:1" class="min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-sm">
            <div class="text-center mb-8">
                <img src="/images/logo.png" alt="Noscite" class="h-12 w-auto mx-auto mb-4 brightness-0 invert">
                <h1 class="text-2xl font-bold" style="color:#55B1AE">Atheneum Noscite</h1>
                <p class="text-sm mt-1" style="color:#8A9696">Area studenti — accesso riservato</p>
            </div>

            @if($errors->any())
            <div class="mb-4 p-3 rounded-lg text-sm" style="background:rgba(255,100,100,0.1);border:1px solid rgba(255,100,100,0.3);color:#fca5a5">
                @foreach($errors->all() as $err)<div>{{ $err }}</div>@endforeach
            </div>
            @endif

            <form method="POST" action="{{ route('student.login.post') }}" class="rounded-2xl p-6 space-y-5" style="background:rgba(42,47,47,0.95);border:1px solid #3a3f3f;backdrop-filter:blur(10px)">
                @csrf
                <div>
                    <label class="block text-sm font-medium mb-1" style="color:#ccc">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus class="w-full px-4 py-2.5 rounded-lg text-sm" style="background:#1A1F1F;border:1px solid #3a3f3f;color:white">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" style="color:#ccc">Password</label>
                    <input type="password" name="password" required class="w-full px-4 py-2.5 rounded-lg text-sm" style="background:#1A1F1F;border:1px solid #3a3f3f;color:white">
                </div>
                <button type="submit" class="w-full px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors" style="background:#55B1AE;color:white" onmouseover="this.style.background='#3A8C89'" onmouseout="this.style.background='#55B1AE'">
                    Accedi
                </button>
            </form>

            <p class="text-center mt-6 text-xs" style="color:#8A9696">
                Problemi? Scrivi a <a href="mailto:info@noscite.it" style="color:#55B1AE">info@noscite.it</a>
            </p>
            <p class="text-center mt-3 text-xs">
                <a href="/" style="color:#555">&larr; Torna al sito</a>
            </p>
        </div>
    </div>
</body>
</html>
