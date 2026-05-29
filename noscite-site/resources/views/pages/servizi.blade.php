@extends('layouts.noscite')
@section('title', 'Servizi — Cosa facciamo')
@section('description', 'Servizi Noscite per PMI: AI Process Automation, Knowledge Management Hub, Private AI e Governance dei dati. Consulenza strategica e implementazione AI con approccio incrementale.')

@push('meta')
    <x-seo title="Servizi" description="I servizi Noscite: consulenza strategica, formazione digitale, implementazione AI e digital transformation per le PMI italiane." />
@endpush

@section('content')

{{-- Hero --}}
<section class="py-20 bg-gradient-to-b from-primary-50/40 to-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="text-4xl sm:text-5xl font-bold text-gray-900">Servizi</h1>
        <p class="mt-4 text-xl text-gray-600 max-w-2xl mx-auto">Soluzioni concrete per la trasformazione digitale della tua impresa.</p>
    </div>
</section>

{{-- Servizi --}}
<section class="py-20 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @php
            $servizi = [
                ['icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>', 'title' => 'Consulenza Strategica', 'desc' => 'Analizziamo il tuo business, identifichiamo le opportunita digitali e definiamo una roadmap personalizzata. Dalla valutazione della Maturità AI alla definizione degli obiettivi, ti accompagniamo nelle scelte strategiche con dati e competenze.', 'items' => ['Assessment Maturità AI', 'Roadmap di trasformazione', 'Analisi costi-benefici', 'Business case per l\'innovazione']],
                ['icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>', 'title' => 'Formazione Digitale', 'desc' => 'Costruiamo competenze digitali nel tuo team con percorsi formativi pratici e personalizzati. Dalla comprensione dell\'AI agli strumenti di produttivita, rendiamo la tecnologia accessibile e utile per ogni ruolo aziendale.', 'items' => ['Workshop AI e automazione', 'Formazione strumenti digitali', 'Percorsi per management', 'Mentoring e coaching']],
                ['icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>', 'title' => 'Implementazione AI', 'desc' => 'Integriamo l\'intelligenza artificiale nei tuoi processi aziendali in modo pratico e misurabile. Dall\'automazione delle attivita ripetitive all\'analisi predittiva, selezioniamo e configuriamo le soluzioni AI piu adatte al tuo contesto.', 'items' => ['Automazione dei processi', 'Chatbot e assistenti AI', 'Analisi predittiva dei dati', 'Integrazione con sistemi esistenti']],
                ['icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>', 'title' => 'Digital Transformation', 'desc' => 'Un percorso completo di trasformazione che abbraccia processi, persone e tecnologia. Ti accompagniamo dalla diagnosi iniziale fino all\'evoluzione continua, con un approccio graduale e sostenibile che rispetta i ritmi della tua organizzazione.', 'items' => ['Reingegnerizzazione processi', 'Change management', 'Digital workplace', 'Governance e compliance digitale']],
            ];
        @endphp

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            @foreach($servizi as $servizio)
                <div class="bg-gray-50 rounded-2xl p-8 hover:shadow-lg transition-shadow">
                    <div class="w-14 h-14 bg-primary-100 text-primary-600 rounded-xl flex items-center justify-center mb-5">
                        <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">{!! $servizio['icon'] !!}</svg>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-3">{{ $servizio['title'] }}</h3>
                    <p class="text-gray-600 leading-relaxed mb-5">{{ $servizio['desc'] }}</p>
                    <ul class="space-y-2">
                        @foreach($servizio['items'] as $item)
                            <li class="flex items-center gap-2 text-sm text-gray-700">
                                <svg class="h-4 w-4 text-primary-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                {{ $item }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- CTA --}}
<section class="bg-primary-600 py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-3xl font-bold text-white">Hai bisogno di una consulenza?</h2>
        <p class="mt-4 text-primary-100 text-lg">Raccontaci le tue esigenze. Troveremo insieme la soluzione giusta.</p>
        <a href="{{ route('contactus') }}" class="inline-flex items-center mt-8 px-8 py-3.5 text-base font-semibold text-primary-700 bg-white rounded-lg hover:bg-primary-50 transition-colors">Contattaci</a>
    </div>
</section>

@endsection
