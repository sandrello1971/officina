@extends('layouts.noscite')
@section('title', 'Profilum Societatis — Il profilo di Noscite')
@section('description', 'Noscite e una startup innovativa che accompagna le PMI italiane nella trasformazione digitale consapevole. Scopri chi siamo, la nostra visione e perche l\'umanesimo digitale e al centro di ogni progetto.')

@push('meta')
    <x-seo title="Profilum Societatis" description="Scopri la storia, la missione e il team di Noscite. Studio di consulenza per l'umanesimo digitale delle PMI." />
@endpush

@section('content')

{{-- Hero --}}
<section class="py-20 bg-gradient-to-b from-primary-50/40 to-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="text-4xl sm:text-5xl font-bold text-gray-900">Profilum Societatis</h1>
        <p class="mt-4 text-xl text-gray-600 max-w-2xl mx-auto">Il profilo di Noscite</p>
    </div>
</section>

{{-- Storia --}}
<section class="py-16 bg-white">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-3xl font-bold text-gray-900 mb-6">La nostra storia</h2>
        <div class="prose prose-lg prose-gray max-w-none">
            <p>Noscite nasce dall'intuizione che la trasformazione digitale non sia una questione puramente tecnologica, ma un percorso profondamente umano. Fondata a Milano, la societa si propone come guida per le piccole e medie imprese italiane che vogliono affrontare il cambiamento con consapevolezza, metodo e visione strategica.</p>
            <p>Il nome stesso — dal latino <em>"conosci te stesso"</em> — riflette la convinzione che ogni trasformazione autentica parta dalla comprensione della propria identita, dei propri punti di forza e delle proprie aspirazioni.</p>
            <p>In un mercato dove la tecnologia evolve piu rapidamente della capacita delle organizzazioni di assorbirla, Noscite offre un ponte tra innovazione e cultura d'impresa, tra strumenti digitali e persone che li utilizzano.</p>
        </div>
    </div>
</section>

{{-- Missione e Visione --}}
<section class="py-16 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-12">
            <div class="bg-white rounded-2xl p-8 shadow-sm">
                <div class="w-12 h-12 bg-primary-100 text-primary-600 rounded-xl flex items-center justify-center mb-5">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
                <h3 class="text-2xl font-bold text-gray-900 mb-4">Missione</h3>
                <p class="text-gray-600 leading-relaxed">Accompagnare le PMI italiane nella trasformazione digitale con un approccio umanistico, rendendo la tecnologia accessibile, comprensibile e funzionale alla crescita sostenibile dell'impresa e delle sue persone.</p>
            </div>
            <div class="bg-white rounded-2xl p-8 shadow-sm">
                <div class="w-12 h-12 bg-secondary-100 text-secondary-600 rounded-xl flex items-center justify-center mb-5">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                </div>
                <h3 class="text-2xl font-bold text-gray-900 mb-4">Visione</h3>
                <p class="text-gray-600 leading-relaxed">Un tessuto imprenditoriale italiano in cui ogni PMI sia protagonista consapevole della propria evoluzione digitale, dove la tecnologia amplifica il talento umano senza mai sostituirlo.</p>
            </div>
        </div>
    </div>
</section>

{{-- Perche Noscite --}}
<section class="py-16 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <h2 class="text-3xl font-bold text-gray-900">Perche scegliere Noscite</h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="bg-white rounded-2xl p-6 border-l-4" style="border-color:#55B1AE; box-shadow: 0 1px 8px rgba(0,0,0,0.06);">
                <div class="w-10 h-10 rounded-lg flex items-center justify-center mb-4" style="background:#E8F5F5">
                    <svg class="h-5 w-5" style="color:#55B1AE" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                </div>
                <h3 class="text-lg font-bold mb-2" style="color:#1A1F1F">Approccio incrementale</h3>
                <p class="text-sm leading-relaxed" style="color:#4A5252">Non calchiamo soluzioni enterprise su realta che non le reggono. Partiamo da quello che hai, costruiamo passo dopo passo.</p>
            </div>
            <div class="bg-white rounded-2xl p-6 border-l-4" style="border-color:#55B1AE; box-shadow: 0 1px 8px rgba(0,0,0,0.06);">
                <div class="w-10 h-10 rounded-lg flex items-center justify-center mb-4" style="background:#E8F5F5">
                    <svg class="h-5 w-5" style="color:#55B1AE" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <h3 class="text-lg font-bold mb-2" style="color:#1A1F1F">Persone prima degli strumenti</h3>
                <p class="text-sm leading-relaxed" style="color:#4A5252">Nessuna automazione viene attivata senza che il team la comprenda e la governi. La dipendenza dagli strumenti non e un successo.</p>
            </div>
            <div class="bg-white rounded-2xl p-6 border-l-4" style="border-color:#55B1AE; box-shadow: 0 1px 8px rgba(0,0,0,0.06);">
                <div class="w-10 h-10 rounded-lg flex items-center justify-center mb-4" style="background:#E8F5F5">
                    <svg class="h-5 w-5" style="color:#55B1AE" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </div>
                <h3 class="text-lg font-bold mb-2" style="color:#1A1F1F">Governance e compliance by design</h3>
                <p class="text-sm leading-relaxed" style="color:#4A5252">EU AI Act, GDPR, responsabilita dei dati: non sono ostacoli, sono il modo in cui costruiamo fiducia nel tempo.</p>
            </div>
        </div>
    </div>
</section>

{{-- CTA --}}
<section class="bg-primary-600 py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-3xl font-bold text-white">Vuoi conoscerci meglio?</h2>
        <p class="mt-4 text-primary-100 text-lg">Parliamo del tuo progetto e di come possiamo aiutarti.</p>
        <a href="{{ route('contactus') }}" class="inline-flex items-center mt-8 px-8 py-3.5 text-base font-semibold text-primary-700 bg-white rounded-lg hover:bg-primary-50 transition-colors">Contattaci</a>
    </div>
</section>

@endsection
