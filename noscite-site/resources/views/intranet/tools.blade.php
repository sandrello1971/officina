@extends('layouts.intranet')
@section('title', 'Strumenti')
@section('content')
<div style="max-width:960px;">
    <h1 style="font-size:1.25rem; font-weight:700; color:#1A1F1F; margin-bottom:24px;">🔧 Strumenti aziendali</h1>

    @foreach($tools as $section => $sectionTools)
    <div style="margin-bottom:32px;">
        <h2 style="font-size:0.8rem; font-weight:700; color:#8A9696; text-transform:uppercase; letter-spacing:0.1em; margin-bottom:12px;">
            {{ $sectionTools->first()['icon'] }} {{ $section }}
        </h2>
        <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:16px;">
            @foreach($sectionTools as $tool)
            <a href="{{ $tool['url'] }}" target="_blank" class="tool-card">
                <div style="font-size:2rem; margin-bottom:10px;">{{ $tool['icon'] }}</div>
                <div style="font-weight:700; color:#1A1F1F; margin-bottom:4px;">{{ $tool['name'] }}</div>
                <div style="font-size:0.8rem; color:#8A9696; line-height:1.5;">{{ $tool['description'] }}</div>
                <div style="margin-top:10px; font-size:0.75rem; color:#55B1AE; font-weight:600;">{{ $tool['label'] }} →</div>
            </a>
            @endforeach
        </div>
    </div>
    @endforeach
</div>
@endsection
