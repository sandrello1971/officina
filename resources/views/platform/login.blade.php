@extends('platform.layout')
@section('title', 'Accesso')
@section('content')
<div class="max-w-sm mx-auto bg-white rounded-xl shadow-sm p-6">
    <h1 class="text-lg font-bold mb-4">Accesso alla console</h1>
    <form method="POST" action="{{ route('platform.login.post') }}" class="space-y-3">
        @csrf
        <input name="email" type="email" value="{{ old('email') }}" placeholder="Email" required autofocus class="w-full border rounded px-3 py-2 text-sm">
        <input name="password" type="password" placeholder="Password" required class="w-full border rounded px-3 py-2 text-sm">
        <button class="w-full rounded px-3 py-2 text-sm font-semibold text-white" style="background:#3A8C89;">Continua</button>
    </form>
</div>
@endsection
