@extends('layouts.noscite')
@section('title', 'Cookie Policy')

@push('meta')
    <x-seo title="Cookie Policy" description="Informativa sui cookie di noscite.it. Quali cookie utilizziamo e come gestirli secondo la normativa europea." />
@endpush

@section('content')

<section class="py-16 bg-white">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <h1 class="text-4xl font-bold text-gray-900 mb-2">Cookie Policy</h1>
        <p class="text-sm text-gray-500 mb-10">Ultimo aggiornamento: Gennaio 2024</p>

        <div class="prose prose-gray max-w-none prose-headings:text-gray-900">

            <h2>1. Cosa sono i cookie</h2>
            <p>I cookie sono piccoli file di testo che i siti web visitati inviano al browser dell'utente, dove vengono memorizzati per essere ritrasmessi agli stessi siti alla visita successiva. I cookie sono utilizzati per diverse finalita, tra cui memorizzare le preferenze, migliorare l'esperienza di navigazione e raccogliere informazioni statistiche.</p>

            <h2>2. Tipologie di cookie utilizzati</h2>

            <h3>2.1 Cookie tecnici (necessari)</h3>
            <p>Sono cookie indispensabili per il corretto funzionamento del sito. Includono:</p>
            <ul>
                <li><strong>Cookie di sessione:</strong> permettono la navigazione e l'utilizzo delle funzionalita del sito (es. autenticazione). Vengono eliminati alla chiusura del browser.</li>
                <li><strong>Cookie CSRF:</strong> proteggono il sito da attacchi di tipo Cross-Site Request Forgery.</li>
                <li><strong>Cookie di preferenza:</strong> memorizzano le scelte dell'utente (es. consenso cookie).</li>
            </ul>
            <p>Base giuridica: legittimo interesse (art. 6.1.f GDPR). Non richiedono il consenso dell'utente.</p>

            <h3>2.2 Cookie analitici</h3>
            <p>Raccolgono informazioni aggregate e anonime sull'utilizzo del sito per finalita statistiche. Utilizziamo questi dati per comprendere come gli utenti interagiscono con il sito e migliorarne i contenuti e le funzionalita.</p>
            <p>Base giuridica: consenso dell'utente (art. 6.1.a GDPR).</p>

            <h3>2.3 Cookie di terze parti</h3>
            <p>Il sito potrebbe includere componenti di terze parti (es. font, script) che potrebbero installare cookie propri. Il Titolare non ha il controllo diretto su tali cookie. Si invita l'utente a consultare le informative privacy dei rispettivi servizi.</p>

            <h2>3. Durata dei cookie</h2>
            <table>
                <thead>
                    <tr>
                        <th>Cookie</th>
                        <th>Tipo</th>
                        <th>Durata</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Sessione Laravel</td>
                        <td>Tecnico</td>
                        <td>Sessione browser</td>
                    </tr>
                    <tr>
                        <td>XSRF-TOKEN</td>
                        <td>Tecnico</td>
                        <td>Sessione browser</td>
                    </tr>
                    <tr>
                        <td>cookie_consent</td>
                        <td>Preferenza</td>
                        <td>12 mesi</td>
                    </tr>
                </tbody>
            </table>

            <h2>4. Gestione dei cookie</h2>
            <p>L'utente puo gestire le preferenze sui cookie attraverso il banner presente al primo accesso al sito, oppure modificando le impostazioni del proprio browser. Di seguito i link alle guide dei principali browser:</p>
            <ul>
                <li><strong>Google Chrome:</strong> Impostazioni > Privacy e sicurezza > Cookie</li>
                <li><strong>Mozilla Firefox:</strong> Opzioni > Privacy e sicurezza</li>
                <li><strong>Safari:</strong> Preferenze > Privacy</li>
                <li><strong>Microsoft Edge:</strong> Impostazioni > Privacy, ricerca e servizi</li>
            </ul>
            <p><strong>Nota:</strong> la disabilitazione dei cookie tecnici potrebbe compromettere il funzionamento di alcune sezioni del sito.</p>

            <h2>5. Aggiornamenti</h2>
            <p>La presente Cookie Policy puo essere soggetta a modifiche. L'utente e invitato a consultare periodicamente questa pagina per verificare eventuali aggiornamenti.</p>

            <h2>6. Contatti</h2>
            <p>Per qualsiasi domanda relativa ai cookie e al trattamento dei dati, e possibile contattare il Titolare all'indirizzo <a href="mailto:privacy@noscite.it">privacy@noscite.it</a>.</p>

        </div>
    </div>
</section>

@endsection
