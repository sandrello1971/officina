<footer class="bg-dark text-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">

            {{-- Col 1: Brand --}}
            <div>
                <a href="{{ route('home') }}" class="inline-block mb-4">
                    <img src="/images/logo.png" alt="Noscite" class="h-10 w-auto brightness-0 invert">
                </a>
                <p class="text-muted text-sm leading-relaxed">
                    Umanesimo digitale, risultati concreti.
                </p>
                <p class="mt-2 text-sm italic text-orange">
                    In digit&#x101;l&#x12B; nova virt&#x16B;s
                </p>
            </div>

            {{-- Col 2: Navigazione --}}
            <div>
                <h3 class="text-label font-bold uppercase tracking-wider text-teal mb-4">Navigazione</h3>
                <ul class="space-y-2">
                    <li><a href="{{ route('profilum') }}" class="text-muted hover:text-teal text-sm transition-colors">Profilum Societatis</a></li>
                    <li><a href="{{ route('fundamenta') }}" class="text-muted hover:text-teal text-sm transition-colors">Fundamenta</a></li>
                    <li><a href="{{ route('methodus') }}" class="text-muted hover:text-teal text-sm transition-colors">Methodus</a></li>
                    <li><a href="{{ route('valor') }}" class="text-muted hover:text-teal text-sm transition-colors">Valor</a></li>
                    <li><a href="{{ route('atheneum') }}" class="text-muted hover:text-teal text-sm transition-colors">Atheneum</a></li>
                    <li><a href="{{ route('commentarium.index') }}" class="text-muted hover:text-teal text-sm transition-colors">Commentarium</a></li>
                    <li><a href="{{ route('contactus') }}" class="text-muted hover:text-teal text-sm transition-colors">Contactus</a></li>
                </ul>
            </div>

            {{-- Col 3: Contatti --}}
            <div>
                <h3 class="text-label font-bold uppercase tracking-wider text-teal mb-4">Contatti</h3>
                <ul class="space-y-2 text-sm text-muted">
                    <li class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-teal" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                        <a href="mailto:info@noscite.it" class="hover:text-teal transition-colors">info@noscite.it</a>
                    </li>
                    <li class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-teal" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <span>Corsico (MI), Italia</span>
                    </li>
                </ul>

                <div class="mt-4">
                    <div class="text-xs font-bold uppercase mb-2 text-teal">Seguici</div>
                    <div class="flex gap-3">
                        <a href="https://www.facebook.com/Noscite" target="_blank" rel="noopener noreferrer" class="w-8 h-8 rounded flex items-center justify-center transition-colors" style="background:#4A5252" onmouseover="this.style.background='#55B1AE'" onmouseout="this.style.background='#4A5252'" aria-label="Facebook Noscite">
                            <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                        </a>
                        <a href="https://www.linkedin.com/company/noscite" target="_blank" rel="noopener noreferrer" class="w-8 h-8 rounded flex items-center justify-center transition-colors" style="background:#4A5252" onmouseover="this.style.background='#55B1AE'" onmouseout="this.style.background='#4A5252'" aria-label="LinkedIn Noscite">
                            <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                        </a>
                        <a href="https://www.instagram.com/noscite" target="_blank" rel="noopener noreferrer" class="w-8 h-8 rounded flex items-center justify-center transition-colors" style="background:#4A5252" onmouseover="this.style.background='#55B1AE'" onmouseout="this.style.background='#4A5252'" aria-label="Instagram Noscite">
                            <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Bottom bar --}}
        <div class="mt-10 pt-6 border-t border-mid/30 flex flex-col sm:flex-row items-center justify-between gap-4">
            <p class="text-muted text-xs">
                &copy; 2025 Noscite S.r.l.s. — Corsico (MI)
            </p>
            <div class="flex items-center gap-4">
                <a href="{{ route('privacy') }}" class="text-muted hover:text-teal text-xs transition-colors">Privacy Policy</a>
                <a href="{{ route('cookies') }}" class="text-muted hover:text-teal text-xs transition-colors">Cookie Policy</a>
            </div>
        </div>
    </div>
</footer>
