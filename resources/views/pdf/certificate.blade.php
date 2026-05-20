<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<style>
    @page {
        size: A4 landscape;
        margin: 0;
    }
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    body {
        margin: 0;
        padding: 0;
    }
    /* PNG template come background a piena pagina. Tutto ciò che è
       statico (cornice, logo, watermark, label) sta nel PNG; sopra,
       solo i campi dinamici via position:absolute. White-label nativo:
       per cambiare brand basta cambiare il PNG. */
    .page {
        width: 297mm;
        height: 210mm;
        position: relative;
        background-image: url('{{ public_path('img/certificates/template-default.png') }}');
        background-size: 297mm 210mm;
        background-repeat: no-repeat;
        background-position: top left;
    }

    .field-student-name {
        position: absolute;
        left: 0;
        right: 0;
        top: 60mm;
        text-align: center;
        font-family: 'cormorant garamond', serif;
        font-weight: bold;
        font-style: italic;
        font-size: 36pt;
        color: #1A1F1F;
    }

    .field-course-name {
        position: absolute;
        left: 0;
        right: 0;
        top: 84mm;
        text-align: center;
        font-family: 'cormorant garamond', serif;
        font-weight: bold;
        font-size: 22pt;
        text-transform: uppercase;
        letter-spacing: 3px;
        color: #55B1AE;
    }

    .field-cert-subtitle {
        position: absolute;
        left: 0;
        right: 0;
        top: 94mm;
        text-align: center;
        font-family: 'cormorant garamond', serif;
        font-style: italic;
        font-size: 13pt;
        color: #E28A53;
    }

    /* Il PNG ha "Punteggio" come placeholder nel badge ovale.
       Copriamo con background bianco + line-height = altezza così il
       testo entra centrato verticalmente nel rettangolo. */
    .field-score {
        position: absolute;
        left: 108mm;
        top: 107mm;
        width: 81mm;
        height: 8mm;
        text-align: center;
        font-family: 'cormorant garamond', serif;
        font-style: italic;
        font-size: 13pt;
        color: #55B1AE;
        background: white;
        line-height: 8mm;
        border-radius: 10mm;
    }

    .field-date-value {
        position: absolute;
        left: 0;
        right: 0;
        top: 131mm;
        text-align: center;
        font-family: 'cormorant garamond', serif;
        font-weight: bold;
        font-size: 12pt;
        color: #1A1F1F;
    }

    .field-code-value {
        position: absolute;
        left: 0;
        right: 0;
        top: 149mm;
        text-align: center;
        font-family: 'inter', sans-serif;
        font-weight: bold;
        font-size: 11pt;
        color: #1A1F1F;
        letter-spacing: 1px;
    }

    .field-owner-value {
        position: absolute;
        left: 0;
        right: 0;
        top: 167mm;
        text-align: center;
        font-family: 'cormorant garamond', serif;
        font-weight: bold;
        font-size: 12pt;
        color: #1A1F1F;
    }

    .verify-block {
        position: absolute;
        left: 25mm;
        top: 158mm;
        width: 45mm;
        text-align: center;
    }
    .verify-block img {
        width: 22mm;
        height: 22mm;
        display: block;
        margin: 0 auto 1.5mm;
    }
    .verify-block .verify-label {
        font-family: 'inter', sans-serif;
        font-weight: bold;
        font-size: 6pt;
        color: #8A9696;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        margin-bottom: 0.5mm;
    }
    .verify-block .verify-url {
        font-family: 'inter', sans-serif;
        font-size: 5.5pt;
        color: #4A5252;
        line-height: 1.3;
        word-break: break-all;
    }
</style>
</head>
<body>
<div class="page">
    <div class="field-student-name">{{ $student->name }}</div>
    <div class="field-course-name">{{ $course?->name ?? $cert->certification_name }}</div>
    <div class="field-cert-subtitle">{{ $cert->certification_name }}</div>

    @if($cert->score)
    <div class="field-score">Punteggio: {{ $cert->score }}%</div>
    @endif

    <div class="field-date-value">{{ $date }}</div>
    <div class="field-code-value">{{ $cert->code }}</div>
    <div class="field-owner-value">{{ atheneum_setting('platform_owner', 'Noscite SRLS') }}</div>

    <div class="verify-block">
        <img src="{{ $qrDataUri }}" alt="QR verifica">
        <div class="verify-label">Verifica online</div>
        <div class="verify-url">{{ $verifyUrl }}</div>
    </div>
</div>
</body>
</html>
