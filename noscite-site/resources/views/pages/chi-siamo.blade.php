@extends('layouts.noscite')
@section('title', 'Chi siamo')

@push('meta')
    <x-seo title="Chi siamo" description="Scopri il team Noscite e la nostra missione: accompagnare le PMI italiane nella trasformazione digitale con umanesimo e metodo." />
@endpush

@section('content')

<section class="py-20 bg-gradient-to-b from-primary-50/40 to-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="text-4xl sm:text-5xl font-bold text-gray-900">Chi siamo</h1>
        <p class="mt-4 text-xl text-gray-600 max-w-2xl mx-auto">Un team di professionisti appassionati di innovazione responsabile al servizio delle PMI italiane.</p>
    </div>
</section>

<section class="py-16 bg-white">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 prose prose-lg prose-gray max-w-none">
        <p>Noscite e uno studio di consulenza che unisce competenze tecnologiche, strategiche e formative per accompagnare le piccole e medie imprese nel percorso di trasformazione digitale.</p>
        <p>Crediamo che l'innovazione debba essere accessibile, comprensibile e al servizio delle persone. Per questo abbiamo sviluppato un approccio unico — l'<strong>Umanesimo Digitale</strong> — che mette al centro la cultura organizzativa e le competenze umane, prima ancora degli strumenti tecnologici.</p>
        <p>Con sede a Milano, lavoriamo con PMI di tutta Italia, offrendo consulenza strategica, formazione e implementazione di soluzioni basate sull'intelligenza artificiale.</p>
    </div>
</section>

<section class="bg-primary-600 py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-3xl font-bold text-white">Vuoi saperne di piu?</h2>
        <p class="mt-4 text-primary-100 text-lg">Scopri il nostro profilo completo e la nostra storia.</p>
        <a href="{{ route('profilum') }}" class="inline-flex items-center mt-8 px-8 py-3.5 text-base font-semibold text-primary-700 bg-white rounded-lg hover:bg-primary-50 transition-colors">Profilum Societatis</a>
    </div>
</section>

@endsection
