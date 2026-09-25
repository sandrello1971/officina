@extends('platform.layout')
@section('title', 'Verifica in due passaggi')
@section('content')
<div class="max-w-sm mx-auto bg-white rounded-xl shadow-sm p-6">
    @if($setup)
        <h1 class="text-lg font-bold mb-2">Configura il 2FA</h1>
        <p class="text-sm mb-4" style="color:#4A5252;">Obbligatorio per la console. Inquadra il QR con l'app di autenticazione, poi inserisci il codice.</p>
        <div class="flex justify-center mb-2">{!! $qrSvg !!}</div>
        <p class="text-xs text-center font-mono mb-4 select-all" style="color:#8A9696;">{{ $secret }}</p>
    @else
        <h1 class="text-lg font-bold mb-4">Codice di verifica</h1>
    @endif
    <form method="POST" action="{{ route('platform.2fa.verify') }}" class="space-y-3">
        @csrf
        <input name="code" inputmode="numeric" autocomplete="one-time-code" placeholder="123456" required autofocus class="w-full border rounded px-3 py-2 text-sm tracking-widest text-center">
        <button class="w-full rounded px-3 py-2 text-sm font-semibold text-white" style="background:#3A8C89;">Verifica</button>
    </form>
</div>
@endsection
