@extends('layouts.noscite')
@section('title', 'Commentarium')

@push('meta')
    <x-seo
        title="Commentarium"
        description="Articoli, approfondimenti e riflessioni su trasformazione digitale, intelligenza artificiale e innovazione per le PMI."
    />
@endpush

@section('content')

<section class="py-16 bg-gradient-to-b from-gray-50 to-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-3xl mx-auto mb-12">
            <h1 class="text-4xl sm:text-5xl font-bold text-gray-900">Commentarium</h1>
            <p class="mt-4 text-lg text-gray-600">
                Riflessioni, analisi e guide pratiche sul digitale e l'innovazione per le PMI.
            </p>
        </div>

        <livewire:commentarium-list />
    </div>
</section>

@endsection
