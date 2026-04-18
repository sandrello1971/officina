@extends('layouts.noscite')
@section('title', 'Contactus — Parliamo del tuo progetto')
@section('description', 'Contatta Noscite per una valutazione concreta della tua PMI. Ti rispondiamo entro 24 ore con indicazioni pratiche su cosa ha senso fare per la tua realta aziendale.')

@push('meta')
    <x-seo title="Contactus" description="Contatta Noscite per una consulenza gratuita. Raccontaci il tuo progetto di trasformazione digitale." />
@endpush

@section('content')

<section class="py-20 bg-gradient-to-b from-primary-50/40 to-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
            <h1 class="text-4xl sm:text-5xl font-bold text-gray-900">Contactus</h1>
            <p class="mt-4 text-xl text-gray-600 max-w-2xl mx-auto">Raccontaci il tuo progetto. La prima consulenza e sempre gratuita.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-5 gap-12">
            {{-- Form --}}
            <div class="lg:col-span-3">
                <div class="p-4 rounded-lg mb-6" style="background:#E8F5F5;border-left:4px solid #55B1AE">
                    <p class="font-semibold mb-2" style="color:#1A1F1F">Cosa succede dopo l'invio</p>
                    <div class="flex flex-col gap-2 text-sm" style="color:#4A5252">
                        <div class="flex gap-2">
                            <span style="color:#55B1AE;font-weight:bold">1.</span>
                            <span>Ricevi una conferma immediata via email</span>
                        </div>
                        <div class="flex gap-2">
                            <span style="color:#55B1AE;font-weight:bold">2.</span>
                            <span>Entro 24 ore ti rispondiamo concretamente con una prima valutazione della tua situazione</span>
                        </div>
                        <div class="flex gap-2">
                            <span style="color:#55B1AE;font-weight:bold">3.</span>
                            <span>Se ha senso procedere, organizziamo una call per capire insieme il percorso piu adatto</span>
                        </div>
                    </div>
                    <p class="text-xs mt-3" style="color:#8A9696">I tuoi dati sono trattati nel rispetto del GDPR, usati solo per risponderti. Nessuno spam, mai.</p>
                </div>
                <livewire:contact-form />
            </div>

            {{-- Info contatto --}}
            <div class="lg:col-span-2 space-y-8">
                <div class="bg-gray-50 rounded-2xl p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Come raggiungerci</h3>
                    <ul class="space-y-4">
                        <li class="flex items-start gap-3">
                            <svg class="h-5 w-5 text-primary-600 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            <div>
                                <p class="text-sm font-medium text-gray-900">Email</p>
                                <a href="mailto:info@noscite.it" class="text-sm text-primary-600 hover:underline">info@noscite.it</a>
                            </div>
                        </li>
                        <li class="flex items-start gap-3">
                            <svg class="h-5 w-5 text-primary-600 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                            </svg>
                            <div>
                                <p class="text-sm font-medium text-gray-900">Telefono</p>
                                <a href="tel:+390200000000" class="text-sm text-primary-600 hover:underline">+39 02 000 000 00</a>
                            </div>
                        </li>
                        <li class="flex items-start gap-3">
                            <svg class="h-5 w-5 text-primary-600 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <div>
                                <p class="text-sm font-medium text-gray-900">Sede</p>
                                <p class="text-sm text-gray-600">Milano, Italia</p>
                            </div>
                        </li>
                    </ul>
                </div>

                <div class="bg-secondary-50 rounded-2xl p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Cosa aspettarti</h3>
                    <ul class="space-y-2 text-sm text-gray-600">
                        <li class="flex items-center gap-2">
                            <svg class="h-4 w-4 text-secondary-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Rispondiamo entro 24 ore lavorative
                        </li>
                        <li class="flex items-center gap-2">
                            <svg class="h-4 w-4 text-secondary-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Prima consulenza sempre gratuita
                        </li>
                        <li class="flex items-center gap-2">
                            <svg class="h-4 w-4 text-secondary-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Nessun impegno vincolante
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

@endsection
