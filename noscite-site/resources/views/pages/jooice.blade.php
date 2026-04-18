@extends('layouts.noscite')
@section('title', 'Jooice — Piattaforma AI per PMI')

@push('meta')
    <x-seo title="Jooice — Piattaforma AI per PMI" description="Jooice e la piattaforma di intelligenza artificiale progettata per le PMI italiane. Automatizza, analizza e cresci con l'AI." />
@endpush

@section('content')

{{-- Hero --}}
<section class="relative overflow-hidden bg-gradient-to-br from-gray-900 via-primary-900 to-gray-900 py-24 sm:py-32">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <div class="max-w-3xl">
            <span class="inline-block px-4 py-1.5 text-xs font-semibold text-secondary-400 bg-secondary-500/10 rounded-full uppercase tracking-wider mb-6">
                Powered by Noscite
            </span>
            <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold text-white leading-tight">
                Jooice
                <span class="block text-primary-400 mt-2">L'AI che parla la lingua delle PMI</span>
            </h1>
            <p class="mt-6 text-lg sm:text-xl text-gray-300 leading-relaxed max-w-2xl">
                Una piattaforma di intelligenza artificiale progettata per le piccole e medie imprese italiane. Semplice da usare, potente nei risultati.
            </p>
            <div class="mt-10 flex flex-col sm:flex-row gap-4">
                <a href="{{ route('contactus') }}"
                   class="inline-flex items-center justify-center px-8 py-3.5 text-base font-semibold text-gray-900 bg-secondary-500 rounded-lg hover:bg-secondary-400 transition-colors shadow-lg">
                    Richiedi una demo
                    <svg class="ml-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                </a>
                <a href="#features"
                   class="inline-flex items-center justify-center px-8 py-3.5 text-base font-semibold text-white border-2 border-white/20 rounded-lg hover:border-white/40 hover:bg-white/5 transition-colors">
                    Scopri le funzionalita
                </a>
            </div>
        </div>
    </div>
    <div class="absolute top-1/2 right-0 -translate-y-1/2 translate-x-1/4 w-[600px] h-[600px] bg-primary-500/10 rounded-full blur-3xl"></div>
</section>

{{-- Features --}}
<section id="features" class="py-20 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-3xl mx-auto mb-16">
            <h2 class="text-3xl sm:text-4xl font-bold text-gray-900">Cosa puo fare Jooice per te</h2>
            <p class="mt-4 text-lg text-gray-600">Funzionalita pensate per le esigenze reali delle PMI italiane.</p>
        </div>

        @php
            $features = [
                ['icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>', 'title' => 'Assistente AI conversazionale', 'desc' => 'Un assistente intelligente che comprende il contesto della tua azienda e risponde in modo pertinente a domande su processi, dati e decisioni operative.'],
                ['icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>', 'title' => 'Analisi predittiva', 'desc' => 'Analizza i tuoi dati storici per identificare trend, anomalie e opportunita. Prevedi la domanda, ottimizza le scorte e anticipa i problemi prima che si presentino.'],
                ['icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>', 'title' => 'Automazione intelligente', 'desc' => 'Automatizza le attivita ripetitive con workflow intelligenti che si adattano al tuo modo di lavorare. Dalla gestione email alla reportistica, libera tempo per cio che conta.'],
                ['icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>', 'title' => 'Generazione documenti', 'desc' => 'Genera report, proposte commerciali e documenti aziendali partendo dai tuoi dati. Con template personalizzabili e output professionale, in pochi secondi.'],
                ['icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>', 'title' => 'Privacy e sicurezza', 'desc' => 'I tuoi dati restano tuoi. Infrastruttura europea, crittografia end-to-end, compliance GDPR nativa. Nessun dato viene utilizzato per addestrare modelli di terze parti.'],
                ['icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>', 'title' => 'Integrazione semplice', 'desc' => 'Si collega ai tuoi strumenti esistenti: CRM, ERP, email, calendario, cloud storage. Nessuna rivoluzione tecnologica, solo un potenziamento intelligente di cio che gia usi.'],
            ];
        @endphp

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            @foreach($features as $feature)
                <div class="bg-gray-50 rounded-2xl p-8 hover:shadow-lg transition-shadow">
                    <div class="w-12 h-12 bg-primary-100 text-primary-600 rounded-xl flex items-center justify-center mb-5">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">{!! $feature['icon'] !!}</svg>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">{{ $feature['title'] }}</h3>
                    <p class="text-gray-600 text-sm leading-relaxed">{{ $feature['desc'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- Social proof --}}
<section class="py-16 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-8 text-center">
            <div>
                <p class="text-4xl font-bold text-primary-600">95%</p>
                <p class="text-sm text-gray-600 mt-2">Riduzione tempi di reportistica</p>
            </div>
            <div>
                <p class="text-4xl font-bold text-primary-600">3x</p>
                <p class="text-sm text-gray-600 mt-2">Produttivita nelle attivita ripetitive</p>
            </div>
            <div>
                <p class="text-4xl font-bold text-primary-600">100%</p>
                <p class="text-sm text-gray-600 mt-2">Dati in infrastruttura EU / GDPR</p>
            </div>
        </div>
    </div>
</section>

{{-- CTA --}}
<section class="bg-gradient-to-r from-primary-600 to-primary-800 py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-3xl sm:text-4xl font-bold text-white">Pronto a scoprire Jooice?</h2>
        <p class="mt-4 text-lg text-primary-100 max-w-2xl mx-auto">
            Richiedi una demo personalizzata e scopri come l'AI puo trasformare la tua impresa.
        </p>
        <a href="{{ route('contactus') }}"
           class="inline-flex items-center mt-8 px-8 py-3.5 text-base font-semibold text-primary-700 bg-white rounded-lg hover:bg-primary-50 transition-colors shadow-lg">
            Richiedi una demo gratuita
            <svg class="ml-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
        </a>
    </div>
</section>

@endsection
