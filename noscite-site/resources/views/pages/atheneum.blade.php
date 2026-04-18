@extends('layouts.noscite')
@section('title', 'Atheneum — Portfolio Formativo Noscite')
@section('description', 'Portfolio formativo Noscite: CONSILIUM, INITIUM, STRUCTURA, AI AGENTS & MCP. 4 corsi certificati conformi EU AI Act Art. 4 per PMI italiane. Da 7 a 24 ore, certificazioni professionali incluse.')

@push('meta')
    <x-seo title="Atheneum — Portfolio Formativo Noscite" description="4 percorsi certificati di formazione AI per PMI: Consilium, Initium, Structura, AI Agents & MCP. ~60 ore totali, conformi EU AI Act." />
@endpush

@section('content')

{{-- ========== HERO ========== --}}
<section class="py-20 bg-white">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="text-display sm:text-[48px] lg:text-[56px] font-light text-dark leading-tight">Atheneum Noscite</h1>
        <p class="mt-6 text-h1 text-dark leading-relaxed max-w-3xl mx-auto">
            La conoscenza che non diventa competenza operativa non cambia nulla. Per questo la formazione Noscite non trasmette nozioni: costruisce competenza.
        </p>
        <p class="mt-4 text-body text-mid leading-relaxed max-w-3xl mx-auto">
            I percorsi sono modulari, interattivi e fondati su casi reali. Ogni programma segue la metodologia learning by doing. Tutti i corsi sono conformi al Reg. UE 2024/1689 (EU AI Act) Art. 4.
        </p>
        <div class="mt-8">
            <span class="inline-block px-5 py-2 text-label font-bold text-white bg-orange rounded-full uppercase tracking-wider">
                4 certificazioni · ~60 ore totali · Conformi EU AI Act
            </span>
        </div>
    </div>
</section>

{{-- ========== CORSI ========== --}}
<section class="py-16 bg-neutral">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12">

        {{-- ===== CONSILIUM ===== --}}
        <div class="bg-white rounded-2xl border border-border overflow-hidden">
            <div class="bg-teal px-8 py-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <h2 class="text-[26px] font-bold text-white">CONSILIUM — Strategia AI per PMI</h2>
                    <span class="inline-block px-3 py-1 text-label font-bold text-teal-dark bg-white/90 rounded-full uppercase tracking-wider self-start">Per Board · Direzione · Imprenditori</span>
                </div>
            </div>
            <div class="p-8">
                <div class="flex flex-wrap gap-4 mb-6 text-body text-mid">
                    <span class="flex items-center gap-1.5"><svg class="h-4 w-4 text-teal" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> 7 ore · 1 giornata</span>
                    <span class="flex items-center gap-1.5"><svg class="h-4 w-4 text-orange" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg> Certified AI Strategist</span>
                </div>

                <p class="text-body text-mid leading-relaxed mb-6">
                    Il laboratorio direzionale Noscite. Dedicato a imprenditori e vertici aziendali che vogliono costruire una visione strategica sull'AI: non seguire la moda, ma guidare una trasformazione con metodo e consapevolezza. Formato 25% teoria, 75% lavoro laboratoriale su canvas. Ogni modulo produce un deliverable operativo che l'azienda conserva.
                </p>

                <h3 class="text-h2 text-dark mb-4">Moduli</h3>
                <div class="space-y-3 mb-8">
                    <div class="flex items-start gap-3 p-4 bg-neutral rounded-xl">
                        <span class="flex-shrink-0 w-8 h-8 bg-teal text-white rounded-lg flex items-center justify-center text-label font-bold">M1</span>
                        <div><p class="text-body font-medium text-dark">Scenario AI per PMI — opportunita, rischi e casi d'uso</p><p class="text-label text-muted mt-1">1h 30'</p></div>
                    </div>
                    <div class="flex items-start gap-3 p-4 bg-neutral rounded-xl">
                        <span class="flex-shrink-0 w-8 h-8 bg-teal text-white rounded-lg flex items-center justify-center text-label font-bold">M2</span>
                        <div><p class="text-body font-medium text-dark">Mappatura dei processi e identificazione casi d'uso AI</p><p class="text-label text-muted mt-1">2h</p></div>
                    </div>
                    <div class="flex items-start gap-3 p-4 bg-neutral rounded-xl">
                        <span class="flex-shrink-0 w-8 h-8 bg-teal text-white rounded-lg flex items-center justify-center text-label font-bold">M3</span>
                        <div><p class="text-body font-medium text-dark">Selezione dei 3 progetti prioritari e definizione degli owner</p><p class="text-label text-muted mt-1">1h 30'</p></div>
                    </div>
                    <div class="flex items-start gap-3 p-4 bg-neutral rounded-xl">
                        <span class="flex-shrink-0 w-8 h-8 bg-teal text-white rounded-lg flex items-center justify-center text-label font-bold">M4</span>
                        <div><p class="text-body font-medium text-dark">AI Usage Policy essenziale e Roadmap a 90 giorni</p><p class="text-label text-muted mt-1">2h</p></div>
                    </div>
                </div>

                <a href="{{ route('contactus') }}" class="inline-flex items-center px-6 py-3 text-body font-semibold text-white bg-orange rounded-lg hover:brightness-110 transition-all">
                    Richiedi informazioni <svg class="ml-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                </a>
            </div>
        </div>

        {{-- ===== INITIUM ===== --}}
        <div class="bg-white rounded-2xl border border-border overflow-hidden">
            <div class="bg-teal-dark px-8 py-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <h2 class="text-[26px] font-bold text-white">INITIUM — Fondamenta AI Operativa</h2>
                    <span class="inline-block px-3 py-1 text-label font-bold text-teal-dark bg-white/90 rounded-full uppercase tracking-wider self-start">Per Manager · Professionisti · Team operativi</span>
                </div>
            </div>
            <div class="p-8">
                <div class="flex flex-wrap gap-4 mb-6 text-body text-mid">
                    <span class="flex items-center gap-1.5"><svg class="h-4 w-4 text-teal" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> 20 ore + 3h esame</span>
                    <span class="flex items-center gap-1.5"><svg class="h-4 w-4 text-orange" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg> Certified AI Productivity User</span>
                </div>

                <p class="text-body text-mid leading-relaxed mb-6">
                    Il punto di partenza per chi vuole capire davvero l'AI generativa. Corso di compliance primaria AI Act: soddisfa i requisiti dell'Art. 4 Reg. UE 2024/1689 per tutto il personale che usa sistemi AI. Formato 70% pratico, 30% teoria.
                </p>

                <h3 class="text-h2 text-dark mb-4">Moduli</h3>
                <div class="space-y-3 mb-8">
                    <div class="flex items-start gap-3 p-4 bg-neutral rounded-xl">
                        <span class="flex-shrink-0 w-8 h-8 bg-teal-dark text-white rounded-lg flex items-center justify-center text-label font-bold">M1</span>
                        <div><p class="text-body font-medium text-dark">Capire l'AI — logica, dati e limiti</p><p class="text-label text-muted mt-1">4h</p></div>
                    </div>
                    <div class="flex items-start gap-3 p-4 bg-neutral rounded-xl">
                        <span class="flex-shrink-0 w-8 h-8 bg-teal-dark text-white rounded-lg flex items-center justify-center text-label font-bold">M2</span>
                        <div><p class="text-body font-medium text-dark">Prompt Engineering e Perplexity in Azione</p><p class="text-label text-muted mt-1">4h</p></div>
                    </div>
                    <div class="flex items-start gap-3 p-4 bg-neutral rounded-xl">
                        <span class="flex-shrink-0 w-8 h-8 bg-teal-dark text-white rounded-lg flex items-center justify-center text-label font-bold">M3</span>
                        <div><p class="text-body font-medium text-dark">Claude e ChatGPT — analisi, contenuti e automazioni</p><p class="text-label text-muted mt-1">4h</p></div>
                    </div>
                    <div class="flex items-start gap-3 p-4 bg-neutral rounded-xl">
                        <span class="flex-shrink-0 w-8 h-8 bg-teal-dark text-white rounded-lg flex items-center justify-center text-label font-bold">M4</span>
                        <div><p class="text-body font-medium text-dark">Vibe Coding e Microsoft Copilot 365</p><p class="text-label text-muted mt-1">4h</p></div>
                    </div>
                    <div class="flex items-start gap-3 p-4 bg-neutral rounded-xl">
                        <span class="flex-shrink-0 w-8 h-8 bg-teal-dark text-white rounded-lg flex items-center justify-center text-label font-bold">M5</span>
                        <div><p class="text-body font-medium text-dark">Second Brain, Data Governance e Private AI</p><p class="text-label text-muted mt-1">4h</p></div>
                    </div>
                    <div class="flex items-start gap-3 p-4 bg-teal-light rounded-xl border border-teal/20">
                        <span class="flex-shrink-0 w-8 h-8 bg-orange text-white rounded-lg flex items-center justify-center text-label font-bold">E</span>
                        <div><p class="text-body font-medium text-dark">Esame: Certified AI Productivity User — soglia 70/100</p><p class="text-label text-muted mt-1">3h</p></div>
                    </div>
                </div>

                <a href="{{ route('contactus') }}" class="inline-flex items-center px-6 py-3 text-body font-semibold text-white bg-orange rounded-lg hover:brightness-110 transition-all">
                    Richiedi informazioni <svg class="ml-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                </a>
            </div>
        </div>

        {{-- ===== STRUCTURA ===== --}}
        <div class="bg-white rounded-2xl border border-border overflow-hidden">
            <div class="bg-dark px-8 py-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <h2 class="text-[26px] font-bold text-white">STRUCTURA — Second Brain Aziendale</h2>
                    <span class="inline-block px-3 py-1 text-label font-bold text-dark bg-white/90 rounded-full uppercase tracking-wider self-start">Per Manager · Knowledge Worker · PM</span>
                </div>
            </div>
            <div class="p-8">
                <div class="flex flex-wrap gap-4 mb-6 text-body text-mid">
                    <span class="flex items-center gap-1.5"><svg class="h-4 w-4 text-teal" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> 24 ore · 6 moduli</span>
                    <span class="flex items-center gap-1.5"><svg class="h-4 w-4 text-orange" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg> Certified Second Brain Implementer</span>
                </div>

                <p class="text-body text-orange font-medium mb-3">Prerequisito consigliato: INITIUM</p>

                <p class="text-body text-mid leading-relaxed mb-6">
                    Un percorso avanzato per implementare sistemi di knowledge management con approccio AI-driven. Produce documentazione audit-ready: vault Obsidian configurato, Playbook di governance, roadmap 90 giorni. Il costo del caos informativo per una PMI con 25 dipendenti e stimato in ~€150.000/anno.
                </p>

                <h3 class="text-h2 text-dark mb-4">Moduli</h3>
                <div class="space-y-3 mb-8">
                    @php
                        $structuraModuli = [
                            ['code' => 'M1', 'title' => 'Metodo CODE e fondamenti del Second Brain', 'dur' => '4h'],
                            ['code' => 'M2', 'title' => 'Setup di Obsidian e Vault Aziendale', 'dur' => '4h'],
                            ['code' => 'M3', 'title' => 'Template e Organizzazione Avanzata', 'dur' => '4h'],
                            ['code' => 'M4', 'title' => 'AI e Automazioni nel Vault', 'dur' => '4h'],
                            ['code' => 'M5', 'title' => 'Collaborazione e Governance del Vault', 'dur' => '4h'],
                            ['code' => 'M6', 'title' => 'Certificazione e Piano d\'Azione', 'dur' => '4h'],
                        ];
                    @endphp
                    @foreach($structuraModuli as $m)
                    <div class="flex items-start gap-3 p-4 bg-neutral rounded-xl">
                        <span class="flex-shrink-0 w-8 h-8 bg-dark text-white rounded-lg flex items-center justify-center text-label font-bold">{{ $m['code'] }}</span>
                        <div><p class="text-body font-medium text-dark">{{ $m['title'] }}</p><p class="text-label text-muted mt-1">{{ $m['dur'] }}</p></div>
                    </div>
                    @endforeach
                </div>

                <a href="{{ route('contactus') }}" class="inline-flex items-center px-6 py-3 text-body font-semibold text-white bg-orange rounded-lg hover:brightness-110 transition-all">
                    Richiedi informazioni <svg class="ml-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                </a>
            </div>
        </div>

        {{-- ===== AI AGENTS & MCP ===== --}}
        <div class="bg-white rounded-2xl border border-border overflow-hidden">
            <div class="bg-orange px-8 py-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <h2 class="text-[26px] font-bold text-white">AI AGENTS & MCP — Agenti AI in Azienda</h2>
                    <span class="inline-block px-3 py-1 text-label font-bold text-orange bg-white/90 rounded-full uppercase tracking-wider self-start">Per Manager · PM · Innovazione · IT</span>
                </div>
            </div>
            <div class="p-8">
                <div class="flex flex-wrap gap-4 mb-6 text-body text-mid">
                    <span class="flex items-center gap-1.5"><svg class="h-4 w-4 text-teal" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> ~9 ore (L1 asincrono ~3h + L2 workshop 6h)</span>
                    <span class="flex items-center gap-1.5"><svg class="h-4 w-4 text-orange" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg> Certified AI Agent Governance Practitioner</span>
                </div>

                <p class="text-body text-orange font-medium mb-3">Prerequisito: INITIUM o equivalente · Max 12 partecipanti L2</p>

                <p class="text-body text-mid leading-relaxed mb-6">
                    L'unico corso del portfolio dedicato alla governance degli agenti AI e del protocollo MCP. Livello 1 asincrono fruibile da qualsiasi dispositivo. Livello 2 workshop in presenza su casi reali aziendali con demo live MCPHub Noscite.
                </p>

                <h3 class="text-h2 text-dark mb-4">Livello 1 — Asincrono (~3h)</h3>
                <div class="space-y-3 mb-6">
                    @foreach(['Il cambio di paradigma', 'Come ragiona un agente', 'Agenti in azienda', 'Rischi e governance'] as $item)
                    <div class="flex items-center gap-3 p-3 bg-neutral rounded-xl">
                        <svg class="h-5 w-5 text-orange flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p class="text-body text-dark">{{ $item }}</p>
                    </div>
                    @endforeach
                </div>

                <h3 class="text-h2 text-dark mb-4">Livello 2 — Workshop (6h, in presenza)</h3>
                <div class="space-y-3 mb-8">
                    @foreach(['Fondamenta MCP', 'Canvas Architetto dell\'Agente', 'Demo MCPHub Noscite', 'Piano d\'Azione 90 giorni'] as $item)
                    <div class="flex items-center gap-3 p-3 bg-neutral rounded-xl">
                        <svg class="h-5 w-5 text-teal flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <p class="text-body text-dark">{{ $item }}</p>
                    </div>
                    @endforeach
                </div>

                <a href="{{ route('contactus') }}" class="inline-flex items-center px-6 py-3 text-body font-semibold text-white bg-orange rounded-lg hover:brightness-110 transition-all">
                    Richiedi informazioni <svg class="ml-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                </a>
            </div>
        </div>

    </div>
</section>

{{-- ========== CTA FINALE ========== --}}
<section class="bg-teal py-16">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <p class="text-h1 text-white leading-relaxed">
            Tutti i percorsi sono costruiti su casi reali, per garantire immediata applicabilita nei processi aziendali.
        </p>
        <p class="mt-4 text-body text-white/80">
            Tutti i corsi sono conformi al Reg. UE 2024/1689 (EU AI Act) Art. 4 — Obbligo di AI Literacy
        </p>
        <a href="{{ route('contactus') }}" class="inline-flex items-center mt-8 px-8 py-3.5 text-body font-semibold text-teal bg-white rounded-lg hover:bg-neutral transition-colors">
            Contattaci per un percorso personalizzato <svg class="ml-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
        </a>
    </div>
</section>

@endsection
