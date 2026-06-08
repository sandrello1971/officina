@extends('layouts.scuola')
@section('title', 'Anagrafica & branding')
@section('breadcrumb', 'Anagrafica & branding')
@section('content')
@php $hasLogo = (bool) $school->setting('logo_path'); @endphp
<div style="max-width:680px;">
    <h1 style="font-size:1.4rem; font-weight:700; color:#1A1F1F; margin-bottom:16px;">Anagrafica & branding</h1>

    @if($errors->any())<div style="margin-bottom:14px; padding:10px 14px; background:#FDECE2; border-left:4px solid #E28A53; border-radius:6px; color:#A8521F; font-size:0.85rem;">{{ $errors->first() }}</div>@endif

    <form method="POST" action="{{ route('scuola.anagrafica.update') }}" enctype="multipart/form-data" data-async
          style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:20px;">
        @csrf @method('PATCH')

        <div style="font-size:0.75rem; font-weight:700; color:#4A5252; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:12px;">Dati scuola</div>
        <label style="display:block; font-size:0.82rem; font-weight:600; color:#4A5252; margin-bottom:4px;">Nome *</label>
        <input type="text" name="name" value="{{ old('name', $school->name) }}" required style="width:100%; padding:9px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.9rem; margin-bottom:14px;">

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px;">
            <div>
                <label style="display:block; font-size:0.82rem; font-weight:600; color:#4A5252; margin-bottom:4px;">Tipo *</label>
                <select name="type" style="width:100%; padding:9px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.9rem;">
                    @foreach(['liceo'=>'Liceo','istituto_tecnico'=>'Istituto tecnico','altro'=>'Altro'] as $k=>$lab)
                        <option value="{{ $k }}" @selected(old('type', $school->type)===$k)>{{ $lab }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="display:block; font-size:0.82rem; font-weight:600; color:#4A5252; margin-bottom:4px;">Città</label>
                <input type="text" name="city" value="{{ old('city', $school->city) }}" style="width:100%; padding:9px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.9rem;">
            </div>
        </div>

        <div style="font-size:0.75rem; font-weight:700; color:#4A5252; text-transform:uppercase; letter-spacing:0.05em; margin:20px 0 12px;">Branding (white-label)</div>
        <p style="font-size:0.78rem; color:#8A9696; margin-bottom:12px;">Lascia vuoto per ereditare il default della piattaforma.</p>

        <label style="display:block; font-size:0.82rem; font-weight:600; color:#4A5252; margin-bottom:4px;">Nome istanza (mostrato nel layout)</label>
        <input type="text" name="instance_name" value="{{ old('instance_name', $school->setting('instance_name')) }}" placeholder="es. Liceo Galilei — Atheneum" style="width:100%; padding:9px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.9rem; margin-bottom:14px;">

        <label style="display:block; font-size:0.82rem; font-weight:600; color:#4A5252; margin-bottom:4px;">Nome assistente (Minerva)</label>
        <input type="text" name="assistant_name" value="{{ old('assistant_name', $school->setting('assistant_name')) }}" placeholder="es. Minerva" style="width:100%; padding:9px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.9rem; margin-bottom:14px;">

        <label style="display:block; font-size:0.82rem; font-weight:600; color:#4A5252; margin-bottom:4px;">Logo</label>
        <div style="display:flex; align-items:center; gap:14px; margin-bottom:6px;">
            @if($hasLogo)
                <img src="{{ route('scuola.logo', $school) }}" alt="logo" style="height:40px; background:#1A1F1F; padding:6px; border-radius:6px;">
                <label style="font-size:0.8rem; color:#A8521F;"><input type="checkbox" name="remove_logo" value="1"> rimuovi logo</label>
            @else
                <span style="font-size:0.8rem; color:#8A9696;">Nessun logo: usato quello piattaforma.</span>
            @endif
        </div>
        <input type="file" name="logo" accept="image/png,image/jpeg,image/svg+xml,image/webp" style="font-size:0.85rem; margin-bottom:18px;">

        <div>
            <button data-busy-label="Salvo…" style="padding:10px 18px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.9rem; font-weight:600; cursor:pointer;">Salva</button>
        </div>
    </form>
</div>
@endsection
