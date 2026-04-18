@extends('layouts.noscite')
@section('title', 'Percorsi — I nostri programmi')

@push('meta')
    <x-seo title="Percorsi" description="Percorsi di trasformazione digitale personalizzati per diversi profili aziendali. Scopri il percorso giusto per la tua PMI." />
@endpush

@section('content')

{{-- Hero --}}
<section class="py-20 bg-gradient-to-b from-primary-50/40 to-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="text-4xl sm:text-5xl font-bold text-gray-900">Percorsi</h1>
        <p class="mt-4 text-xl text-gray-600 max-w-2xl mx-auto">Ogni impresa e unica. Per questo offriamo percorsi personalizzati per diversi profili e obiettivi.</p>
    </div>
</section>

{{-- Percorsi --}}
<section class="py-20 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @php
            $percorsi = [
                ['title' => 'Startup & Microimprese', 'subtitle' => 'Muovere i primi passi nel digitale', 'desc' => 'Per chi parte da zero o quasi. Un percorso essenziale per costruire le fondamenta digitali: dalla scelta degli strumenti giusti alla formazione del team, fino alla definizione di processi snelli e scalabili.', 'duration' => '2-3 mesi', 'color' => 'primary'],
                ['title' => 'PMI in crescita', 'subtitle' => 'Scalare con metodo', 'desc' => 'Per imprese che crescono e hanno bisogno di struttura. Ottimizzazione dei processi, integrazione degli strumenti, automazione delle attivita ripetitive e costruzione di una governance digitale solida.', 'duration' => '3-6 mesi', 'color' => 'secondary'],
                ['title' => 'Aziende strutturate', 'subtitle' => 'Innovare dall\'interno', 'desc' => 'Per organizzazioni mature che vogliono fare il salto verso l\'AI e l\'innovazione avanzata. Dalla data strategy all\'implementazione di soluzioni predittive, con un focus sulla cultura dell\'innovazione.', 'duration' => '6-12 mesi', 'color' => 'primary'],
                ['title' => 'Settore manifatturiero', 'subtitle' => 'Industria 4.0 accessibile', 'desc' => 'Percorso specifico per il settore manifatturiero: IoT, manutenzione predittiva, ottimizzazione della supply chain e digitalizzazione della produzione, con un approccio graduale e pragmatico.', 'duration' => '4-8 mesi', 'color' => 'secondary'],
            ];
        @endphp

        <div class="space-y-8">
            @foreach($percorsi as $i => $percorso)
                <div class="bg-gray-50 rounded-2xl p-8 sm:p-10 flex flex-col md:flex-row gap-8 items-start hover:shadow-lg transition-shadow">
                    <div class="flex-shrink-0 w-16 h-16 bg-{{ $percorso['color'] }}-100 text-{{ $percorso['color'] }}-600 rounded-2xl flex items-center justify-center text-2xl font-bold">
                        {{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}
                    </div>
                    <div class="flex-1">
                        <h3 class="text-2xl font-bold text-gray-900">{{ $percorso['title'] }}</h3>
                        <p class="text-{{ $percorso['color'] }}-600 font-medium mt-1">{{ $percorso['subtitle'] }}</p>
                        <p class="text-gray-600 leading-relaxed mt-4">{{ $percorso['desc'] }}</p>
                        <div class="mt-4 inline-flex items-center gap-2 text-sm text-gray-500">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Durata indicativa: {{ $percorso['duration'] }}
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
        <h2 class="text-3xl font-bold text-white">Non sai quale percorso scegliere?</h2>
        <p class="mt-4 text-primary-100 text-lg">Parlaci della tua impresa e ti aiuteremo a individuare il percorso piu adatto.</p>
        <a href="{{ route('contactus') }}" class="inline-flex items-center mt-8 px-8 py-3.5 text-base font-semibold text-primary-700 bg-white rounded-lg hover:bg-primary-50 transition-colors">Richiedi una consulenza</a>
    </div>
</section>

@endsection
