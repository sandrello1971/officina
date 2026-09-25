@extends($layout)
@section('title', 'Edizioni — ' . $course->name)
@section('breadcrumb', 'Edizioni')
@section('content')
<div class="ed-wrap">
    @include('attendance.editions._style')
    <a href="{{ $nav->courseUrl() }}" class="ed-back">&larr; {{ $course->name }}</a>
    <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:16px;">
        <div>
            <h2 class="ed-h2">Edizioni e registro presenze</h2>
            <p class="ed-meta" style="margin:0;">Ogni edizione ha le sue giornate, i suoi discenti e il suo registro.</p>
        </div>
        <a href="{{ $nav->url('create') }}" class="ed-btn primary">+ Nuova edizione</a>
    </div>

    <div class="ed-card">
        @if($editions->isEmpty())
            <p style="color:#6B7280; text-align:center; padding:24px 0;">Nessuna edizione. Crea la prima: scegli le date e le giornate, poi associa i discenti.</p>
        @else
        <table class="ed-table">
            <thead><tr><th>Edizione</th><th>Date</th><th>Giornate</th><th>Discenti</th><th>Formatore</th><th></th></tr></thead>
            <tbody>
            @foreach($editions as $e)
                @php $first = $e->days->min('scheduled_at'); $last = $e->days->max('scheduled_at'); @endphp
                <tr>
                    <td><a href="{{ $nav->url('show', $e) }}" style="font-weight:600; color:#3A8C89; text-decoration:none;">{{ $e->name }}</a>
                        <div style="font-size:0.75rem; color:#8A9696;">{{ $e->modalityLabel() }}@if($e->location) · {{ $e->location }}@endif</div></td>
                    <td>{{ $first ? \Illuminate\Support\Carbon::parse($first)->format('d/m/Y') . ' – ' . \Illuminate\Support\Carbon::parse($last)->format('d/m/Y') : '—' }}</td>
                    <td>{{ $e->days_count }} · {{ \App\Support\AttendanceCell::hours($e->days->sum('duration_minutes') / 60) }}</td>
                    <td>{{ $e->students_count }}</td>
                    <td>{{ $e->instructor?->name ?? '—' }}</td>
                    <td style="text-align:right;"><a href="{{ $nav->url('show', $e) }}" class="ed-btn">Apri</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>
@endsection
