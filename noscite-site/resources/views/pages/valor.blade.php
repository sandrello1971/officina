@extends('layouts.noscite')
@section('title', 'Valor — I nostri valori')
@section('description', 'I valori che guidano Noscite: rigore, etica, innovazione concreta, accessibilita, collaborazione. Scopri come questi principi si traducono in risultati misurabili per le PMI italiane.')

@push('meta')
    <x-seo title="Valor" description="I valori di Noscite: Rigore, Etica, Innovazione, Accessibilita, Collaborazione, Continuita. I principi che guidano ogni nostro progetto." />
@endpush

@section('content')

{{-- Hero --}}
<section class="py-20 bg-gradient-to-b from-primary-50/40 to-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="text-4xl sm:text-5xl font-bold text-gray-900">Valor</h1>
        <p class="mt-4 text-xl text-gray-600 max-w-2xl mx-auto">I principi che orientano ogni progetto e ogni relazione.</p>
    </div>
</section>

{{-- Valori --}}
<section class="py-20 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @php
            $valori = [
                ['icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>', 'title' => 'Rigore', 'desc' => 'La precisione non e pedanteria, e rispetto. Rispetto per il cliente, per i dati, per i risultati. Ogni nostra analisi e metodica, ogni raccomandazione e supportata da evidenze, ogni progetto segue standard di qualita rigorosi. Il rigore e il fondamento su cui costruiamo la fiducia.', 'color' => 'primary'],
                ['icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>', 'title' => 'Etica', 'desc' => 'In un mondo dove i dati sono il nuovo petrolio, l\'etica non e opzionale. Operiamo con trasparenza totale, rispettiamo la privacy, utilizziamo l\'AI in modo responsabile e ci impegniamo a considerare l\'impatto sociale di ogni soluzione che proponiamo.', 'color' => 'secondary'],
                ['icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>', 'title' => 'Innovazione', 'desc' => 'Non inseguiamo le mode tecnologiche. Selezioniamo con cura le innovazioni che possono generare valore reale per i nostri clienti. La nostra curiosita e al servizio della concretezza: ogni soluzione deve essere praticabile, misurabile e sostenibile.', 'color' => 'primary'],
                ['icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/>', 'title' => 'Accessibilita', 'desc' => 'La trasformazione digitale non deve essere un privilegio delle grandi aziende. Progettiamo soluzioni proporzionate, con costi sostenibili e percorsi graduali, perche ogni impresa — indipendentemente dalla dimensione — meriti di innovare.', 'color' => 'secondary'],
                ['icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>', 'title' => 'Collaborazione', 'desc' => 'Non siamo fornitori, siamo partner. Lavoriamo fianco a fianco con i nostri clienti, condividendo conoscenze, responsabilita e successi. La vera trasformazione nasce dalla collaborazione autentica tra chi conosce l\'impresa e chi conosce la tecnologia.', 'color' => 'primary'],
                ['icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>', 'title' => 'Continuita', 'desc' => 'Non crediamo nei progetti mordi-e-fuggi. Ogni intervento e pensato per generare valore nel tempo, con logiche di evoluzione continua. Restiamo al fianco dei nostri clienti anche dopo il go-live, perche la trasformazione e un viaggio, non una destinazione.', 'color' => 'secondary'],
            ];
        @endphp

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            @foreach($valori as $valore)
                <div class="bg-gray-50 rounded-2xl p-8 hover:shadow-lg transition-shadow">
                    <div class="flex items-start gap-5">
                        <div class="flex-shrink-0 w-14 h-14 bg-{{ $valore['color'] }}-100 text-{{ $valore['color'] }}-600 rounded-xl flex items-center justify-center">
                            <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">{!! $valore['icon'] !!}</svg>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-gray-900 mb-3">{{ $valore['title'] }}</h3>
                            <p class="text-gray-600 leading-relaxed">{{ $valore['desc'] }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- CTA --}}
<section class="bg-primary-600 py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-3xl font-bold text-white">I nostri valori in azione</h2>
        <p class="mt-4 text-primary-100 text-lg">Scopri come applichiamo questi principi nel nostro metodo di lavoro.</p>
        <a href="{{ route('methodus') }}" class="inline-flex items-center mt-8 px-8 py-3.5 text-base font-semibold text-primary-700 bg-white rounded-lg hover:bg-primary-50 transition-colors">Scopri il metodo</a>
    </div>
</section>

@endsection
