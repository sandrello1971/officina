@extends('layouts.noscite')
@section('title', 'Privacy Policy')

@push('meta')
    <x-seo title="Privacy Policy" description="Informativa sulla privacy di Noscite ai sensi del GDPR (Regolamento UE 2016/679). Come trattiamo i tuoi dati personali." />
@endpush

@section('content')

<section class="py-16 bg-white">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <h1 class="text-4xl font-bold text-gray-900 mb-2">Privacy Policy</h1>
        <p class="text-sm text-gray-500 mb-10">Ultimo aggiornamento: Gennaio 2024</p>

        <div class="prose prose-gray max-w-none prose-headings:text-gray-900">

            <h2>1. Titolare del trattamento</h2>
            <p>Il Titolare del trattamento dei dati personali e <strong>Noscite</strong>, con sede legale in Milano, Italia. Email di contatto: <a href="mailto:privacy@noscite.it">privacy@noscite.it</a>.</p>

            <h2>2. Tipologie di dati raccolti</h2>
            <p>I dati personali raccolti da questo sito, in modo autonomo o tramite terze parti, includono:</p>
            <ul>
                <li><strong>Dati di contatto:</strong> nome, cognome, indirizzo email, numero di telefono, nome dell'azienda, forniti volontariamente tramite il modulo di contatto.</li>
                <li><strong>Dati di navigazione:</strong> indirizzo IP, tipo di browser, sistema operativo, pagine visitate, orario di accesso e altre informazioni trasmesse dal protocollo HTTP.</li>
                <li><strong>Dati della newsletter:</strong> indirizzo email fornito per l'iscrizione alla newsletter.</li>
                <li><strong>Cookie:</strong> si rimanda alla <a href="{{ route('cookies') }}">Cookie Policy</a> per informazioni dettagliate.</li>
            </ul>

            <h2>3. Finalita del trattamento</h2>
            <p>I dati personali sono trattati per le seguenti finalita:</p>
            <ul>
                <li><strong>Rispondere alle richieste di contatto:</strong> base giuridica: esecuzione di misure precontrattuali (art. 6.1.b GDPR).</li>
                <li><strong>Invio della newsletter:</strong> base giuridica: consenso dell'interessato (art. 6.1.a GDPR).</li>
                <li><strong>Funzionamento del sito:</strong> base giuridica: legittimo interesse del Titolare (art. 6.1.f GDPR).</li>
                <li><strong>Adempimenti legali:</strong> base giuridica: obbligo legale (art. 6.1.c GDPR).</li>
            </ul>

            <h2>4. Modalita del trattamento</h2>
            <p>Il trattamento dei dati viene effettuato con strumenti informatici e/o telematici, con modalita organizzative e logiche strettamente correlate alle finalita indicate. I dati sono protetti con misure di sicurezza tecniche e organizzative adeguate a garantirne la riservatezza, l'integrita e la disponibilita.</p>

            <h2>5. Periodo di conservazione</h2>
            <ul>
                <li><strong>Dati di contatto:</strong> conservati per 24 mesi dall'ultima comunicazione, salvo diversa richiesta dell'interessato.</li>
                <li><strong>Dati della newsletter:</strong> conservati fino alla revoca del consenso (disiscrizione).</li>
                <li><strong>Dati di navigazione:</strong> conservati per 12 mesi.</li>
            </ul>

            <h2>6. Diritti dell'interessato</h2>
            <p>Ai sensi degli articoli 15-22 del GDPR, l'interessato ha diritto di:</p>
            <ul>
                <li>Accedere ai propri dati personali</li>
                <li>Richiederne la rettifica o la cancellazione</li>
                <li>Richiedere la limitazione del trattamento</li>
                <li>Opporsi al trattamento</li>
                <li>Richiedere la portabilita dei dati</li>
                <li>Revocare il consenso in qualsiasi momento</li>
                <li>Proporre reclamo all'autorita di controllo (Garante per la Protezione dei Dati Personali)</li>
            </ul>
            <p>Per esercitare i propri diritti, l'interessato puo contattare il Titolare all'indirizzo <a href="mailto:privacy@noscite.it">privacy@noscite.it</a>.</p>

            <h2>7. Comunicazione e diffusione dei dati</h2>
            <p>I dati personali non saranno diffusi. Potranno essere comunicati a soggetti terzi che svolgono attivita strumentali alle finalita indicate (es. fornitori di servizi hosting, servizi email), nominati responsabili del trattamento ai sensi dell'art. 28 GDPR.</p>

            <h2>8. Trasferimento dei dati</h2>
            <p>I dati personali sono trattati all'interno dell'Unione Europea. In caso di eventuale trasferimento verso Paesi terzi, saranno adottate le garanzie previste dal GDPR (decisioni di adeguatezza, clausole contrattuali tipo).</p>

            <h2>9. Modifiche alla presente informativa</h2>
            <p>Il Titolare si riserva il diritto di modificare la presente informativa in qualsiasi momento. Le modifiche saranno pubblicate su questa pagina con indicazione della data di ultimo aggiornamento.</p>

        </div>
    </div>
</section>

@endsection
