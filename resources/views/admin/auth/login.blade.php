<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — Atheneum Noscite</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <script src="https://cdn.tailwindcss.com/3.4.1"></script>
</head>
<body style="background:#1A1F1F; min-height:100vh; display:flex; align-items:center; justify-content:center;">
    <div style="background:#252B2B; border-radius:12px; padding:40px; width:100%; max-width:400px; border:1px solid rgba(85,177,174,0.2);">
        <div style="text-align:center; margin-bottom:32px;">
            <img src="/images/logo.png" alt="Noscite" style="height:40px; filter:brightness(0) invert(1); margin:0 auto 12px;">
            <div style="color:#55B1AE; font-weight:700; font-size:1.1rem;">Atheneum Admin</div>
            <div style="color:#8A9696; font-size:0.8rem;">Accesso riservato</div>
        </div>

        @if(session('error'))
        <div style="background:rgba(226,82,82,0.12); border:1px solid rgba(226,82,82,0.5); border-radius:8px; padding:12px; margin-bottom:20px; color:#fca5a5; font-size:0.875rem;">
            {{ session('error') }}
        </div>
        @endif

        @if($errors->any())
        <div style="background:rgba(226,138,83,0.15); border:1px solid #E28A53; border-radius:8px; padding:12px; margin-bottom:20px; color:#E28A53; font-size:0.875rem;">
            {{ $errors->first() }}
        </div>
        @endif

        <a href="{{ route('admin.microsoft.redirect') }}"
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
                    margin:16px 0; position:relative;">
            <span style="background:#252B2B; padding:0 12px; position:relative; z-index:1;">
                oppure con email
            </span>
            <div style="position:absolute; top:50%; left:0; right:0;
                        height:1px; background:rgba(232,237,237,0.15); z-index:0;"></div>
        </div>

        <form method="POST" action="/admin/login">
            @csrf
            <div style="margin-bottom:16px;">
                <label style="color:#8A9696; font-size:0.8rem; display:block; margin-bottom:6px;">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" required
                       style="width:100%; padding:10px 14px; background:#1A1F1F; border:1px solid rgba(85,177,174,0.3); border-radius:8px; color:#E8EDED; font-size:0.875rem; outline:none;">
            </div>
            <div style="margin-bottom:24px;">
                <label style="color:#8A9696; font-size:0.8rem; display:block; margin-bottom:6px;">Password</label>
                <input type="password" name="password" required
                       style="width:100%; padding:10px 14px; background:#1A1F1F; border:1px solid rgba(85,177,174,0.3); border-radius:8px; color:#E8EDED; font-size:0.875rem; outline:none;">
            </div>
            <button type="submit" style="width:100%; padding:12px; background:#55B1AE; color:white; border:none; border-radius:8px; font-weight:700; cursor:pointer; font-size:0.9rem;">
                Accedi
            </button>
        </form>
    </div>
</body>
</html>
