@extends($layout)
@section('title', $edition->name . ' — ' . $course->name)
@section('breadcrumb', 'Edizione')
@section('content')
@php
    $days = $register['days'];
    $first = $days->first()?->scheduled_at; $last = $days->last()?->scheduled_at;
    $tabUrl = fn ($t) => $nav->url('show', $edition) . '?tab=' . $t;
@endphp
<div class="ed-wrap">
    @include('attendance.editions._style')
    <a href="{{ $nav->url('index') }}" class="ed-back">&larr; Edizioni di {{ $course->name }}</a>
    <div style="display:flex; justify-content:space-between; align-items:flex-end; gap:12px; flex-wrap:wrap;">
        <div>
            <h2 class="ed-h2">{{ $edition->name }}</h2>
            <p class="ed-meta" style="margin:0;">
                {{ $edition->modalityLabel() }}@if($edition->location) · {{ $edition->location }}@endif
                @if($edition->instructor) · Formatore: {{ $edition->instructor->name }}@endif
                @if($first) · {{ $first->format('d/m/Y') }} – {{ $last->format('d/m/Y') }}@endif
                · {{ $days->count() }} giornate · {{ \App\Support\AttendanceCell::hours($register['planned_hours']) }} · {{ $edition->students->count() }} discenti
            </p>
        </div>
        <div style="display:flex; gap:8px;">
            <a href="{{ $nav->url('pdf', $edition) }}" class="ed-btn">Registro PDF</a>
            <a href="{{ $nav->url('csv', $edition) }}" class="ed-btn">Excel (CSV)</a>
        </div>
    </div>

    <div class="ed-tabs" style="margin-top:16px;">
        <a href="{{ $tabUrl('registro') }}" class="ed-tab {{ $tab === 'registro' ? 'active' : '' }}">Registro</a>
        <a href="{{ $tabUrl('giornate') }}" class="ed-tab {{ $tab === 'giornate' ? 'active' : '' }}">Giornate ({{ $days->count() }})</a>
        <a href="{{ $tabUrl('discenti') }}" class="ed-tab {{ $tab === 'discenti' ? 'active' : '' }}">Discenti ({{ $edition->students->count() }})</a>
    </div>

    @include('attendance.editions._tab_' . $tab)
</div>
@endsection
