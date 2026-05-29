<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: 0 auto; padding: 24px; }
        h2 { color: #55B1AE; }
        .cta { display: inline-block; background: #E28A53; color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 4px; margin: 20px 0; font-weight: bold; }
        .small { font-size: 12px; color: #888; margin-top: 30px; }
    </style>
</head>
<body>
    <h2>Ciao {{ $lead['first_name'] }},</h2>

    <p>grazie per aver completato la Mappa di Maturità AI di Noscite.</p>

    <p>In allegato trovi il tuo <strong>report personalizzato</strong> con il risultato delle 5 dimensioni dell'assessment e la nostra raccomandazione formativa per il tuo percorso AI Act compliant.</p>

    <p><strong>Score totale:</strong> {{ $lead['total_score'] }} / 20<br>
    <strong>Corso suggerito:</strong> {{ $lead['recommended_course'] }}</p>

    <p>Un nostro consulente ti contatterà entro 24 ore per approfondire il percorso più adatto alla tua azienda.</p>

    <p>Se vuoi anticipare i tempi puoi scriverci a <a href="mailto:sales@noscite.it">sales@noscite.it</a>.</p>

    <a href="https://noscite.it/contatti" class="cta">Contattaci subito</a>

    <p>A presto,<br>Il team Noscite</p>

    <hr>
    <p class="small">
        Hai ricevuto questa email perché hai compilato la Mappa di Maturità AI su noscite.it/assessment-ai-act.<br>
        Noscite Srls, Corsico (MI) — P.IVA 14385240966<br>
        Per cancellare i tuoi dati scrivi a <a href="mailto:privacy@noscite.it">privacy@noscite.it</a>
    </p>
</body>
</html>
