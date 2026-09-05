@extends('layouts.admin')
@section('title', 'Completezza — ' . $course->name)
@section('content')

<div style="display:flex; align-items:center; gap:10px; margin-bottom:6px;">
    <a href="{{ route('admin.completeness.index') }}" style="color:#8A9696; text-decoration:none; font-size:0.85rem;">&larr; Completezza</a>
    <h1 style="font-size:1.3rem; color:#1A1F1F; margin:0;">&#9989; {{ $course->name }}</h1>
</div>

@if (session('success'))
    <div data-flash style="display:flex; gap:10px; background:rgba(85,177,174,0.12); border:1px solid #55B1AE; color:#1A1F1F; padding:10px 14px; border-radius:8px; margin:12px 0; font-size:0.85rem;">
        <span style="flex:1;">{{ session('success') }}</span>
        <button type="button" data-dismiss-flash style="background:none; border:none; color:#3A8C89; cursor:pointer; font-size:1rem;">&times;</button>
    </div>
@endif

<form method="POST" action="{{ route('admin.completeness.run', $course) }}" style="margin:14px 0;">
    @csrf
    <button type="submit" style="padding:9px 18px; background:#E28A53; color:white; border:none; border-radius:6px; font-size:0.82rem; font-weight:700; cursor:pointer;">&#128260; Verifica ora</button>
</form>

@if ($findings->isEmpty())
    <div style="background:white; border-radius:10px; padding:20px; border:1px solid #E6EBEB; color:#3A8C89; font-weight:600;">
        ✓ Nessuna segnalazione aperta.
    </div>
@else
<div style="background:white; border-radius:10px; border:1px solid #E6EBEB; overflow:hidden;">
    <table style="width:100%; border-collapse:collapse; font-size:0.85rem;">
        <thead>
            <tr style="background:#F5F7F7; color:#5A6666; text-align:left;">
                <th style="padding:10px 14px;">Modulo</th>
                <th style="padding:10px 14px;">Tipo</th>
                <th style="padding:10px 14px;">Messaggio</th>
                <th style="padding:10px 14px;"></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($findings as $f)
            <tr style="border-top:1px solid #F0F4F4;">
                <td style="padding:10px 14px; color:#5A6666;">{{ $f->module->title ?? '—' }}</td>
                <td style="padding:10px 14px;">
                    <span style="padding:2px 8px; border-radius:10px; font-size:0.72rem; font-weight:700;
                        background:{{ $f->severity === 'warning' ? 'rgba(226,138,83,0.15)' : 'rgba(90,102,102,0.12)' }};
                        color:{{ $f->severity === 'warning' ? '#C26A2E' : '#5A6666' }};">{{ $f->check_type }}</span>
                </td>
                <td style="padding:10px 14px; color:#1A1F1F;">{{ $f->message }}</td>
                <td style="padding:10px 14px; text-align:right;">
                    <form method="POST" action="{{ route('admin.completeness.dismiss', $f) }}" style="margin:0;">
                        @csrf @method('PATCH')
                        <button type="submit" style="padding:5px 10px; background:white; color:#8A9696; border:1px solid #E6EBEB; border-radius:6px; font-size:0.74rem; cursor:pointer;">Ignora</button>
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

<script>
document.querySelectorAll('[data-dismiss-flash]').forEach(function (b) { b.addEventListener('click', function () { b.closest('[data-flash]').remove(); }); });
</script>
@endsection
