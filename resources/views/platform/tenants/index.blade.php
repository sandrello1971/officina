@extends('platform.layout')
@section('title', 'Enti')
@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-xl font-bold">Enti</h1>
    <a href="{{ route('platform.tenants.create') }}" class="rounded px-4 py-2 text-sm font-semibold text-white" style="background:#3A8C89;">+ Nuovo ente</a>
</div>
<div class="bg-white rounded-xl shadow-sm overflow-x-auto">
    <table class="w-full text-sm">
        <thead style="color:#8A9696;" class="text-left text-xs uppercase">
            <tr>
                <th class="px-4 py-3">Ente</th><th class="px-4 py-3">Host</th><th class="px-4 py-3">Stato</th>
                <th class="px-4 py-3">Chiave AI</th><th class="px-4 py-3 text-right">AI mese (piattaforma)</th><th class="px-4 py-3 text-right">Budget</th>
            </tr>
        </thead>
        <tbody>
        @forelse($rows as $r)
            @php $t = $r['tenant']; $over = $r['budget'] !== null && $r['platform_usd'] >= $r['budget']; @endphp
            <tr class="border-t">
                <td class="px-4 py-3">
                    <a href="{{ route('platform.tenants.edit', $t) }}" class="font-semibold" style="color:#3A8C89;">{{ $t->name }}</a>
                    @if($t->isPrimary())<span class="ml-1 text-xs" style="color:#E28A53;">primario</span>@endif
                </td>
                <td class="px-4 py-3 font-mono text-xs"><a href="https://{{ $t->learnHost() }}" target="_blank" rel="noopener">{{ $t->base_host }}</a></td>
                <td class="px-4 py-3">{{ $t->status === 'active' ? 'attivo' : 'sospeso' }}</td>
                <td class="px-4 py-3">{{ ['platform' => 'piattaforma', 'tenant' => 'ente', 'both' => 'ente → piattaforma'][$t->ai_key_mode] ?? $t->ai_key_mode }}</td>
                <td class="px-4 py-3 text-right font-mono {{ $over ? 'font-bold' : '' }}" style="{{ $over ? 'color:#B42318' : '' }}">
                    ${{ number_format($r['platform_usd'], 2) }}
                    @if($r['tenant_usd'] > 0)<div class="text-xs" style="color:#8A9696;">+ ${{ number_format($r['tenant_usd'], 2) }} chiave ente</div>@endif
                </td>
                <td class="px-4 py-3 text-right font-mono">{{ $r['budget'] !== null ? '$'.number_format($r['budget'], 2) : '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-6 text-center" style="color:#8A9696;">Nessun ente.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
