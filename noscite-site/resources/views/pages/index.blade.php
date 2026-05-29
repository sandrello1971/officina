@extends('layouts.noscite')
@section('title', 'Trasformazione Digitale e AI per PMI')

@push('meta')
    <x-seo
        title="Trasformazione Digitale e AI per PMI"
        description="Noscite accompagna le PMI nel percorso di innovazione digitale con metodo, visione e tecnologia. Umanesimo digitale per la crescita sostenibile."
    />
@endpush

@section('content')

{{-- ========== HERO ========== --}}
<section class="relative overflow-hidden bg-white" style="min-height: 85vh;">
    {{-- Background decorativo: classicismo --}}
    <div class="absolute inset-0 bg-cover bg-no-repeat opacity-[0.35] pointer-events-none decorative" style="background-image: url('/images/classicismo.png'); background-position: center right; image-rendering: optimizeSpeed;" aria-hidden="true"></div>

    {{-- Contenuto --}}
    <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 sm:py-28 lg:py-36 flex items-center" style="min-height: 85vh;">
        <div class="max-w-3xl">
            <h1 class="text-display sm:text-[48px] lg:text-[56px] font-light leading-tight tracking-tight">
                <span class="text-[#1A1F1F]">In Digit&#x101;l&#x12B;</span><br>
                <span class="text-[#55B1AE] font-normal">nova Virt&#x16B;s</span>
            </h1>
            <p class="mt-4 text-base font-semibold mb-2" style="color:#55B1AE">
                Consulenza strategica e formazione AI per la trasformazione digitale delle PMI italiane
            </p>
            <p class="mt-4 text-lg font-bold text-[#1A1F1F] leading-relaxed max-w-2xl">
                Innovazione Digitale e Trasformazione Digitale
            </p>
            <p class="mt-3 text-body text-[#4A5252] leading-relaxed max-w-2xl">
                Il digitale non e solo tecnologia, ma capacita di creare valore attraverso metodo, visione e innovazione digitale per la tua azienda.
            </p>

            <div class="flex flex-col gap-2 mt-6 mb-6">
                <div class="flex items-center gap-2 text-sm" style="color:#1A1F1F">
                    <span style="color:#55B1AE;font-weight:bold">&rarr;</span>
                    <span><strong>Mappiamo i processi</strong> — identifichiamo dove l'AI genera valore reale</span>
                </div>
                <div class="flex items-center gap-2 text-sm" style="color:#1A1F1F">
                    <span style="color:#55B1AE;font-weight:bold">&rarr;</span>
                    <span><strong>Formiamo il team</strong> — competenze concrete, non slide teoriche</span>
                </div>
                <div class="flex items-center gap-2 text-sm" style="color:#1A1F1F">
                    <span style="color:#55B1AE;font-weight:bold">&rarr;</span>
                    <span><strong>Implementiamo automazioni misurabili</strong> — risultati tracciabili, non promesse</span>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-4">
                <a href="{{ route('methodus') }}"
                   class="inline-flex items-center justify-center px-8 py-3.5 text-sm font-semibold text-white bg-teal rounded-lg hover:bg-teal-dark transition-colors">
                    Scopri il metodo
                    <svg class="ml-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                    </svg>
                </a>
                <a href="{{ route('contactus') }}"
                   class="inline-flex items-center justify-center px-8 py-3.5 text-sm font-semibold text-teal bg-white border-2 border-teal rounded-lg hover:bg-teal-light transition-colors">
                    Raccontaci la tua situazione
                </a>
            </div>
            <p class="text-xs mt-3" style="color:#8A9696">Ti rispondiamo concretamente entro 24 ore.</p>
        </div>
    </div>
</section>

{{-- ========== IDENTITAS ========== --}}
<section class="py-20 bg-teal-light">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-3xl mx-auto mb-16">
            <span class="text-label uppercase font-bold text-teal tracking-wider">Chi siamo</span>
            <h2 class="mt-3 text-[28px] sm:text-[32px] font-light text-dark">Umanesimo digitale per le PMI</h2>
            <p class="mt-4 text-body text-mid leading-relaxed">
                Noscite e uno studio di consulenza dedicato all'umanesimo digitale. Aiutiamo le piccole e medie imprese a integrare tecnologia e cultura organizzativa, trasformando la complessita digitale in opportunita concreta di crescita.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            {{-- Metodo --}}
            <div class="bg-white rounded-2xl p-8 text-center hover:shadow-lg transition-shadow border border-border">
                <div class="w-14 h-14 mx-auto mb-5 bg-teal-light text-teal rounded-xl flex items-center justify-center">
                    <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                </div>
                <h3 class="text-h2 text-dark mb-3">Metodo</h3>
                <p class="text-body text-mid leading-relaxed">
                    Un approccio strutturato in cinque fasi che garantisce risultati misurabili e sostenibili nel tempo.
                </p>
            </div>

            {{-- Cultura --}}
            <div class="bg-white rounded-2xl p-8 text-center hover:shadow-lg transition-shadow border border-border">
                <div class="w-14 h-14 mx-auto mb-5 bg-teal-light text-teal rounded-xl flex items-center justify-center">
                    <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                    </svg>
                </div>
                <h3 class="text-h2 text-dark mb-3">Cultura</h3>
                <p class="text-body text-mid leading-relaxed">
                    La trasformazione digitale inizia dalle persone. Investiamo nella crescita delle competenze e nella consapevolezza organizzativa.
                </p>
            </div>

            {{-- Tecnologia --}}
            <div class="bg-white rounded-2xl p-8 text-center hover:shadow-lg transition-shadow border border-border">
                <div class="w-14 h-14 mx-auto mb-5 bg-teal-light text-teal rounded-xl flex items-center justify-center">
                    <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                    </svg>
                </div>
                <h3 class="text-h2 text-dark mb-3">Tecnologia</h3>
                <p class="text-body text-mid leading-relaxed">
                    Strumenti e soluzioni selezionate con cura, dall'intelligenza artificiale all'automazione dei processi.
                </p>
            </div>
        </div>
    </div>
</section>

{{-- ========== METHODUS ========== --}}
<section class="py-20 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-3xl mx-auto mb-16">
            <span class="text-label uppercase font-bold text-teal tracking-wider">Il nostro approccio</span>
            <h2 class="mt-3 text-[28px] sm:text-[32px] font-light text-dark">Cinque fasi per trasformare</h2>
            <p class="mt-4 text-body text-mid">
                Una trasformazione digitale consapevole e duratura.
            </p>
        </div>

        <div class="space-y-6">
            @php
                $fasi = [
                    ['num' => '01', 'title' => 'Diagnosi', 'desc' => 'Analizziamo lo stato attuale dell\'organizzazione, i processi e la Maturità AI per identificare punti di forza e aree di intervento.', 'output' => 'Mappa processi + priorita AI'],
                    ['num' => '02', 'title' => 'Strategia', 'desc' => 'Definiamo obiettivi chiari, priorita e una roadmap personalizzata in linea con le risorse e la visione dell\'impresa.', 'output' => 'Roadmap 90 giorni'],
                    ['num' => '03', 'title' => 'Formazione', 'desc' => 'Prepariamo le persone al cambiamento con percorsi formativi mirati, costruendo competenze digitali solide e consapevoli.', 'output' => 'Team formato e certificato'],
                    ['num' => '04', 'title' => 'Implementazione', 'desc' => 'Accompagniamo l\'adozione degli strumenti e dei processi, garantendo un\'integrazione fluida nell\'operativita quotidiana.', 'output' => 'Automazioni attive e misurate'],
                    ['num' => '05', 'title' => 'Evoluzione', 'desc' => 'Monitoriamo i risultati, ottimizziamo e aggiorniamo la strategia per una crescita continua e adattiva.', 'output' => 'Piano di crescita continua'],
                ];
            @endphp

            @foreach($fasi as $fase)
                <div class="bg-neutral rounded-2xl p-6 sm:p-8 flex flex-col sm:flex-row items-start gap-6 hover:shadow-lg transition-shadow border border-border">
                    <div class="flex-shrink-0 w-14 h-14 bg-teal text-white rounded-xl flex items-center justify-center font-bold text-lg">
                        {{ $fase['num'] }}
                    </div>
                    <div>
                        <h3 class="text-h1 text-dark mb-2">{{ $fase['title'] }}</h3>
                        <span class="text-xs px-2 py-1 rounded mt-1 inline-block" style="background:#E8F5F5;color:#3A8C89;font-weight:600">&rarr; {{ $fase['output'] }}</span>
                        <p class="text-body text-mid leading-relaxed mt-2">{{ $fase['desc'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ========== VALOR ========== --}}
<section class="py-20 bg-teal-light">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-3xl mx-auto mb-16">
            <span class="text-label uppercase font-bold text-teal tracking-wider">I nostri valori</span>
            <h2 class="mt-3 text-[28px] sm:text-[32px] font-light text-dark">Principi guida</h2>
            <p class="mt-4 text-body text-mid">
                Orientano ogni progetto e ogni relazione.
            </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
            @php
                $valori = [
                    ['icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>', 'title' => 'Rigore', 'desc' => 'Precisione nell\'analisi, coerenza nell\'esecuzione e trasparenza nei risultati.'],
                    ['icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>', 'title' => 'Etica', 'desc' => 'Rispetto per le persone, i dati e l\'impatto sociale della tecnologia.'],
                    ['icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>', 'title' => 'Innovazione', 'desc' => 'Curiosita e sperimentazione al servizio di soluzioni concrete e misurabili.'],
                    ['icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/>', 'title' => 'Accessibilita', 'desc' => 'Tecnologia comprensibile e alla portata di ogni organizzazione.'],
                    ['icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>', 'title' => 'Collaborazione', 'desc' => 'Lavoriamo al fianco dei nostri clienti come partner, non come fornitori.'],
                    ['icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>', 'title' => 'Continuita', 'desc' => 'Percorsi di evoluzione continua che crescono con l\'impresa.'],
                ];
            @endphp

            @foreach($valori as $valore)
                <div class="flex items-start gap-4 p-6 bg-white rounded-2xl border border-border hover:shadow-lg transition-shadow">
                    <div class="flex-shrink-0 w-12 h-12 bg-teal-light text-teal rounded-xl flex items-center justify-center">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">{!! $valore['icon'] !!}</svg>
                    </div>
                    <div>
                        <h3 class="text-h2 text-dark mb-1">{{ $valore['title'] }}</h3>
                        <p class="text-body text-mid leading-relaxed">{{ $valore['desc'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ========== ATHENEUM ========== --}}
<section class="py-20 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl mx-auto text-center">
            <span class="text-label uppercase font-bold text-teal tracking-wider">Formazione</span>
            <h2 class="mt-3 text-[28px] sm:text-[32px] font-light text-dark">Programmi formativi</h2>
            <p class="mt-4 text-body text-mid leading-relaxed">
                L'Atheneum Noscite offre percorsi di formazione pensati per imprenditori, manager e team operativi. Dalla comprensione dell'AI alla governance dei dati, costruiamo competenze che generano valore.
            </p>
            <a href="{{ route('atheneum') }}"
               class="inline-flex items-center mt-8 px-8 py-3.5 text-sm font-semibold text-white bg-teal rounded-lg hover:bg-teal-dark transition-colors">
                Esplora i programmi
                <svg class="ml-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                </svg>
            </a>
        </div>
    </div>
</section>

{{-- ========== CITAZIONE ========== --}}
<section class="py-16 bg-teal-light">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <blockquote class="border-l-4 border-teal pl-6 py-4">
            <p class="text-xl italic text-orange leading-relaxed">
                "La tua impresa. Potenziata dall'intelligenza."
            </p>
            <footer class="mt-3 text-label uppercase font-bold text-teal tracking-wider">— Noscite</footer>
        </blockquote>
    </div>
</section>

{{-- ========== PARTNERS ========== --}}
<section class="py-12 px-4" style="background:#F5F7F7">
    <div class="max-w-4xl mx-auto text-center">
        <p class="text-xs font-bold uppercase mb-8 tracking-widest" style="color:#8A9696">I nostri partner</p>
        <div class="flex flex-wrap items-center justify-center gap-12">
            <a href="https://www.webidoo.com" target="_blank" rel="noopener noreferrer" class="opacity-60 hover:opacity-100 transition-opacity duration-300">
                <img src="/images/webidoo-logo-6kXZdXos.png" alt="Webidoo" class="h-10 w-auto object-contain" loading="lazy">
            </a>
            <a href="https://www.tibidabo.it" target="_blank" rel="noopener noreferrer" class="opacity-60 hover:opacity-100 transition-opacity duration-300">
                <img src="/images/tibidabo-logo-ByPvwhp8.jpeg" alt="Tibidabo Digital Insights" class="h-10 w-auto object-contain" loading="lazy">
            </a>
            <a href="https://www.teknet.it" target="_blank" rel="noopener noreferrer" class="opacity-60 hover:opacity-100 transition-opacity duration-300">
                <img src="/images/teknet-logo-DuQzOzpy.png" alt="Teknet" class="h-10 w-auto object-contain" loading="lazy">
            </a>
        </div>
    </div>
</section>

{{-- ========== CONTACT CTA ========== --}}
<section class="bg-teal">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 text-center">
        <h2 class="text-[28px] sm:text-[32px] font-light text-white">Inizia il tuo percorso</h2>
        <p class="mt-4 text-base text-white/80 max-w-2xl mx-auto">
            Parlaci della tua impresa. Insieme troveremo la strada giusta per la tua trasformazione digitale.
        </p>
        <a href="{{ route('contactus') }}"
           class="inline-flex items-center mt-8 px-8 py-3.5 text-sm font-semibold text-teal bg-white rounded-lg hover:bg-neutral transition-colors">
            Contattaci
            <svg class="ml-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
            </svg>
        </a>
    </div>
</section>

{{-- ========== NEWSLETTER ========== --}}
<section class="py-12 bg-dark">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h3 class="text-h1 text-white mb-2">Resta aggiornato</h3>
        <p class="text-muted text-body mb-6">Iscriviti alla newsletter per ricevere aggiornamenti su digitale, AI e innovazione per le PMI.</p>
        <div class="max-w-md mx-auto">
            <livewire:newsletter-subscribe />
        </div>
    </div>
</section>

@endsection
