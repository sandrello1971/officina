@extends('layouts.app')
@section('title', 'AI ACT Essentials — Alfabetizzazione AI Act per PMI')
@section('description', 'AI ACT Essentials: corso asincrono di 6 ore per assolvere all\'obbligo di alfabetizzazione dell\'Articolo 4 dell\'AI Act (Reg. UE 2024/1689). Per PMI italiane che usano strumenti di intelligenza artificiale. Attestato finale.')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-16">

    {{-- HERO --}}
    <span class="badge-orange mb-4 inline-block">Obbligo di legge &middot; Art. 4 AI Act</span>
    <h1 class="text-4xl font-bold mb-2" style="color:#1A1F1F">AI ACT Essentials</h1>
    <p class="text-lg mb-6" style="color:#4A5252">
        Il corso di <strong>alfabetizzazione all'AI Act</strong> per chi, in azienda, usa strumenti di
        intelligenza artificiale. Asincrono, concreto, pensato per le PMI italiane.
    </p>
    <div class="flex flex-wrap gap-3 mb-8">
        <span class="badge-orange">6 ore &middot; 100% asincrono</span>
        <span class="badge-teal">Attestato finale</span>
        <span class="badge-teal">Reg. UE 2024/1689</span>
    </div>

    <div class="flex flex-wrap gap-3 mb-12">
        <a href="/contatti" class="btn-orange">Attiva il corso per la tua azienda</a>
        <a href="/learn/login" class="btn-outline">Accedi alla piattaforma</a>
    </div>

    {{-- PERCHÉ ORA --}}
    <div class="py-8 px-6 rounded-xl mb-8" style="background:#FDECE2;border:1px solid #E28A53">
        <p class="text-xs font-bold uppercase mb-3 tracking-widest" style="color:#A8521F">Perché ora</p>
        <p class="text-base" style="color:#4A5252;line-height:1.7">
            Dal <strong>2 febbraio 2025</strong> è in vigore l'<strong>Articolo 4 dell'AI Act</strong>
            (Regolamento UE 2024/1689): chiunque usi sistemi di intelligenza artificiale nella propria
            attività deve adottare misure per garantire un <strong>livello adeguato di alfabetizzazione</strong>
            del personale che li utilizza. AI ACT Essentials è il percorso che assolve a quest'obbligo e
            lo <strong>documenta</strong>.
        </p>
    </div>

    {{-- COSA IMPARI --}}
    <div class="corso-card mb-8">
        <h2 class="text-xl font-bold mb-4" style="color:#1A1F1F">Cosa impari — 8 moduli</h2>
        <div class="grid md:grid-cols-2 gap-2">
            <div class="text-sm p-3 rounded" style="background:#E8F5F5"><strong>M1</strong> Perché sei qui: l'obbligo di alfabetizzazione</div>
            <div class="text-sm p-3 rounded" style="background:#E8F5F5"><strong>M2</strong> Capire l'AI: cos'è, cosa regola la norma</div>
            <div class="text-sm p-3 rounded" style="background:#E8F5F5"><strong>M3</strong> A ciascuno la sua competenza</div>
            <div class="text-sm p-3 rounded" style="background:#E8F5F5"><strong>M4</strong> Le pratiche vietate: l'Articolo 5</div>
            <div class="text-sm p-3 rounded" style="background:#E8F5F5"><strong>M5</strong> Classificare il rischio dei tuoi sistemi</div>
            <div class="text-sm p-3 rounded" style="background:#E8F5F5"><strong>M6</strong> Le regole d'uso: la policy aziendale</div>
            <div class="text-sm p-3 rounded" style="background:#E8F5F5"><strong>M7</strong> Usare l'AI in modo critico e conforme</div>
            <div class="text-sm p-3 rounded" style="background:#E8F5F5"><strong>M8</strong> Documentare la formazione e verifica finale</div>
        </div>
    </div>

    {{-- PER CHI + COSA OTTIENI --}}
    <div class="grid md:grid-cols-2 gap-6 mb-8">
        <div class="corso-card">
            <h2 class="text-lg font-bold mb-3" style="color:#1A1F1F">Per chi è</h2>
            <ul class="text-sm space-y-2" style="color:#4A5252">
                <li>&#9644; <strong>PMI italiane</strong> che usano strumenti di AI nel lavoro quotidiano</li>
                <li>&#9644; Titolari, responsabili e dipendenti che usano AI a <strong>rischio minimo o limitato</strong></li>
                <li>&#9644; Chi deve <strong>dimostrare</strong> di aver formato il personale</li>
            </ul>
        </div>
        <div class="corso-card">
            <h2 class="text-lg font-bold mb-3" style="color:#1A1F1F">Cosa ottieni</h2>
            <ul class="text-sm space-y-2" style="color:#4A5252">
                <li>&#9644; Un percorso <strong>asincrono</strong>, da seguire con i tuoi tempi</li>
                <li>&#9644; Regole d'uso e criteri per <strong>classificare il rischio</strong> dei tuoi sistemi</li>
                <li>&#9644; L'<strong>attestato finale</strong> (al superamento dei quiz) che concorre a documentare le misure adottate</li>
            </ul>
        </div>
    </div>

    {{-- FUNDAMENTA — coerenza brand --}}
    <div class="py-8 px-6 rounded-xl mb-10" style="background:#E8F5F5">
        <p class="text-xs font-bold uppercase mb-4 tracking-widest" style="color:#8A9696">Radicato nell'Umanesimo Digitale Effetto Glitch</p>
        <div class="grid md:grid-cols-3 gap-4">
            <div class="flex gap-3">
                <span style="color:#55B1AE;font-size:1.2rem">&#9644;</span>
                <div>
                    <div class="font-bold text-sm mb-1" style="color:#1A1F1F">La persona al centro</div>
                    <p class="text-xs" style="color:#4A5252">Non basta "usare l'AI": impari a farlo con consapevolezza e senso critico.</p>
                </div>
            </div>
            <div class="flex gap-3">
                <span style="color:#55B1AE;font-size:1.2rem">&#9644;</span>
                <div>
                    <div class="font-bold text-sm mb-1" style="color:#1A1F1F">Comprensione prima dell'azione</div>
                    <p class="text-xs" style="color:#4A5252">Prima capisci cosa impone la norma, poi la applichi al tuo contesto reale.</p>
                </div>
            </div>
            <div class="flex gap-3">
                <span style="color:#55B1AE;font-size:1.2rem">&#9644;</span>
                <div>
                    <div class="font-bold text-sm mb-1" style="color:#1A1F1F">Conformità concreta</div>
                    <p class="text-xs" style="color:#4A5252">Ogni modulo lascia qualcosa di operativo: policy, criteri, documentazione.</p>
                </div>
            </div>
        </div>
    </div>

    {{-- CTA finale --}}
    <div class="text-center py-10 px-6 rounded-xl" style="background:#1A1F1F">
        <h2 class="text-2xl font-bold mb-3" style="color:white">Metti in regola la tua PMI sull'AI Act</h2>
        <p class="text-base mb-6" style="color:#8A9696">Attiva AI ACT Essentials per il tuo team e documenta la formazione richiesta dall'Articolo 4.</p>
        <a href="/contatti" class="btn-orange">Richiedi l'attivazione &rarr;</a>
    </div>

</div>
@endsection
