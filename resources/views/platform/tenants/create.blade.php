@extends('platform.layout')
@section('title', 'Nuovo ente')
@section('content')
<h1 class="text-xl font-bold mb-6">Nuovo ente</h1>
<form method="POST" action="{{ route('platform.tenants.store') }}" class="bg-white rounded-xl shadow-sm p-6"
      onsubmit="this.querySelector('button[type=submit]').disabled = true; this.querySelector('button[type=submit]').textContent = 'Creazione del database in corso…';">
    @csrf
    @include('platform.tenants._fields')
    <div class="grid gap-4 sm:grid-cols-2 mt-5">
        <label class="block text-sm">Email primo amministratore
            <input name="admin_email" type="email" value="{{ old('admin_email') }}" required class="mt-1 w-full border rounded px-3 py-2">
        </label>
        <label class="block text-sm">Nome primo amministratore
            <input name="admin_name" value="{{ old('admin_name') }}" class="mt-1 w-full border rounded px-3 py-2">
        </label>
    </div>
    <p class="text-xs mt-4" style="color:#8A9696;">Il database dell'ente viene clonato dal template. Dopo la creazione servono DNS e certificato TLS per i due host.</p>
    <button type="submit" class="mt-5 rounded px-4 py-2 text-sm font-semibold text-white" style="background:#3A8C89;">Crea ente</button>
</form>
@endsection
