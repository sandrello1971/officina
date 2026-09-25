@extends($layout)
@section('title', 'Appello — ' . $day->title)
@section('breadcrumb', 'Appello')
@section('content')
@php
    $days = $edition->days()->get(['id', 'day_number', 'scheduled_at']);
    $idx = $days->search(fn ($d) => $d->id === $day->id);
    $prev = $idx > 0 ? $days[$idx - 1] : null; $next = $days[$idx + 1] ?? null;
    $start = $day->scheduled_at?->format('H:i'); $end = $day->endsAt()?->format('H:i');
@endphp
<div class="ed-wrap" style="max-width:980px;">
    @include('attendance.editions._style')
    <a href="{{ $nav->url('show', $edition) }}" class="ed-back">&larr; {{ $edition->name }}</a>
    <div style="display:flex; justify-content:space-between; align-items:flex-end;">
        <div>
            <h2 class="ed-h2">{{ $day->title }} — {{ $day->scheduled_at?->locale('it')->isoFormat('dddd D MMMM YYYY') }}</h2>
            <p class="ed-meta" style="margin:0;">{{ $start }}–{{ $end }} · {{ \App\Support\AttendanceCell::hours($day->duration_minutes / 60) }} · {{ $course->name }}</p>
        </div>
        <div style="display:flex; gap:6px;">
            @if($prev)<a href="{{ $nav->url('day', $edition, $prev) }}" class="ed-btn">&larr; G{{ $prev->day_number }}</a>@endif
            @if($next)<a href="{{ $nav->url('day', $edition, $next) }}" class="ed-btn">G{{ $next->day_number }} &rarr;</a>@endif
        </div>
    </div>

    @if($students->isEmpty())
        <div class="ed-card" style="margin-top:16px; text-align:center; color:#6B7280;">Nessun discente nell'edizione: aggiungili dalla scheda Discenti.</div>
    @else
    <form method="POST" action="{{ $nav->url('mark', $edition, $day) }}" class="ed-card" style="margin-top:16px;" data-busy="Salvo l'appello…">
        @csrf
        <div style="display:flex; gap:8px; margin-bottom:10px;">
            <button type="button" class="ed-btn" onclick="this.form.querySelectorAll('input[type=radio][value=presente]').forEach(r => { r.checked = true; r.dispatchEvent(new Event('change', {bubbles:true})); })">Tutti presenti</button>
            <span style="font-size:0.78rem; color:#8A9696; align-self:center;">Entrata dopo le {{ $start }} = ritardo · uscita prima delle {{ $end }} = uscita anticipata. Le ore si calcolano da sole; il campo "Ore" serve solo a correggerle.</span>
        </div>
        <table class="ed-table">
            <thead><tr><th>Discente</th><th>Stato</th><th>Entrata</th><th>Uscita</th><th>Ore</th><th>Nota</th></tr></thead>
            <tbody>
            @foreach($students as $s)
                @php $r = $records->get($s->id); $st = old("marks.{$s->id}.status", $r?->status); @endphp
                <tr class="mark-row" data-present="{{ $st === 'presente' ? 1 : 0 }}">
                    <td>{{ $s->name }}</td>
                    <td style="white-space:nowrap;">
                        @foreach(['presente' => 'P', 'assente' => 'A', 'assente_giustificato' => 'AG'] as $v => $l)
                            <label style="margin-right:8px; font-size:0.85rem; cursor:pointer;" title="{{ \App\Models\AttendanceRecord::STATUSES[$v] }}">
                                <input type="radio" name="marks[{{ $s->id }}][status]" value="{{ $v }}" @checked($st === $v)
                                       onchange="this.closest('tr').dataset.present = this.value === 'presente' ? 1 : 0"> {{ $l }}
                            </label>
                        @endforeach
                    </td>
                    <td><input type="time" name="marks[{{ $s->id }}][arrived_at]" value="{{ old("marks.{$s->id}.arrived_at", $r?->arrived_at ? substr($r->arrived_at, 0, 5) : '') }}" class="ed-input only-present" style="width:105px;"></td>
                    <td><input type="time" name="marks[{{ $s->id }}][left_at]" value="{{ old("marks.{$s->id}.left_at", $r?->left_at ? substr($r->left_at, 0, 5) : '') }}" class="ed-input only-present" style="width:105px;"></td>
                    <td><input type="number" step="0.25" min="0" max="24" name="marks[{{ $s->id }}][hours]" placeholder="{{ $r && $r->isPresent() ? str_replace('.', ',', (float) $r->hours_credited) : 'auto' }}" class="ed-input only-present" style="width:75px;"></td>
                    <td><input name="marks[{{ $s->id }}][note]" value="{{ old("marks.{$s->id}.note", $r?->note) }}" maxlength="500" class="ed-input" style="width:100%;" placeholder="es. certificato medico"></td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <style>.mark-row[data-present="0"] .only-present { opacity:.35; }</style>
        <button type="submit" class="ed-btn primary" style="margin-top:14px;">Salva appello</button>
        @if($records->isNotEmpty())
            <span style="font-size:0.78rem; color:#8A9696; margin-left:8px;">Ultimo salvataggio: {{ $records->max('updated_at')?->format('d/m/Y H:i') }} · {{ $records->first()->marked_by }}</span>
        @endif
    </form>
    @endif
</div>
@endsection
