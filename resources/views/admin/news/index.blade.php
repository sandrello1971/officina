@extends('layouts.admin')
@section('title', 'News AI')
@section('content')

<div style="max-width:900px;">
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">
        <h2 style="font-size:1.25rem; font-weight:700; color:#1A1F1F;">News AI</h2>
        <form method="POST" action="{{ route('admin.news.fetch') }}">
            @csrf
            <button type="submit"
                    style="background:#55B1AE; color:white; border:none; border-radius:8px; padding:9px 16px; font-size:0.85rem; font-weight:600; cursor:pointer;">
                Recupera ora
            </button>
        </form>
    </div>

    <p style="font-size:0.82rem; color:#8A9696; margin-bottom:20px;">
        Le news vengono recuperate dalla ricerca online (settimanale se abilitata nelle Impostazioni, oppure «Recupera ora»).
        Restano <strong>bozze</strong> finché non le pubblichi: solo le pubblicate sono visibili ai discenti.
        @if($lastRun)
            <br>Ultimo recupero: {{ $lastRun->created_at?->format('d/m/Y H:i') }} — stato <strong>{{ $lastRun->status }}</strong>,
            {{ $lastRun->items_found }} news.
            @if($lastRun->failure_reason)<span style="color:#c0392b;"> ({{ $lastRun->failure_reason }})</span>@endif
        @endif
    </p>

    @if(session('success'))
        <div style="background:#E8F5F5; border:1px solid #55B1AE; border-radius:8px; padding:10px 14px; margin-bottom:16px; font-size:0.85rem; color:#1A1F1F;">{{ session('success') }}</div>
    @endif

    {{-- BOZZE DA RIVEDERE --}}
    <h3 style="font-size:1rem; font-weight:700; color:#1A1F1F; margin:18px 0 10px;">Da rivedere ({{ $drafts->count() }})</h3>
    @forelse($drafts as $item)
        @include('admin.news._item', ['item' => $item, 'draft' => true])
    @empty
        <p style="font-size:0.85rem; color:#8A9696; font-style:italic;">Nessuna bozza in attesa.</p>
    @endforelse

    {{-- PUBBLICATE --}}
    <h3 style="font-size:1rem; font-weight:700; color:#1A1F1F; margin:26px 0 10px;">Pubblicate</h3>
    @forelse($published as $item)
        @include('admin.news._item', ['item' => $item, 'draft' => false])
    @empty
        <p style="font-size:0.85rem; color:#8A9696; font-style:italic;">Ancora nessuna news pubblicata.</p>
    @endforelse
</div>
@endsection
