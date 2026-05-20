@php
    // Maschera l'email: prima lettera + *** + @dominio (es. stefano@noscite.it -> s***@noscite.it)
    $maskedEmail = null;
    if ($cert && $cert->student?->email) {
        $email = $cert->student->email;
        $at = strpos($email, '@');
        if ($at !== false) {
            $first = substr($email, 0, 1);
            $domain = substr($email, $at);
            $maskedEmail = $first . '***' . $domain;
        }
    }
@endphp
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Verifica certificato — {{ atheneum_setting('instance_name', 'Atheneum') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing:border-box; margin:0; padding:0; }
        body {
            font-family: 'Figtree', system-ui, sans-serif;
            background: #F5F7F7;
            color: #1A1F1F;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 32px 16px;
        }
        .header {
            text-align: center;
            margin-bottom: 32px;
        }
        .logo {
            font-size: 1.75rem;
            font-weight: 700;
            letter-spacing: 4px;
            color: #55B1AE;
            text-transform: uppercase;
        }
        .tagline {
            font-size: 0.75rem;
            color: #8A9696;
            font-style: italic;
            letter-spacing: 2px;
            margin-top: 4px;
        }
        .card {
            background: white;
            border-radius: 16px;
            padding: 40px 32px;
            max-width: 560px;
            width: 100%;
            box-shadow: 0 4px 24px rgba(26,31,31,0.06);
        }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 20px;
        }
        .badge-valid {
            background: #E8F5F5;
            color: #3A8C89;
        }
        .badge-invalid {
            background: #FCE8E0;
            color: #C25B2C;
        }
        h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .subtitle {
            color: #8A9696;
            font-size: 0.9rem;
            margin-bottom: 24px;
            line-height: 1.5;
        }
        .row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #F5F7F7;
            font-size: 0.875rem;
        }
        .row:last-child { border-bottom: none; }
        .row-label {
            color: #8A9696;
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 1px;
            font-weight: 600;
        }
        .row-value {
            color: #1A1F1F;
            font-weight: 600;
            text-align: right;
            max-width: 60%;
            word-break: break-word;
        }
        .code-block {
            font-family: monospace;
            font-size: 1rem;
            font-weight: 700;
            letter-spacing: 2px;
            padding: 12px 16px;
            background: #F5F7F7;
            border: 1px solid #C8D0D0;
            border-radius: 8px;
            text-align: center;
            margin: 16px 0;
            color: #1A1F1F;
        }
        .footer-link {
            text-align: center;
            margin-top: 32px;
            font-size: 0.85rem;
            color: #8A9696;
        }
        .footer-link a {
            color: #55B1AE;
            text-decoration: none;
            font-weight: 600;
        }
        .footer-link a:hover { text-decoration: underline; }
        .invalid-icon {
            font-size: 3rem;
            text-align: center;
            margin-bottom: 8px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">{{ atheneum_setting('platform_owner', 'Noscite') }}</div>
        <div class="tagline">{{ atheneum_setting('platform_tagline', 'In digitālī nova virtūs') }}</div>
    </div>

    <div class="card">
        @if($cert)
            <div class="badge badge-valid">
                <span>✓</span>
                <span>Certificato valido</span>
            </div>

            <h1>{{ $cert->student?->name ?? 'Studente' }}</h1>
            <p class="subtitle">
                Ha completato con successo il corso e ottenuto la certificazione di {{ atheneum_setting('platform_owner', 'Noscite') }}.
            </p>

            <div class="code-block">{{ $cert->code }}</div>

            <div class="row">
                <span class="row-label">Corso</span>
                <span class="row-value">{{ $cert->course?->name ?? $cert->certification_name }}</span>
            </div>
            <div class="row">
                <span class="row-label">Certificazione</span>
                <span class="row-value">{{ $cert->certification_name }}</span>
            </div>
            <div class="row">
                <span class="row-label">Data emissione</span>
                <span class="row-value">{{ $cert->issued_at->locale('it')->isoFormat('D MMMM YYYY') }}</span>
            </div>
            <div class="row">
                <span class="row-label">Punteggio</span>
                <span class="row-value">{{ $cert->score }}%</span>
            </div>
            @if($maskedEmail)
            <div class="row">
                <span class="row-label">Email titolare</span>
                <span class="row-value">{{ $maskedEmail }}</span>
            </div>
            @endif
        @else
            <div class="badge badge-invalid">
                <span>✗</span>
                <span>Codice non trovato</span>
            </div>

            <div class="invalid-icon">🔍</div>
            <h1>Certificato non trovato</h1>
            <p class="subtitle">
                Il codice <strong>{{ $code }}</strong> non corrisponde ad alcun certificato
                emesso da {{ atheneum_setting('instance_name', 'Atheneum') }}. Verifica di averlo digitato correttamente.
            </p>
        @endif
    </div>

    <div class="footer-link">
        <a href="https://www.noscite.it">noscite.it</a>
        &nbsp;·&nbsp;
        <a href="https://atheneum.noscite.it">atheneum.noscite.it</a>
    </div>
</body>
</html>
