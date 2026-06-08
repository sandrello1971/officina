@extends('layouts.scuola')
@section('title', 'Studenti')
@section('breadcrumb', 'Studenti')
@section('content')
<div style="max-width:1040px;">
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:18px;">
        <h1 style="font-size:1.4rem; font-weight:700; color:#1A1F1F; margin:0;">Studenti</h1>
        <a href="{{ route('scuola.studenti.import.create') }}" style="padding:9px 16px; background:#55B1AE; color:white; border-radius:8px; font-size:0.85rem; font-weight:600; text-decoration:none;">&#11014; Importa da CSV</a>
    </div>

    <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; overflow:hidden;">
        <table style="width:100%; border-collapse:collapse; font-size:0.85rem;">
            <thead><tr style="background:#F5F7F7; text-align:left; color:#4A5252;">
                <th style="padding:10px 14px;">Nome</th><th style="padding:10px 14px;">Email / Username</th>
                <th style="padding:10px 14px;">Classe</th><th style="padding:10px 14px;">Nascita</th><th style="padding:10px 14px;">Stato</th>
            </tr></thead>
            <tbody>
            @forelse($students as $s)
                <tr style="border-top:1px solid #F0F2F2;">
                    <td style="padding:10px 14px; color:#1A1F1F;">{{ $s->name }}</td>
                    <td style="padding:10px 14px; color:#4A5252;">{{ $s->email ?? $s->username }}</td>
                    <td style="padding:10px 14px; color:#4A5252;">{{ $s->classEnrollments->first()?->schoolClass?->name ?? '—' }}</td>
                    <td style="padding:10px 14px; color:#4A5252;">{{ $s->birth_date?->format('d/m/Y') ?? '—' }}</td>
                    <td style="padding:10px 14px;">
                        @if($s->must_change_password)<span style="font-size:0.72rem; color:#E28A53;">invito da completare</span>
                        @elseif($s->is_active)<span style="font-size:0.72rem; color:#3A8C89;">attivo</span>
                        @else<span style="font-size:0.72rem; color:#A8521F;">disattivato</span>@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" style="padding:18px 14px; color:#8A9696;">Nessuno studente. Importa un CSV per iniziare.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
