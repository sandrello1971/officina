<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; }
        .alert { background: #fef3c7; border: 1px solid #f59e0b; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        table { border-collapse: collapse; width: 100%; margin: 10px 0; }
        td { padding: 8px; border-bottom: 1px solid #eee; }
    </style>
</head>
<body>
    <div class="alert">
        <strong>FALLBACK CRM IRRAGGIUNGIBILE</strong><br>
        Il lead è stato raccolto e gli è stato inviato il report PDF, ma il CRM non è riuscito a registrare la richiesta.<br>
        <strong>INSERIRE MANUALMENTE NEL CRM</strong> i dati qui sotto.
    </div>

    <p><strong>Motivo errore:</strong> {{ $errorReason }}</p>

    <h3>Dati lead da inserire</h3>
    <table>
        <tr><td><strong>Nome</strong></td><td>{{ $lead['first_name'] }} {{ $lead['last_name'] }}</td></tr>
        <tr><td><strong>Email</strong></td><td>{{ $lead['email'] }}</td></tr>
        <tr><td><strong>Telefono</strong></td><td>{{ $lead['phone'] ?? '—' }}</td></tr>
        <tr><td><strong>Azienda</strong></td><td>{{ $lead['company_name'] }}</td></tr>
        <tr><td><strong>Ruolo</strong></td><td>{{ $lead['role'] ?? '—' }}</td></tr>
        <tr><td><strong>Total score</strong></td><td>{{ $lead['total_score'] }} / 20</td></tr>
        <tr><td><strong>Corso raccomandato</strong></td><td>{{ $lead['recommended_course'] }}</td></tr>
    </table>

    <h4>Scores</h4>
    <table>
        @foreach($lead['scores'] as $dim => $value)
            <tr><td>{{ ucfirst($dim) }}</td><td>{{ $value }}/4</td></tr>
        @endforeach
    </table>

    @if(!empty($lead['notes']))
        <h4>Note</h4>
        @foreach($lead['notes'] as $dim => $note)
            @if(!empty($note))
                <p><strong>{{ ucfirst($dim) }}:</strong> {{ $note }}</p>
            @endif
        @endforeach
    @endif
</body>
</html>
