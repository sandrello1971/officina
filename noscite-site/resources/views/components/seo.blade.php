@props([
    'title',
    'description',
    'canonical' => '',
    'type' => 'website',
    'image' => '/images/classicismo.png',
    'imageWidth' => '1536',
    'imageHeight' => '1024',
    'imageAlt' => 'Noscite — Umanesimo digitale per le PMI',
])

@php
    $resolvedUrl = $canonical ?: url()->current();
    $resolvedImage = url($image);
    $fullTitle = $title . ' — Noscite';
@endphp

<title>{{ $fullTitle }}</title>
<meta name="description" content="{{ $description }}">
<link rel="canonical" href="{{ $resolvedUrl }}">

{{-- Open Graph (Facebook, LinkedIn, WhatsApp, Telegram) --}}
<meta property="og:type" content="{{ $type }}">
<meta property="og:site_name" content="Noscite">
<meta property="og:locale" content="it_IT">
<meta property="og:url" content="{{ $resolvedUrl }}">
<meta property="og:title" content="{{ $fullTitle }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:image" content="{{ $resolvedImage }}">
<meta property="og:image:width" content="{{ $imageWidth }}">
<meta property="og:image:height" content="{{ $imageHeight }}">
<meta property="og:image:alt" content="{{ $imageAlt }}">

{{-- Twitter Card (LinkedIn legge anche questi come fallback) --}}
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $fullTitle }}">
<meta name="twitter:description" content="{{ $description }}">
<meta name="twitter:image" content="{{ $resolvedImage }}">

{{-- TODO: sostituire /images/classicismo.png con OG image dedicata 1200x630 (ratio 1.91:1).
     Quando disponibile, aggiornare anche imageWidth/imageHeight default. --}}
