<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 30px; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11pt; color: #1A1F1F; }
        .header { border-bottom: 2px solid #55B1AE; padding-bottom: 10px; margin-bottom: 18px; }
        .header img { height: 40px; }
        h1 { color: #55B1AE; font-size: 22pt; margin: 10px 0 4px; }
        .subtitle { color: #555; font-size: 10pt; }
        .info-box { background: #E8F5F5; border-left: 3px solid #55B1AE; padding: 12px; margin: 16px 0; }
        .score-summary { text-align: center; margin: 24px 0; }
        .score-big { font-size: 48pt; color: #55B1AE; font-weight: bold; line-height: 1; }
        .score-label { color: #888; font-size: 10pt; }
        .reco-box { background: #E28A53; color: white; padding: 16px; border-radius: 4px; text-align: center; margin: 18px 0; }
        .reco-course { font-size: 18pt; font-weight: bold; margin: 6px 0; }
        table { border-collapse: collapse; width: 100%; margin: 14px 0; }
        td, th { padding: 7px 10px; border-bottom: 1px solid #ddd; text-align: left; }
        th { background: #f5f7f7; }
        .dim-label { font-weight: bold; }
        .footer { margin-top: 30px; padding-top: 12px; border-top: 1px solid #ddd; font-size: 9pt; color: #888; }
    </style>
</head>
<body>
    <div class="header">
        @if(file_exists(public_path('images/logo.png')))
            <img src="{{ public_path('images/logo.png') }}" alt="Noscite">
        @endif
        <h1>Mappa di Maturità AI</h1>
        <div class="subtitle">Report personalizzato — {{ \Carbon\Carbon::now()->format('d/m/Y') }}</div>
    </div>

    <div class="info-box">
        <strong>Azienda:</strong> {{ $lead['company_name'] }}<br>
        <strong>Contatto:</strong> {{ $lead['first_name'] }} {{ $lead['last_name'] }}
        @if(!empty($lead['role'])) — {{ $lead['role'] }} @endif
    </div>

    <div class="score-summary">
        <div class="score-big">{{ $lead['total_score'] }}<span style="font-size:18pt;color:#888;">/20</span></div>
        <div class="score-label">Punteggio totale di Maturità AI</div>
    </div>

    <div class="reco-box">
        <div style="font-size:9pt;letter-spacing:1px;">CORSO RACCOMANDATO</div>
        <div class="reco-course">{{ $lead['recommended_course'] }}</div>
        <div style="font-size:9pt;">
            @if($lead['recommended_course'] === 'PRIMUS')
                Corso introduttivo di 4 ore per scoprire i fondamenti dell'AI generativa nelle PMI e l'obbligo di alfabetizzazione AI (Art. 4 EU AI Act).
            @elseif($lead['recommended_course'] === 'CONSILIUM')
                Workshop strategico di 7 ore per board e dirigenti: definizione AI Usage Policy, roadmap 90 giorni e selezione progetti pilota.
            @else
                Percorso operativo di 20 ore (5 moduli) con certificazione: formazione strutturata per team che adottano AI in modo sistematico.
            @endif
        </div>
    </div>

    <h3>Dettaglio per dimensione</h3>
    <table>
        <thead>
            <tr><th>Dimensione</th><th style="text-align:center;width:80px;">Score</th><th>Livello</th></tr>
        </thead>
        <tbody>
            @php
                $labels = [
                    'tools' => 'Utilizzo degli Strumenti AI',
                    'governance' => 'Governance dei Dati',
                    'skills' => 'Competenze del Team',
                    'processes' => 'Processi Digitalizzati',
                    'compliance' => 'Conformità Normativa (AI Act)',
                ];
                $levels = [1 => 'Assente', 2 => 'Iniziale', 3 => 'In sviluppo', 4 => 'Strutturato'];
            @endphp
            @foreach($lead['scores'] as $key => $value)
                <tr>
                    <td class="dim-label">{{ $labels[$key] ?? ucfirst($key) }}</td>
                    <td style="text-align:center;">{{ $value }} / 4</td>
                    <td>{{ $levels[$value] ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h3>Prossimo passo</h3>
    <p>Un nostro consulente ti contatterà entro 24 ore per discutere il percorso più adatto alla tua azienda. Se vuoi anticipare i tempi, scrivi a <strong>sales@noscite.it</strong>.</p>

    <p style="margin-top:30px;">Cordialmente,<br><strong>Il team Noscite</strong></p>

    <div class="footer">
        Noscite Srls — Corsico (MI) — P.IVA 14385240966<br>
        www.noscite.it — sales@noscite.it<br>
        Report generato il {{ \Carbon\Carbon::now()->format('d/m/Y H:i') }}
    </div>
</body>
</html>
