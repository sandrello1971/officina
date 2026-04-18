<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="@yield('description', 'Noscite accompagna le PMI italiane nella trasformazione digitale con AI: formazione certificata, consulenza strategica e governance dei dati. Conformi EU AI Act.')">
    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="apple-touch-icon" href="/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://cdn.tailwindcss.com">
    <link rel="preload" as="image" href="/images/logo.png">
    @stack('meta')

    <title>@yield('title', 'Noscite') — Noscite</title>

    <!-- Schema.org -->
    @verbatim
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Organization",
      "name": "Noscite",
      "legalName": "Noscite S.r.l.s.",
      "url": "https://noscite.it",
      "logo": "https://noscite.it/images/logo.png",
      "description": "Startup innovativa che accompagna le PMI italiane nella trasformazione digitale consapevole, integrando l'intelligenza artificiale come leva di crescita.",
      "foundingDate": "2025",
      "foundingLocation": "Corsico, Milano, Italia",
      "address": {
        "@type": "PostalAddress",
        "addressLocality": "Corsico",
        "addressRegion": "MI",
        "addressCountry": "IT"
      },
      "contactPoint": {
        "@type": "ContactPoint",
        "telephone": "+39-347-685-9801",
        "email": "info@noscite.it",
        "contactType": "customer service",
        "availableLanguage": "Italian"
      },
      "sameAs": [
        "https://www.facebook.com/Noscite",
        "https://www.linkedin.com/company/noscite",
        "https://www.instagram.com/noscite"
      ]
    }
    </script>
    @endverbatim

    <!-- Tailwind CSS (dev CDN) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        teal: { DEFAULT: '#55B1AE', dark: '#3A8C89', light: '#E8F5F5' },
                        orange: { DEFAULT: '#E28A53' },
                        dark: '#1A1F1F',
                        mid: '#4A5252',
                        muted: '#8A9696',
                        neutral: '#F5F7F7',
                        border: '#C8D0D0',
                    },
                    fontFamily: { sans: ['Calibri', 'system-ui', 'sans-serif'] },
                    fontSize: {
                        'display': ['48px', { lineHeight: '1.2', fontWeight: '300' }],
                        'h1':      ['32px', { lineHeight: '1.3', fontWeight: '400' }],
                        'h2':      ['22px', { lineHeight: '1.4', fontWeight: '700' }],
                        'body':    ['18px', { lineHeight: '1.6', fontWeight: '400' }],
                        'label':   ['14px', { lineHeight: '1', fontWeight: '700' }],
                    },
                },
            },
        }
    </script>

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    @livewireStyles

    <style>
        :root {
            --color-teal: #55B1AE;
            --color-orange: #E28A53;
            --color-teal-dark: #3A8C89;
            --color-teal-light: #E8F5F5;
            --color-dark: #1A1F1F;
            --color-mid: #4A5252;
            --color-muted: #8A9696;
            --color-neutral: #F5F7F7;
            --color-border: #C8D0D0;
        }
        [x-cloak] { display: none !important; }
        body { font-family: 'Calibri', system-ui, sans-serif; }
        img.decorative { image-rendering: optimizeSpeed; }
    </style>

    @stack('styles')
</head>
<body class="font-sans antialiased bg-white text-dark text-body">

    @include('components.header')

    <main class="min-h-screen">
        @yield('content')
    </main>

    @include('components.footer')

    @livewire('cookie-banner')

    @livewireScripts
    @stack('scripts')
</body>
</html>
