@extends('layouts.student')
@section('title', $material->title . ' — Schede dei discenti')
@section('breadcrumb', 'Schede dei discenti')
@section('content')
<div style="max-width:960px; margin:0 auto;">
    <a href="{{ route('student.course.canvas-review.index', $course->slug) }}" style="font-size:0.8rem; color:#55B1AE; text-decoration:none;">&larr; Schede dei discenti</a>
    <h2 style="font-size:1.25rem; font-weight:700; color:#1A1F1F; margin-top:4px;">{{ $material->title }}</h2>
    <p style="color:#6B7280; font-size:0.85rem; margin:6px 0 18px;">
        {{ $submissions->count() }} schede compilate su {{ $enrolledCount }} iscritti attivi.
    </p>

    @forelse($submissions as $i => $sub)
    <details @if($submissions->count() === 1) open @endif style="background:white; border:1px solid #E5E7EB; border-radius:10px; margin-bottom:12px;">
        <summary style="padding:12px 16px; cursor:pointer; display:flex; justify-content:space-between; gap:12px;">
            <span style="font-weight:600; color:#1A1F1F;">{{ $sub['student']?->name ?? 'Discente' }} <span style="font-weight:400; color:#8A9696; font-size:0.8rem;">{{ $sub['student']?->email }}</span></span>
            <span style="color:#8A9696; font-size:0.8rem;">salvata {{ $sub['updated_at']?->format('d/m/Y H:i') }}</span>
        </summary>
        <div style="padding:4px 16px 16px;">
            @foreach($sub['fields'] as $f)
            <div style="margin-top:12px;">
                <div style="font-size:0.75rem; font-weight:700; color:#5A6464; text-transform:uppercase; letter-spacing:.03em;">{{ $f['label'] }}</div>
                @if($f['rows'] !== null)
                    @if(empty($f['rows']))
                        <div style="color:#8A9696; font-size:0.85rem;">—</div>
                    @else
                    @php $cols = array_keys(array_merge(...array_map(fn ($r) => $r, $f['rows']))); @endphp
                    <table style="width:100%; border-collapse:collapse; font-size:0.8rem; margin-top:4px;">
                        @if(count(array_filter($cols, fn ($c) => $c !== '' && !is_int($c))))
                        <thead><tr>@foreach($cols as $c)<th style="text-align:left; padding:6px; background:#F5F7F7; color:#5A6464;">{{ is_int($c) ? '' : ucfirst(str_replace('_', ' ', $c)) }}</th>@endforeach</tr></thead>
                        @endif
                        <tbody>
                        @foreach($f['rows'] as $r)
                            <tr>@foreach($cols as $c)<td style="padding:6px; border-bottom:1px solid #F0F0F0; vertical-align:top; white-space:pre-wrap;">{{ $r[$c] ?? '' }}</td>@endforeach</tr>
                        @endforeach
                        </tbody>
                    </table>
                    @endif
                @else
                    <div style="white-space:pre-wrap; font-size:0.9rem; color:#1A1F1F; line-height:1.5;">{{ trim($f['text'] ?? '') !== '' ? $f['text'] : '—' }}</div>
                @endif
            </div>
            @endforeach
        </div>
    </details>
    @empty
    <div style="padding:32px; text-align:center; background:#F7F9F9; border-radius:8px; color:#6B7280;">Nessun discente ha ancora compilato questa scheda.</div>
    @endforelse
</div>
@endsection
