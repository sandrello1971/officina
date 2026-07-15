{{--
    Landing pubblica del corso AI ACT Essentials — stile GLITCH (come "/"):
    nero profondo, avorio, cremisi, font mono. Standalone: NON usa layouts.app.
    Riusa resources/css/glitch-landing.css (stesse classi glitch-*).
--}}
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="AI ACT Essentials: corso asincrono di 6 ore per assolvere all'obbligo di alfabetizzazione dell'Articolo 4 dell'AI Act (Reg. UE 2024/1689). Per PMI italiane che usano l'intelligenza artificiale. Attestato finale.">
    <title>AI ACT Essentials — Alfabetizzazione AI Act per PMI · Effetto Glitch / Officina</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    @vite(['resources/css/glitch-landing.css'])
</head>
<body class="bg-glitch-black text-glitch-ivory font-mono antialiased selection:bg-glitch-red selection:text-glitch-black">

    <a href="#contenuto"
       class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:bg-glitch-red focus:text-glitch-black focus:px-4 focus:py-2 glitch-navlink">
        Salta al contenuto
    </a>

    {{-- ===================== NAV ===================== --}}
    <header class="border-b border-glitch-ivory/10">
        <nav class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-5 py-5 sm:px-8"
             aria-label="Navigazione principale">
            <a href="/" class="glitch-navlink text-glitch-ivory">
                EFFETTO GLITCH <span class="text-glitch-red">/</span> OFFICINA
            </a>
            <div class="flex items-center gap-5 sm:gap-8">
                <a href="/" class="glitch-navlink text-glitch-ivory hidden sm:inline">Home</a>
                <a href="{{ route('contatti') }}" class="glitch-navlink text-glitch-ivory hidden sm:inline">Contatti</a>
                <a href="{{ route('student.login') }}" class="glitch-navlink text-glitch-red">Accedi</a>
            </div>
        </nav>
    </header>

    <main id="contenuto">

        {{-- ===================== HERO ===================== --}}
        <section class="mx-auto max-w-6xl px-5 pt-20 pb-20 sm:px-8 sm:pt-28 sm:pb-24"
                 aria-labelledby="titolo">
            <p class="glitch-tag mb-8">AI ACT Essentials · Obbligo Art. 4 · Reg. UE 2024/1689</p>
            <h1 id="titolo" class="glitch-manifesto text-glitch-ivory max-w-4xl">
                Metti in regola chi usa l'AI<span class="text-glitch-red">.</span>
            </h1>
            <p class="glitch-body mt-10 max-w-2xl text-glitch-ivory/80">
                Il corso di <span class="text-glitch-ivory">alfabetizzazione all'AI Act</span> per le PMI
                italiane. Asincrono, concreto: assolve l'obbligo dell'Articolo 4 e lo documenta.
            </p>
            <p class="glitch-tag mt-8 text-glitch-ivory/60">
                6 ore <span class="text-glitch-red">·</span> 100% asincrono
                <span class="text-glitch-red">·</span> 8 moduli
                <span class="text-glitch-red">·</span> attestato finale
            </p>
            <div class="mt-12 flex flex-wrap items-center gap-6">
                <a href="{{ route('contatti') }}" class="glitch-cta inline-block px-8 py-4">
                    Attiva il corso per la tua azienda
                </a>
                <a href="{{ route('student.login') }}" class="glitch-navlink text-glitch-ivory/70">
                    Hai già un accesso? Entra &rarr;
                </a>
            </div>
        </section>

        {{-- ===================== 01 · PERCHÉ ORA ===================== --}}
        <section class="border-t border-glitch-ivory/10" aria-labelledby="perche-titolo">
            <div class="mx-auto grid max-w-6xl gap-6 px-5 py-20 sm:grid-cols-[auto_1fr] sm:gap-12 sm:px-8 sm:py-24">
                <span class="glitch-numeral" aria-hidden="true">01</span>
                <div class="max-w-2xl">
                    <p class="glitch-tag mb-4">Perché ora</p>
                    <h2 id="perche-titolo" class="glitch-section-title text-glitch-ivory">L'obbligo è già in vigore.</h2>
                    <p class="glitch-body mt-6 text-glitch-ivory/80">
                        Dal <span class="text-glitch-ivory">2 febbraio 2025</span> l'Articolo 4 dell'AI Act
                        impone a chiunque usi sistemi di intelligenza artificiale di garantire un livello
                        adeguato di <span class="text-glitch-ivory">alfabetizzazione</span> del personale.
                        AI ACT Essentials è il percorso che assolve a quest'obbligo — e lascia una prova
                        che l'hai fatto.
                    </p>
                </div>
            </div>
        </section>

        {{-- ===================== 02 · COSA IMPARI ===================== --}}
        <section class="border-t border-glitch-ivory/10" aria-labelledby="moduli-titolo">
            <div class="mx-auto grid max-w-6xl gap-6 px-5 py-20 sm:grid-cols-[auto_1fr] sm:gap-12 sm:px-8 sm:py-24">
                <span class="glitch-numeral" aria-hidden="true">02</span>
                <div class="max-w-3xl">
                    <p class="glitch-tag mb-4">Cosa impari</p>
                    <h2 id="moduli-titolo" class="glitch-section-title text-glitch-ivory">Otto moduli, un percorso.</h2>
                    <ol class="mt-8 grid gap-x-12 gap-y-4 sm:grid-cols-2">
                        @foreach([
                            'Perché sei qui: l\'obbligo di alfabetizzazione',
                            'Capire l\'AI: cos\'è, cosa regola la norma',
                            'A ciascuno la sua competenza',
                            'Le pratiche vietate: l\'Articolo 5',
                            'Classificare il rischio dei tuoi sistemi',
                            'Le regole d\'uso: la policy aziendale',
                            'Usare l\'AI in modo critico e conforme',
                            'Documentare la formazione e verifica finale',
                        ] as $i => $modulo)
                        <li class="glitch-body flex gap-4 text-glitch-ivory/80 border-t border-glitch-ivory/10 pt-3">
                            <span class="text-glitch-red">{{ sprintf('%02d', $i + 1) }}</span>
                            <span>{{ $modulo }}</span>
                        </li>
                        @endforeach
                    </ol>
                </div>
            </div>
        </section>

        {{-- ===================== 03 · PER CHI / COSA OTTIENI ===================== --}}
        <section class="border-t border-glitch-ivory/10" aria-labelledby="perchi-titolo">
            <div class="mx-auto grid max-w-6xl gap-6 px-5 py-20 sm:grid-cols-[auto_1fr] sm:gap-12 sm:px-8 sm:py-24">
                <span class="glitch-numeral" aria-hidden="true">03</span>
                <div class="max-w-3xl">
                    <p class="glitch-tag mb-4">Per chi · cosa ottieni</p>
                    <h2 id="perchi-titolo" class="glitch-section-title text-glitch-ivory">Per chi usa l'AI ogni giorno.</h2>
                    <div class="mt-8 grid gap-10 sm:grid-cols-2">
                        <div>
                            <p class="glitch-tag mb-3 text-glitch-ivory/60">Per chi è</p>
                            <p class="glitch-body text-glitch-ivory/80">
                                PMI italiane, titolari, responsabili e dipendenti che usano strumenti di AI
                                a rischio minimo o limitato — e chi deve dimostrare di aver formato il personale.
                            </p>
                        </div>
                        <div>
                            <p class="glitch-tag mb-3 text-glitch-ivory/60">Cosa ottieni</p>
                            <p class="glitch-body text-glitch-ivory/80">
                                Un percorso asincrono, criteri per classificare il rischio dei tuoi sistemi,
                                regole d'uso pronte, e l'attestato finale che concorre a documentare le misure adottate.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- ===================== CALLOUT + CTA ===================== --}}
        <section class="border-t border-glitch-ivory/10" aria-labelledby="chiusura">
            <div class="mx-auto max-w-6xl px-5 py-24 sm:px-8 sm:py-32">
                <p id="chiusura" class="glitch-callout max-w-3xl pl-6">
                    Conformità non è un modulo da spuntare. È sapere cosa stai facendo.
                </p>
                <div class="mt-12 flex flex-wrap items-center gap-6">
                    <a href="{{ route('contatti') }}" class="glitch-cta inline-block px-8 py-4">
                        Attiva il corso per la tua azienda
                    </a>
                    <span class="glitch-body text-sm text-glitch-ivory/60">
                        Formi il team e documenti l'Articolo 4.
                    </span>
                </div>
            </div>
        </section>
    </main>

    {{-- ===================== FOOTER ===================== --}}
    <footer class="border-t border-glitch-ivory/10">
        <div class="mx-auto grid max-w-6xl gap-8 px-5 py-12 sm:grid-cols-3 sm:px-8">
            <p class="glitch-body text-sm text-glitch-ivory/60">
                officina.effettoglitch.it
                <span class="text-glitch-red">·</span>
                MMXXVI
            </p>
            <p class="glitch-body text-sm text-glitch-ivory/60 sm:text-center">
                Un progetto <span class="text-glitch-ivory">Effetto Glitch</span> — effettoglitch.it
            </p>
            <p class="glitch-body text-sm text-glitch-ivory/60 sm:text-right">
                <a href="{{ route('contatti') }}" class="glitch-navlink text-glitch-ivory">Contatti</a>
            </p>
        </div>
    </footer>

</body>
</html>
