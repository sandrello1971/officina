@extends('layouts.noscite')
@section('title', 'Risorse — Materiali e strumenti')

@push('meta')
    <x-seo title="Risorse" description="Guide, template e strumenti gratuiti per la trasformazione digitale della tua PMI. Risorse pratiche dall'esperienza Noscite." />
@endpush

@section('content')

{{-- Hero --}}
<section class="py-20 bg-gradient-to-b from-primary-50/40 to-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="text-4xl sm:text-5xl font-bold text-gray-900">Risorse</h1>
        <p class="mt-4 text-xl text-gray-600 max-w-2xl mx-auto">Materiali e strumenti gratuiti per iniziare il tuo percorso di trasformazione digitale.</p>
    </div>
</section>

{{-- Risorse --}}
<section class="py-20 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">

            @php
                $risorse = [
                    ['type' => 'Guida', 'title' => 'Introduzione all\'AI per le PMI', 'desc' => 'Una guida pratica per comprendere cos\'e l\'intelligenza artificiale e come puo essere applicata nella tua impresa.', 'color' => 'primary'],
                    ['type' => 'Template', 'title' => 'Canvas di Maturita Digitale', 'desc' => 'Uno strumento di autovalutazione per misurare il livello di digitalizzazione della tua organizzazione.', 'color' => 'secondary'],
                    ['type' => 'Guida', 'title' => 'GDPR e Dati: cosa sapere', 'desc' => 'Le basi della protezione dei dati per le PMI: obblighi, best practice e strumenti per la compliance.', 'color' => 'primary'],
                    ['type' => 'Checklist', 'title' => 'Digital Readiness Checklist', 'desc' => 'Una checklist completa per valutare se la tua azienda e pronta per la trasformazione digitale.', 'color' => 'secondary'],
                    ['type' => 'Template', 'title' => 'Piano di Formazione Digitale', 'desc' => 'Un template per progettare il percorso formativo del tuo team sulle competenze digitali.', 'color' => 'primary'],
                    ['type' => 'Guida', 'title' => 'Automazione dei Processi', 'desc' => 'Come identificare i processi automatizzabili e scegliere gli strumenti giusti per la tua azienda.', 'color' => 'secondary'],
                ];
            @endphp

            @foreach($risorse as $risorsa)
                <div class="bg-gray-50 rounded-2xl p-6 hover:shadow-lg transition-shadow flex flex-col">
                    <span class="inline-block px-3 py-1 text-xs font-semibold text-{{ $risorsa['color'] }}-700 bg-{{ $risorsa['color'] }}-100 rounded-full uppercase tracking-wide self-start mb-4">
                        {{ $risorsa['type'] }}
                    </span>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">{{ $risorsa['title'] }}</h3>
                    <p class="text-gray-600 text-sm leading-relaxed flex-1">{{ $risorsa['desc'] }}</p>
                    <div class="mt-5">
                        <span class="inline-flex items-center text-sm font-medium text-gray-400 cursor-not-allowed">
                            Disponibile a breve
                            <svg class="ml-1 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                        </span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- Newsletter CTA --}}
<section class="py-16 bg-gray-900">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-2xl font-bold text-white mb-2">Vuoi ricevere le nuove risorse?</h2>
        <p class="text-gray-400 text-sm mb-6">Iscriviti alla newsletter per essere avvisato quando pubblichiamo nuovi materiali.</p>
        <div class="max-w-md mx-auto">
            <livewire:newsletter-subscribe />
        </div>
    </div>
</section>

@endsection
