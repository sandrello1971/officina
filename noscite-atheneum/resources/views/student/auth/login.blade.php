<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Accedi — {{ atheneum_setting('instance_name', 'Atheneum') }}</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body style="font-family:Calibri,system-ui,sans-serif;background:#1A1F1F;color:white;min-height:100vh;position:relative;overflow:hidden">
    <div style="position:absolute;inset:0;background-image:url('/images/atheneum_new.png');background-size:cover;background-position:center;opacity:0.08;z-index:0" aria-hidden="true"></div>

    <div style="position:relative;z-index:1" class="min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-sm">
            <div class="text-center mb-8">
                <img src="/images/logo.png" alt="{{ atheneum_setting('platform_owner', 'Noscite Srl') }}" class="h-12 w-auto mx-auto mb-4 brightness-0 invert">
                <h1 class="text-2xl font-bold" style="color:#55B1AE">{{ atheneum_setting('instance_name', 'Atheneum') }}</h1>
                <p class="text-sm mt-1" style="color:#8A9696">Area studenti — accesso riservato</p>
            </div>

            @if(session('error'))
            <div style="padding:10px 14px; background:rgba(226,82,82,0.1);
                        border:1px solid rgba(226,82,82,0.4); border-radius:8px;
                        color:#fca5a5; font-size:0.85rem; margin-bottom:16px;">
                {{ session('error') }}
            </div>
            @endif

            @if($errors->any())
            <div class="mb-4 p-3 rounded-lg text-sm" style="background:rgba(255,100,100,0.1);border:1px solid rgba(255,100,100,0.3);color:#fca5a5">
                @foreach($errors->all() as $err)<div>{{ $err }}</div>@endforeach
            </div>
            @endif

            <a href="{{ route('student.microsoft.redirect') }}"
               style="display:flex; align-items:center; justify-content:center;
                      gap:10px; width:100%; padding:12px 16px; background:white;
                      border:1px solid #C8D0D0; border-radius:8px; color:#1A1F1F;
                      text-decoration:none; font-weight:600; margin-bottom:16px;
                      transition:all 0.2s;"
               onmouseover="this.style.borderColor='#55B1AE'"
               onmouseout="this.style.borderColor='#C8D0D0'">
                <svg width="20" height="20" viewBox="0 0 23 23" style="flex-shrink:0;">
                    <rect width="10" height="10" fill="#f25022"/>
                    <rect x="12" width="10" height="10" fill="#7fba00"/>
                    <rect y="12" width="10" height="10" fill="#00a4ef"/>
                    <rect x="12" y="12" width="10" height="10" fill="#ffb900"/>
                </svg>
                <span>Accedi con Microsoft</span>
            </a>

            <div style="text-align:center; color:#8A9696; font-size:0.8rem;
                        margin:16px 0 16px 0; position:relative;">
                <span style="background:#1A1F1F; padding:0 12px; position:relative; z-index:1;">
                    oppure con email
                </span>
                <div style="position:absolute; top:50%; left:0; right:0;
                            height:1px; background:rgba(232,237,237,0.15); z-index:0;"></div>
            </div>

            @if(request('demo'))
            <div style="margin-bottom:20px; padding:14px 16px; background:rgba(226,138,83,0.15);
                 border:1px solid rgba(226,138,83,0.4); border-radius:10px;">
                <div style="color:#E28A53; font-weight:700; font-size:0.85rem; margin-bottom:6px;">
                    ✦ Accesso Demo
                </div>
                <div style="color:#8A9696; font-size:0.8rem; margin-bottom:10px;">
                    Usa queste credenziali per esplorare la piattaforma:
                </div>
                <div style="background:rgba(0,0,0,0.2); border-radius:8px; padding:10px 14px;
                     font-family:monospace; font-size:0.85rem;">
                    <div style="color:#E8EDED; margin-bottom:4px;">
                        📧 <span style="color:#55B1AE;">{{ atheneum_setting('demo_user_email', 'demo@atheneum.noscite.it') }}</span>
                    </div>
                    <div style="color:#E8EDED;">
                        🔑 <span style="color:#55B1AE;">Demo2024</span>
                    </div>
                </div>
                <button onclick="
                    document.querySelector('input[name=email]').value='{{ atheneum_setting('demo_user_email', 'demo@atheneum.noscite.it') }}';
                    document.querySelector('input[name=password]').value='Demo2024';
                " style="margin-top:10px; width:100%; padding:8px; background:#E28A53; color:white;
                         border:none; border-radius:6px; font-size:0.8rem; font-weight:700; cursor:pointer;">
                    ↓ Compila automaticamente e accedi
                </button>
            </div>
            @endif

            <form method="POST" action="{{ route('student.login.post') }}" class="rounded-2xl p-6 space-y-5" style="background:rgba(42,47,47,0.95);border:1px solid #3a3f3f;backdrop-filter:blur(10px)">
                @csrf
                <div>
                    <label class="block text-sm font-medium mb-1" style="color:#ccc">Email o username</label>
                    <input type="text" name="email" value="{{ old('email') }}" required autofocus autocapitalize="none" class="w-full px-4 py-2.5 rounded-lg text-sm" style="background:#1A1F1F;border:1px solid #3a3f3f;color:white">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" style="color:#ccc">Password</label>
                    <input type="password" name="password" required class="w-full px-4 py-2.5 rounded-lg text-sm" style="background:#1A1F1F;border:1px solid #3a3f3f;color:white">
                </div>
                <button type="submit" class="w-full px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors" style="background:#55B1AE;color:white" onmouseover="this.style.background='#3A8C89'" onmouseout="this.style.background='#55B1AE'">
                    Accedi
                </button>
            </form>

            @php
                $supportEmail = atheneum_setting('mail_from_address', 'info@noscite.it');
                $ownerUrl = atheneum_setting('platform_owner_url', 'https://atheneum.noscite.it');
                $ownerName = atheneum_setting('platform_owner', 'Atheneum');
            @endphp
            <p class="text-center mt-6 text-xs" style="color:#8A9696">
                Problemi? Scrivi a <a href="mailto:{{ $supportEmail }}" style="color:#55B1AE">{{ $supportEmail }}</a>
            </p>
            @if($ownerUrl)
            <div style="text-align:center; margin-top:16px; padding-top:16px; border-top:1px solid rgba(85,177,174,0.15);">
                <a href="{{ $ownerUrl }}"
                   style="color:#8A9696; font-size:0.8rem; text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
                    ← Torna al sito {{ $ownerName }}
                </a>
            </div>
            @endif
        </div>
    </div>
</body>
</html>
