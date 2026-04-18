@props(['title', 'description', 'canonical' => ''])

<title>{{ $title }} — Noscite</title>
<meta name="description" content="{{ $description }}">

<meta property="og:title" content="{{ $title }} — Noscite">
<meta property="og:description" content="{{ $description }}">
<meta property="og:type" content="website">
@if($canonical)
<meta property="og:url" content="{{ $canonical }}">
<link rel="canonical" href="{{ $canonical }}">
@else
<meta property="og:url" content="{{ url()->current() }}">
<link rel="canonical" href="{{ url()->current() }}">
@endif
