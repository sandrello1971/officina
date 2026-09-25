@extends('platform.layout')
@section('title', $tenant->name)
@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-xl font-bold">{{ $tenant->name }} @if($tenant->isPrimary())<span class="text-sm" style="color:#E28A53;">· primario</span>@endif</h1>
    <div class="text-xs font-mono space-x-3">
        <a href="https://{{ $tenant->adminHost() }}" target="_blank" rel="noopener" style="color:#3A8C89;">{{ $tenant->adminHost() }}</a>
        <a href="https://{{ $tenant->learnHost() }}" target="_blank" rel="noopener" style="color:#3A8C89;">{{ $tenant->learnHost() }}</a>
    </div>
</div>

<form method="POST" action="{{ route('platform.tenants.update', $tenant) }}" class="bg-white rounded-xl shadow-sm p-6">
    @csrf @method('PUT')
    @include('platform.tenants._fields')
    <label class="block text-sm mt-5 sm:w-1/2">Stato
        <select name="status" class="mt-1 w-full border rounded px-3 py-2" @disabled($tenant->isPrimary())>
            <option value="active" @selected($tenant->status === 'active')>Attivo</option>
            <option value="suspended" @selected($tenant->status === 'suspended')>Sospeso (accesso bloccato, dati intatti)</option>
        </select>
        @if($tenant->isPrimary())<input type="hidden" name="status" value="active">@endif
    </label>
    <p class="text-xs mt-4 font-mono" style="color:#8A9696;">DB: {{ $tenant->database()->getName() }}</p>
    <button class="mt-5 rounded px-4 py-2 text-sm font-semibold text-white" style="background:#3A8C89;">Salva</button>
</form>

<div class="bg-white rounded-xl shadow-sm p-6 mt-6">
    <h2 class="font-semibold mb-3">Amministratori dell'ente</h2>
    <table class="w-full text-sm">
        @foreach($admins as $a)
        <tr class="border-t">
            <td class="py-2">{{ $a->name }}</td>
            <td class="py-2 font-mono text-xs">{{ $a->email }}</td>
            <td class="py-2">{{ $a->is_active ? 'attivo' : 'disattivo' }}</td>
            <td class="py-2 text-right">
                <form method="POST" action="{{ route('platform.tenants.admin-password', [$tenant, $a->id]) }}" onsubmit="return confirm('Generare una nuova password per {{ $a->email }}?')">
                    @csrf
                    <button class="text-xs font-semibold" style="color:#E28A53;">Nuova password</button>
                </form>
            </td>
        </tr>
        @endforeach
    </table>
</div>

@unless($tenant->isPrimary())
<form method="POST" action="{{ route('platform.tenants.destroy', $tenant) }}" class="bg-white rounded-xl shadow-sm p-6 mt-6" style="border:1px solid #F4C7C3;">
    @csrf @method('DELETE')
    <h2 class="font-semibold mb-2" style="color:#B42318;">Elimina ente</h2>
    <p class="text-sm mb-3" style="color:#4A5252;">Cancella il database dell'ente con tutti i corsi, i discenti e i documenti. Non si può annullare.</p>
    <label class="block text-sm">Scrivi <span class="font-mono">{{ $tenant->slug }}</span> per confermare
        <input name="confirm_slug" autocomplete="off" class="mt-1 border rounded px-3 py-2 font-mono">
    </label>
    <button class="mt-3 rounded px-4 py-2 text-sm font-semibold text-white" style="background:#B42318;">Elimina definitivamente</button>
</form>
@endunless
@endsection
