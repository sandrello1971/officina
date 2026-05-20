<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body {
        /* DejaVu Serif: bundlato di default in dompdf, copre tutto il
           range Latin Extended (macron ā ī ū) che Georgia non ha →
           senza questo cambio "In digitālī nova virtūs" rende come
           "In digit?l? nova virt?s" nel PDF. */
        font-family: 'DejaVu Serif', serif;
        background: white;
        color: #1A1F1F;
    }
    .page {
        width: 297mm;
        height: 210mm;
        padding: 16mm;
        position: relative;
        overflow: hidden;
    }
    .border-outer {
        position: absolute;
        inset: 8mm;
        border: 3px solid #55B1AE;
        border-radius: 4px;
    }
    .border-inner {
        position: absolute;
        inset: 10mm;
        border: 1px solid #E28A53;
        border-radius: 3px;
    }
    .content {
        /* dompdf non supporta flex. height:100% causa overflow → page
           break. Sostituiamo con layout block normale, padding-top
           calcolato per pseudo-vertical-center, e text-align center per
           l'orizzontale. */
        position: relative;
        z-index: 10;
        padding: 18mm 8mm 8mm;
        text-align: center;
    }
    .logo-area {
        margin-bottom: 6mm;
    }
    .logo-text {
        font-size: 28pt;
        font-weight: bold;
        color: #55B1AE;
        letter-spacing: 4px;
        text-transform: uppercase;
    }
    .logo-sub {
        font-size: 9pt;
        color: #8A9696;
        font-style: italic;
        letter-spacing: 2px;
        margin-top: 1mm;
    }
    .divider {
        width: 60mm;
        height: 1px;
        background: linear-gradient(to right, transparent, #E28A53, transparent);
        margin: 5mm auto;
    }
    .certifies {
        font-size: 10pt;
        color: #8A9696;
        text-transform: uppercase;
        letter-spacing: 3px;
        margin-bottom: 4mm;
    }
    .student-name {
        font-size: 32pt;
        color: #1A1F1F;
        font-style: italic;
        margin-bottom: 4mm;
        font-weight: bold;
    }
    .completion-text {
        font-size: 10pt;
        color: #4A5252;
        margin-bottom: 3mm;
        line-height: 1.6;
    }
    .course-name {
        font-size: 20pt;
        color: #55B1AE;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 2px;
        margin-bottom: 2mm;
    }
    .certification-name {
        font-size: 12pt;
        color: #E28A53;
        font-style: italic;
        margin-bottom: 6mm;
    }
    .divider-bottom {
        width: 80mm;
        height: 1px;
        background: #C8D0D0;
        margin: 0 auto 6mm;
    }
    .footer-grid {
        display: flex;
        justify-content: space-between;
        width: 100%;
        padding: 0 10mm;
        margin-top: 4mm;
    }
    .footer-item {
        text-align: center;
    }
    .footer-label {
        font-size: 7pt;
        color: #8A9696;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 1mm;
    }
    .footer-value {
        font-size: 9pt;
        color: #1A1F1F;
        font-weight: bold;
    }
    .footer-line {
        width: 30mm;
        height: 1px;
        background: #C8D0D0;
        margin: 2mm auto 1mm;
    }
    .watermark {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%) rotate(-30deg);
        font-size: 80pt;
        color: rgba(85,177,174,0.04);
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 8px;
        white-space: nowrap;
        z-index: 1;
    }
    .corner-ornament {
        position: absolute;
        font-size: 20pt;
        color: #E28A53;
        opacity: 0.5;
    }
    .corner-tl { top: 14mm; left: 14mm; }
    .corner-tr { top: 14mm; right: 14mm; }
    .corner-bl { bottom: 14mm; left: 14mm; }
    .corner-br { bottom: 14mm; right: 14mm; }
    .score-badge {
        display: inline-block;
        padding: 2mm 6mm;
        border: 1px solid #55B1AE;
        border-radius: 20px;
        font-size: 9pt;
        color: #55B1AE;
        margin-bottom: 4mm;
    }
    .verify-block {
        /* width esplicita: senza, dompdf risolve la position absolute
           a partire dalla width naturale del contenuto e il blocco
           esce dal bordo destro pagina (URL + label troncati). */
        position: absolute;
        bottom: 16mm;
        right: 16mm;
        width: 45mm;
        text-align: center;
        z-index: 11;
    }
    .verify-qr {
        width: 22mm;
        height: 22mm;
        display: block;
        margin: 0 auto 1mm;
    }
    .verify-label {
        font-size: 6pt;
        color: #8A9696;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 0.5mm;
    }
    .verify-url {
        /* DejaVu Sans Mono: bundlato in dompdf. font-size ridotta a
           5.5pt + line-height 1.3 per fare entrare l'URL completo
           su 2-3 righe dentro i 45mm del parent. max-width rimosso:
           il parent ora ha width esplicita. */
        font-size: 5.5pt;
        color: #4A5252;
        font-family: 'DejaVu Sans Mono', monospace;
        word-break: break-all;
        line-height: 1.3;
    }
</style>
</head>
<body>
<div class="page">
    <div class="border-outer"></div>
    <div class="border-inner"></div>
    <div class="watermark">{{ strtoupper(atheneum_setting('platform_owner', 'NOSCITE')) }}</div>

    <span class="corner-ornament corner-tl">✦</span>
    <span class="corner-ornament corner-tr">✦</span>
    <span class="corner-ornament corner-bl">✦</span>
    <span class="corner-ornament corner-br">✦</span>

    <div class="content">
        <div class="logo-area">
            <div class="logo-text">{{ strtoupper(atheneum_setting('platform_owner', 'NOSCITE')) }}</div>
            <div class="logo-sub">{{ atheneum_setting('platform_tagline', 'In digitālī nova virtūs') }}</div>
        </div>

        <div class="divider"></div>

        <div class="certifies">Certifica che</div>
        <div class="student-name">{{ $student->name }}</div>
        <div class="completion-text">ha completato con successo il corso</div>
        <div class="course-name">{{ $course?->name ?? $cert->certification_name }}</div>
        <div class="certification-name">{{ $cert->certification_name }}</div>

        @if($cert->score)
        <div class="score-badge">Punteggio: {{ $cert->score }}%</div>
        @endif

        <div class="divider-bottom"></div>

        <div class="footer-grid">
            <div class="footer-item">
                <div class="footer-label">Data di emissione</div>
                <div class="footer-line"></div>
                <div class="footer-value">{{ $date }}</div>
            </div>
            <div class="footer-item">
                <div class="footer-label">Codice certificato</div>
                <div class="footer-line"></div>
                <div class="footer-value" style="font-size:8pt; font-family:'DejaVu Sans Mono', monospace;">{{ $cert->code }}</div>
            </div>
            <div class="footer-item">
                <div class="footer-label">Rilasciato da</div>
                <div class="footer-line"></div>
                <div class="footer-value">{{ atheneum_setting('platform_owner', 'Noscite SRLS') }}</div>
            </div>
        </div>
    </div>

    <div class="verify-block">
        <img src="{{ $qrDataUri }}" class="verify-qr" alt="QR verifica">
        <div class="verify-label">Verifica online</div>
        <div class="verify-url">{{ $verifyUrl }}</div>
    </div>
</div>
</body>
</html>
