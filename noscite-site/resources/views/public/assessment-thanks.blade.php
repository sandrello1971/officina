@extends('layouts.noscite')

@section('title', 'Grazie — Mappa Maturità AI')
@section('description', 'Grazie per aver completato la Mappa di Maturità AI Noscite. Il tuo report è in arrivo via email.')

@section('content')
<div class="max-w-3xl mx-auto px-6 py-20 text-center">
    <h1 class="text-4xl font-light text-teal mb-6">Grazie!</h1>

    <p class="text-lg text-mid mb-4">
        Il tuo report personalizzato è in arrivo nella tua casella email entro qualche minuto.
    </p>

    <p class="text-mid mb-4">
        Un nostro consulente Noscite ti contatterà entro <strong>24 ore</strong> per discutere il tuo percorso formativo AI Act compliant.
    </p>

    <p class="text-mid mt-8">
        Nel frattempo, se vuoi anticipare i tempi puoi scriverci a
        <a href="mailto:sales@noscite.it" class="text-teal underline">sales@noscite.it</a>.
    </p>

    @if($leadRef && $leadRef !== 'pending')
        <p class="mt-10 text-xs text-muted">Riferimento richiesta: {{ $leadRef }}</p>
    @endif

    <div class="mt-10 flex flex-wrap justify-center gap-3">
        <a href="{{ url('/') }}"
           class="inline-block px-7 py-3 bg-orange text-white rounded font-bold hover:opacity-90 transition">
            Torna alla homepage
        </a>
        <a href="https://atheneum.noscite.it/" target="_blank" rel="noopener"
           class="inline-block px-7 py-3 bg-teal text-white rounded font-bold hover:opacity-90 transition">
            Visita il nostro Atheneum
        </a>
    </div>
</div>
@endsection
