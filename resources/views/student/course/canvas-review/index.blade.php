@extends('layouts.student')
@section('title', 'Schede dei discenti — ' . $course->name)
@section('breadcrumb', 'Schede dei discenti')
@section('content')
<div style="max-width:960px; margin:0 auto;">
    <a href="{{ route('student.course.show', $course->slug) }}" style="font-size:0.8rem; color:#55B1AE; text-decoration:none;">&larr; {{ $course->name }}</a>
    <h2 style="font-size:1.25rem; font-weight:700; color:#1A1F1F; margin-top:4px;">Schede dei discenti</h2>
    <p style="color:#6B7280; font-size:0.85rem; margin:6px 0 18px;">
        I canvas dei laboratori compilati dagli iscritti al corso ({{ $enrolledCount }} attivi). Sola lettura: i discenti le salvano mentre scrivono.
    </p>

    @if($canvases->isEmpty())
        <div style="padding:32px; text-align:center; background:#F7F9F9; border-radius:8px; color:#6B7280;">Questo corso non ha canvas compilabili.</div>
    @else
        <table style="width:100%; border-collapse:collapse; font-size:0.85rem; background:white; border-radius:8px; overflow:hidden;">
            <thead>
                <tr style="text-align:left; color:#6B7280; border-bottom:2px solid #E5E7EB;">
                    <th style="padding:10px 12px;">Canvas</th>
                    <th style="padding:10px 12px;">Modulo</th>
                    <th style="padding:10px 12px;">Compilate</th>
                    <th style="padding:10px 12px;">Ultimo salvataggio</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($canvases as $c)
                @php $stat = $counts->get($c->id); @endphp
                <tr style="border-bottom:1px solid #F0F0F0;">
                    <td style="padding:10px 12px; font-weight:600; color:#1A1F1F;">{{ $c->title }}</td>
                    <td style="padding:10px 12px; color:#5A6464;">{{ $c->module?->title ?? '—' }}</td>
                    <td style="padding:10px 12px;">{{ $stat->n ?? 0 }} / {{ $enrolledCount }}</td>
                    <td style="padding:10px 12px; color:#5A6464;">{{ $stat?->last_at ? \Illuminate\Support\Carbon::parse($stat->last_at)->format('d/m/Y H:i') : '—' }}</td>
                    <td style="padding:10px 12px; text-align:right;">
                        <a href="{{ route('student.course.canvas-review.show', [$course->slug, $c]) }}" style="color:#55B1AE; font-weight:600; text-decoration:none;">Apri &rarr;</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
