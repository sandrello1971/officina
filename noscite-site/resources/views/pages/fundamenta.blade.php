@extends('layouts.noscite')
@section('title', 'Fundamenta — Il nostro manifesto')
@section('description', 'I principi fondativi di Noscite: umanesimo digitale, etica AI, centralita della persona. Il manifesto che guida ogni intervento di consulenza e formazione AI per PMI italiane.')

@push('meta')
    <x-seo title="Fundamenta" description="I principi fondanti dell'Umanesimo Digitale di Noscite. Il manifesto che guida il nostro lavoro con le PMI." />
@endpush

@section('content')

{{-- Hero --}}
<section class="py-20 bg-gradient-to-b from-primary-50/40 to-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="text-4xl sm:text-5xl font-bold text-gray-900">Fundamenta</h1>
        <p class="mt-4 text-xl text-gray-600 max-w-2xl mx-auto">Il manifesto dell'Umanesimo Digitale</p>
    </div>
</section>

{{-- Citazione --}}
<section class="py-12 bg-white">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <blockquote class="border-l-4 border-primary-500 pl-6 py-4 bg-primary-50/30 rounded-r-xl">
            <p class="text-xl sm:text-2xl text-gray-800 italic leading-relaxed">
                "La tecnologia e un buon servitore, ma un cattivo padrone. Il compito dell'umanesimo digitale e restituire all'uomo il governo consapevole degli strumenti che crea."
            </p>
            <footer class="mt-4 text-sm text-gray-500 font-medium">— Manifesto Noscite</footer>
        </blockquote>
    </div>
</section>

{{-- Intro --}}
<section class="py-8 bg-white">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <p class="text-lg text-gray-600 leading-relaxed">
            I Fundamenta sono i principi che orientano ogni nostra scelta, ogni progetto, ogni relazione. Non sono regole rigide, ma bussole morali che ci guidano nel territorio in continua evoluzione della trasformazione digitale.
        </p>
    </div>
</section>

{{-- Principi --}}
<section class="py-16 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @php
            $principi = [
                ['icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>', 'title' => 'La persona al centro', 'desc' => 'Ogni tecnologia deve servire le persone, non il contrario. Prima di chiederci quale strumento adottare, ci chiediamo quale bisogno umano soddisfare. La digitalizzazione ha senso solo quando amplifica il potenziale delle persone che lavorano in azienda.'],
                ['icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>', 'title' => 'Comprensione prima dell\'azione', 'desc' => 'Non proponiamo soluzioni senza aver compreso il contesto. Ogni intervento inizia con l\'ascolto, l\'analisi e la comprensione profonda dell\'organizzazione, della sua cultura e delle sue dinamiche.'],
                ['icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>', 'title' => 'Etica e responsabilita', 'desc' => 'La tecnologia pone questioni etiche che non possiamo ignorare. Dalla gestione dei dati all\'uso dell\'intelligenza artificiale, operiamo con trasparenza e responsabilita, guidati dal rispetto per le persone e la societa.'],
                ['icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/>', 'title' => 'Accessibilita universale', 'desc' => 'L\'innovazione non e un privilegio riservato alle grandi aziende. Lavoriamo per rendere la trasformazione digitale accessibile e sostenibile anche per le realta piu piccole, con soluzioni proporzionate e percorsi graduali.'],
                ['icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>', 'title' => 'Evoluzione continua', 'desc' => 'Non crediamo nelle rivoluzioni improvvise. Crediamo nell\'evoluzione costante, nei piccoli miglioramenti che si sommano, nella crescita organica che rispetta i ritmi naturali dell\'organizzazione.'],
                ['icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>', 'title' => 'Sapere condiviso', 'desc' => 'La conoscenza genera valore solo quando e condivisa. Investiamo nella formazione e nella diffusione delle competenze, perche un\'organizzazione che impara e un\'organizzazione che cresce.'],
            ];
        @endphp

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            @foreach($principi as $i => $principio)
                <div class="bg-white rounded-2xl p-8 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-start gap-5">
                        <div class="flex-shrink-0 w-12 h-12 bg-primary-100 text-primary-600 rounded-xl flex items-center justify-center">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">{!! $principio['icon'] !!}</svg>
                        </div>
                        <div>
                            <h3 class="text-xl font-semibold text-gray-900 mb-3">{{ $principio['title'] }}</h3>
                            <p class="text-gray-600 leading-relaxed">{{ $principio['desc'] }}</p>
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
        <h2 class="text-3xl font-bold text-white">Condividi i nostri principi?</h2>
        <p class="mt-4 text-primary-100 text-lg">Scopri come li mettiamo in pratica ogni giorno.</p>
        <a href="{{ route('methodus') }}" class="inline-flex items-center mt-8 px-8 py-3.5 text-base font-semibold text-primary-700 bg-white rounded-lg hover:bg-primary-50 transition-colors">Scopri il metodo</a>
    </div>
</section>

@endsection
