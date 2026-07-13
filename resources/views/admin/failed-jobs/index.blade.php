@extends('layouts.admin')
@section('title', 'Job falliti')
@section('content')
<div style="max-width:1100px;">
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; flex-wrap:wrap; gap:10px;">
        <div>
            <h2 style="font-size:1.25rem; font-weight:700; color:#1A1F1F;">Job falliti</h2>
            <p style="font-size:0.8rem; color:#8A9696;">Processi asincroni (generazioni AI, ingestion, video) che hanno fallito.</p>
        </div>
        @if($jobs->isNotEmpty())
        <div style="display:flex; gap:8px;">
            <form method="POST" action="{{ route('admin.failed-jobs.retry-all') }}">@csrf
                <button style="padding:8px 16px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.8rem; font-weight:700; cursor:pointer;">↻ Riprova tutti</button>
            </form>
            <form method="POST" action="{{ route('admin.failed-jobs.flush') }}" onsubmit="return confirm('Eliminare TUTTI i job falliti? Irreversibile.');">@csrf
                <button style="padding:8px 16px; background:white; color:#C52A2A; border:1px solid #E28A8A; border-radius:8px; font-size:0.8rem; font-weight:700; cursor:pointer;">Svuota</button>
            </form>
        </div>
        @endif
    </div>

    @if(session('success'))
    <div style="padding:10px 14px; background:rgba(85,177,174,0.12); border:1px solid rgba(85,177,174,0.4); border-radius:8px; color:#3A8C89; margin-bottom:14px; font-size:0.85rem;">✅ {{ session('success') }}</div>
    @endif

    <div style="background:white; border-radius:10px; overflow:hidden; border:1px solid #E8F5F5;">
        <table style="width:100%; border-collapse:collapse; font-size:0.8rem;">
            <thead>
                <tr style="background:#F5F7F7; text-align:left; color:#5A6464;">
                    <th style="padding:10px;">Quando</th>
                    <th style="padding:10px;">Job</th>
                    <th style="padding:10px;">Coda</th>
                    <th style="padding:10px;">Errore</th>
                    <th style="padding:10px; width:150px;">Azioni</th>
                </tr>
            </thead>
            <tbody>
                @forelse($jobs as $job)
                <tr style="border-top:1px solid #F0F5F5;">
                    <td style="padding:10px; color:#8A9696; white-space:nowrap;">{{ \Illuminate\Support\Carbon::parse($job->failed_at)->format('d/m H:i') }}</td>
                    <td style="padding:10px; color:#1A1F1F; font-weight:600;">{{ class_basename($job->name) }}</td>
                    <td style="padding:10px; color:#5A6464;">{{ $job->queue }}</td>
                    <td style="padding:10px; color:#C52A2A;">{{ Str::limit($job->exception, 90) }}</td>
                    <td style="padding:10px;">
                        <div style="display:flex; gap:6px;">
                            <form method="POST" action="{{ route('admin.failed-jobs.retry', $job->uuid) }}">@csrf
                                <button style="padding:5px 10px; background:#55B1AE; color:white; border:none; border-radius:6px; font-size:0.72rem; font-weight:700; cursor:pointer;">↻ Riprova</button>
                            </form>
                            <form method="POST" action="{{ route('admin.failed-jobs.forget', $job->uuid) }}">@csrf
                                <button style="padding:5px 10px; background:white; color:#8A9696; border:1px solid #C8D0D0; border-radius:6px; font-size:0.72rem; cursor:pointer;">Rimuovi</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" style="padding:24px; text-align:center; color:#8A9696;">Nessun job fallito. 🎉</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
