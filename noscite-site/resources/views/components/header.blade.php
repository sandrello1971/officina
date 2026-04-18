<header class="fixed top-0 left-0 right-0 z-50 bg-white border-b border-border" x-data="{ mobileOpen: false }">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-[72px]">

            {{-- Logo + motto --}}
            <a href="{{ route('home') }}" class="flex-shrink-0 flex items-center gap-3">
                <img src="/images/logo.png" alt="Noscite" class="h-12 w-auto">
                <span class="hidden sm:block text-xs italic text-orange leading-tight">In digit&#x101;l&#x12B; nova virt&#x16B;s</span>
            </a>

            {{-- Nav desktop --}}
            <nav class="hidden lg:flex items-center space-x-5">
                @php
                    $navLinks = [
                        ['route' => 'profilum', 'latin' => 'Profilum', 'it' => 'Chi siamo'],
                        ['route' => 'fundamenta', 'latin' => 'Fundamenta', 'it' => 'Il manifesto'],
                        ['route' => 'methodus', 'latin' => 'Methodus', 'it' => 'Il metodo'],
                        ['route' => 'valor', 'latin' => 'Valor', 'it' => 'I valori'],
                        ['route' => 'atheneum', 'latin' => 'Atheneum', 'it' => 'Formazione'],
                        ['route' => 'commentarium.index', 'latin' => 'Commentarium', 'it' => 'Articoli', 'match' => 'commentarium.*'],
                    ];
                @endphp

                @foreach($navLinks as $link)
                    @php $isActive = request()->routeIs($link['match'] ?? $link['route']); @endphp
                    <a href="{{ route($link['route']) }}"
                       class="text-sm font-medium transition-colors leading-tight
                              {{ $isActive ? 'text-teal border-b-2 border-teal pb-0.5' : 'text-dark hover:text-teal' }}">
                        {{ $link['latin'] }} <span class="text-xs" style="color:#8A9696">{{ $link['it'] }}</span>
                    </a>
                @endforeach

                <a href="{{ route('contactus') }}"
                   class="inline-flex items-center px-5 py-2 text-sm font-semibold text-white bg-teal rounded-lg hover:bg-teal-dark transition-colors">
                    Contactus <span class="text-xs ml-1 opacity-80">Contatti</span>
                </a>
            </nav>

            {{-- Hamburger mobile --}}
            <button @click="mobileOpen = !mobileOpen"
                    class="lg:hidden inline-flex items-center justify-center p-2 rounded-md text-teal hover:bg-teal-light transition-colors"
                    aria-label="Menu">
                <svg x-show="!mobileOpen" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
                <svg x-show="mobileOpen" x-cloak class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </div>

    {{-- Nav mobile --}}
    <div x-show="mobileOpen" x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 -translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-1"
         class="lg:hidden bg-white border-t border-border">
        <nav class="px-4 py-3 space-y-1">
            <p class="px-3 py-1 text-xs italic text-orange">In digit&#x101;l&#x12B; nova virt&#x16B;s</p>

            @foreach($navLinks as $link)
                @php $isActive = request()->routeIs($link['match'] ?? $link['route']); @endphp
                <a href="{{ route($link['route']) }}"
                   class="block px-3 py-2 rounded-md text-base font-medium transition-colors
                          {{ $isActive ? 'text-teal bg-teal-light' : 'text-dark hover:bg-neutral' }}">
                    {{ $link['latin'] }} <span class="text-sm" style="color:#8A9696">· {{ $link['it'] }}</span>
                </a>
            @endforeach

            <a href="{{ route('contactus') }}"
               class="block px-3 py-2 mt-2 rounded-lg text-center text-base font-semibold text-white bg-teal hover:bg-teal-dark transition-colors">
                Contactus · Contatti
            </a>
        </nav>
    </div>
</header>

{{-- Spacer per compensare header fisso --}}
<div class="h-[72px]"></div>
