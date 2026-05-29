@extends('layouts.noscite')
@section('title', 'Methodus — Il nostro metodo')
@section('description', 'Il metodo Noscite in 5 fasi: diagnosi dei processi, strategia AI, formazione del team, implementazione automazioni misurabili, evoluzione continua. Trasformazione digitale concreta per PMI.')

@push('meta')
    <x-seo title="Methodus" description="Il metodo Noscite in 5 fasi: Diagnosi, Strategia, Formazione, Implementazione, Evoluzione. Un percorso strutturato per la trasformazione digitale delle PMI." />
@endpush

@section('content')

{{-- Hero --}}
<section class="py-20 bg-gradient-to-b from-primary-50/40 to-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="text-4xl sm:text-5xl font-bold text-gray-900">Methodus</h1>
        <p class="mt-4 text-xl text-gray-600 max-w-2xl mx-auto">Cinque fasi per una trasformazione digitale consapevole, misurabile e duratura.</p>
    </div>
</section>

{{-- Timeline --}}
<section class="py-20 bg-white">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        @php
            $fasi = [
                [
                    'num' => '01', 'title' => 'Diagnosi', 'color' => 'primary',
                    'desc' => 'Ogni percorso inizia con la comprensione. Analizziamo lo stato attuale dell\'organizzazione attraverso interviste, audit dei processi e valutazione della Maturità AI.',
                    'details' => ['Mappatura dei processi aziendali', 'Assessment della Maturità AI', 'Analisi delle competenze del team', 'Identificazione delle criticita e opportunita'],
                ],
                [
                    'num' => '02', 'title' => 'Strategia', 'color' => 'secondary',
                    'desc' => 'Sulla base della diagnosi, definiamo una roadmap personalizzata con obiettivi chiari, priorita condivise e KPI misurabili, sempre in linea con le risorse disponibili.',
                    'details' => ['Definizione degli obiettivi strategici', 'Prioritizzazione degli interventi', 'Piano d\'azione con milestone', 'Budget e allocazione delle risorse'],
                ],
                [
                    'num' => '03', 'title' => 'Formazione', 'color' => 'primary',
                    'desc' => 'Le persone sono il motore del cambiamento. Progettiamo percorsi formativi su misura che costruiscono competenze digitali concrete e alimentano una cultura dell\'innovazione.',
                    'details' => ['Workshop pratici e interattivi', 'Formazione su AI e strumenti digitali', 'Percorsi per manager e team operativi', 'Mentoring e affiancamento continuo'],
                ],
                [
                    'num' => '04', 'title' => 'Implementazione', 'color' => 'secondary',
                    'desc' => 'Accompagniamo l\'adozione degli strumenti e dei processi passo dopo passo, garantendo un\'integrazione fluida nell\'operativita quotidiana senza interruzioni.',
                    'details' => ['Selezione e configurazione degli strumenti', 'Integrazione con i sistemi esistenti', 'Testing e validazione', 'Go-live assistito con supporto dedicato'],
                ],
                [
                    'num' => '05', 'title' => 'Evoluzione', 'color' => 'primary',
                    'desc' => 'La trasformazione non si ferma al go-live. Monitoriamo i risultati, raccogliamo feedback e ottimizziamo la strategia per garantire una crescita continua e adattiva.',
                    'details' => ['Monitoraggio dei KPI definiti', 'Ottimizzazione continua dei processi', 'Aggiornamento della strategia', 'Scalabilita e nuove opportunita'],
                ],
            ];
        @endphp

        <div class="relative">
            {{-- Linea verticale --}}
            <div class="absolute left-6 top-0 bottom-0 w-0.5 bg-gray-200 hidden sm:block"></div>

            <div class="space-y-12">
                @foreach($fasi as $fase)
                    <div class="relative flex gap-8">
                        {{-- Numero --}}
                        <div class="flex-shrink-0 relative z-10">
                            <div class="w-12 h-12 bg-{{ $fase['color'] }}-600 text-white rounded-full flex items-center justify-center font-bold text-sm shadow-lg">
                                {{ $fase['num'] }}
                            </div>
                        </div>

                        {{-- Contenuto --}}
                        <div class="flex-1 bg-gray-50 rounded-2xl p-6 sm:p-8 pb-8">
                            <h3 class="text-2xl font-bold text-gray-900 mb-3">{{ $fase['title'] }}</h3>
                            <p class="text-gray-600 leading-relaxed mb-5">{{ $fase['desc'] }}</p>
                            <ul class="space-y-2">
                                @foreach($fase['details'] as $detail)
                                    <li class="flex items-center gap-3 text-sm text-gray-700">
                                        <svg class="h-4 w-4 text-{{ $fase['color'] }}-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                        {{ $detail }}
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>

{{-- CTA --}}
<section class="bg-primary-600 py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-3xl font-bold text-white">Pronto a iniziare?</h2>
        <p class="mt-4 text-primary-100 text-lg">Scopri come il nostro metodo puo trasformare la tua impresa.</p>
        <a href="{{ route('contactus') }}" class="inline-flex items-center mt-8 px-8 py-3.5 text-base font-semibold text-primary-700 bg-white rounded-lg hover:bg-primary-50 transition-colors">Contattaci</a>
    </div>
</section>

@endsection
