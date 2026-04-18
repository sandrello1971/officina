<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Admin Noscite — Accesso</title>
    <link rel="icon" type="image/png" href="/images/logo.png">
    <link rel="shortcut icon" type="image/png" href="/images/logo.png">
    <script src="https://cdn.tailwindcss.com/3.4.1"></script>
</head>
<body style="background:#1A1F1F; min-height:100vh; display:flex; align-items:center; justify-content:center;">
    <div style="background:#252B2B; border-radius:16px; padding:48px 40px; width:100%; max-width:420px; border:1px solid rgba(85,177,174,0.2); text-align:center;">

        <img src="/images/logo.png" alt="Noscite" style="height:48px; filter:brightness(0) invert(1); margin:0 auto 16px;">
        <div style="color:#55B1AE; font-weight:700; font-size:1.1rem; margin-bottom:4px;">Admin Noscite</div>
        <div style="color:#8A9696; font-size:0.8rem; margin-bottom:32px; font-style:italic;">Area riservata</div>

        @if(session('error'))
        <div style="background:rgba(226,138,83,0.15); border:1px solid #E28A53; border-radius:8px; padding:12px; margin-bottom:20px; color:#E28A53; font-size:0.875rem;">
            {{ session('error') }}
        </div>
        @endif

        <a href="/nosciteadmin/auth/redirect"
           style="display:flex; align-items:center; justify-content:center; gap:12px; width:100%; padding:14px; background:white; color:#1A1F1F; border-radius:10px; font-weight:700; text-decoration:none; font-size:0.95rem;"
           onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">
            <svg width="20" height="20" viewBox="0 0 21 21" fill="none">
                <rect x="1" y="1" width="9" height="9" fill="#F25022"/>
                <rect x="11" y="1" width="9" height="9" fill="#7FBA00"/>
                <rect x="1" y="11" width="9" height="9" fill="#00A4EF"/>
                <rect x="11" y="11" width="9" height="9" fill="#FFB900"/>
            </svg>
            Accedi con Microsoft 365
        </a>

        <div style="margin-top:16px; color:#4A5252; font-size:0.75rem;">
            Accesso riservato agli amministratori Noscite
        </div>

        <div style="margin-top:24px; padding-top:16px; border-top:1px solid rgba(85,177,174,0.1);">
            <a href="https://noscite.it" style="color:#8A9696; font-size:0.8rem; text-decoration:none;">← Torna al sito</a>
        </div>
    </div>
</body>
</html>
