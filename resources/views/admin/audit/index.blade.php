@extends('layouts.admin')
@section('title', 'Audit trail')
@section('content')
<div style="max-width:1200px;">
    <h2 style="font-size:1.25rem; font-weight:700; color:#1A1F1F; margin-bottom:6px;">Audit trail</h2>
    <p style="font-size:0.8rem; color:#8A9696; margin-bottom:16px;">Azioni mutanti (non-GET) nelle aree admin e docente: chi, cosa, quando.</p>

    <form method="GET" style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:16px; align-items:end;">
        <div>
            <label style="font-size:0.7rem; color:#8A9696; display:block;">Cerca (attore / azione / path)</label>
            <input type="text" name="q" value="{{ $q }}" placeholder="es. audit@ente.it, quizzes…"
                   style="padding:8px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.85rem; min-width:260px;">
        </div>
        <div>
            <label style="font-size:0.7rem; color:#8A9696; display:block;">Area</label>
            <select name="area" style="padding:8px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.85rem;">
                <option value="">Tutte</option>
                <option value="admin" {{ $area === 'admin' ? 'selected' : '' }}>admin</option>
                <option value="docente" {{ $area === 'docente' ? 'selected' : '' }}>docente</option>
            </select>
        </div>
        <div>
            <label style="font-size:0.7rem; color:#8A9696; display:block;">Giorni</label>
            <input type="number" name="days" value="{{ $days }}" min="1" max="365"
                   style="width:80px; padding:8px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.85rem;">
        </div>
        <button type="submit" style="padding:8px 20px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.85rem; font-weight:700; cursor:pointer;">Filtra</button>
    </form>

    <div style="background:white; border-radius:10px; overflow:hidden; border:1px solid #E8F5F5;">
        <table style="width:100%; border-collapse:collapse; font-size:0.8rem;">
            <thead>
                <tr style="background:#F5F7F7; text-align:left; color:#5A6464;">
                    <th style="padding:10px;">Quando</th>
                    <th style="padding:10px;">Attore</th>
                    <th style="padding:10px;">Area</th>
                    <th style="padding:10px;">Azione</th>
                    <th style="padding:10px;">Soggetto</th>
                    <th style="padding:10px; width:60px;">Stato</th>
                    <th style="padding:10px;">IP</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                <tr style="border-top:1px solid #F0F5F5;">
                    <td style="padding:10px; color:#8A9696; white-space:nowrap;">{{ $log->created_at?->format('d/m H:i:s') }}</td>
                    <td style="padding:10px;">
                        <div style="font-weight:600; color:#1A1F1F;">{{ $log->actor_label ?? '—' }}</div>
                        <div style="font-size:0.7rem; color:#8A9696;">{{ $log->actor_type }}</div>
                    </td>
                    <td style="padding:10px;">
                        <span style="padding:2px 8px; background:rgba(85,177,174,0.15); color:#3A8C89; border-radius:8px; font-size:0.7rem; font-weight:700;">{{ $log->area }}</span>
                    </td>
                    <td style="padding:10px;">
                        <div style="color:#1A1F1F;">{{ $log->action }}</div>
                        <div style="font-size:0.7rem; color:#8A9696;">{{ $log->method }} {{ $log->path }}</div>
                    </td>
                    <td style="padding:10px; color:#5A6464;">
                        @if($log->subject_type){{ $log->subject_type }}: <span style="font-size:0.7rem;">{{ Str::limit($log->subject_id, 12) }}</span>@else—@endif
                    </td>
                    <td style="padding:10px;">
                        @php $ok = $log->status >= 200 && $log->status < 400; @endphp
                        <span style="color:{{ $ok ? '#3A8C89' : '#C52A2A' }}; font-weight:700;">{{ $log->status }}</span>
                    </td>
                    <td style="padding:10px; color:#8A9696; font-size:0.72rem;">{{ $log->ip }}</td>
                </tr>
                @empty
                <tr><td colspan="7" style="padding:24px; text-align:center; color:#8A9696;">Nessuna azione registrata nel periodo.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:16px;">{{ $logs->links() }}</div>
</div>
@endsection
