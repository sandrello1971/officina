{{-- Griglia discenti × giornate. --}}
<div class="ed-card" style="overflow-x:auto;">
    @if($days->isEmpty() || $register['rows']->isEmpty())
        <p style="color:#6B7280; text-align:center; padding:20px 0;">
            {{ $days->isEmpty() ? 'Nessuna giornata: aggiungile dalla scheda Giornate.' : 'Nessun discente: associali dalla scheda Discenti.' }}
        </p>
    @else
    <table class="ed-table">
        <thead>
            <tr>
                <th>Discente</th>
                @foreach($days as $d)
                    <th style="text-align:center; min-width:62px;">
                        <a href="{{ $nav->url('day', $edition, $d) }}" style="color:#3A8C89; text-decoration:none;" title="Apri l'appello">G{{ $d->day_number }}<br><span style="font-weight:400; font-size:0.72rem;">{{ $d->scheduled_at?->format('d/m') }}</span></a>
                    </th>
                @endforeach
                <th style="text-align:center;">Ore</th><th style="text-align:center;">%</th>
            </tr>
        </thead>
        <tbody>
        @foreach($register['rows'] as $row)
            <tr>
                <td>{{ $row['student']->name }}</td>
                @foreach($days as $d)
                    @php $cell = $row['cells'][$d->id]; $label = \App\Support\AttendanceCell::label($cell); @endphp
                    <td class="ed-cell {{ $label === '' ? '' : (in_array($label, ['P','A','AG']) ? $label : 'RU') }}" title="{{ \App\Support\AttendanceCell::describe($cell) }}">
                        {{ $label === '' ? '·' : $label }}@if($cell['record']?->note)<sup>✎</sup>@endif
                    </td>
                @endforeach
                <td style="text-align:center; white-space:nowrap;">{{ \App\Support\AttendanceCell::hours($row['hours']) }} / {{ \App\Support\AttendanceCell::hours($row['planned']) }}</td>
                <td style="text-align:center; font-weight:600; color:{{ $row['percent'] >= 80 ? '#2B6F6C' : '#B4691A' }};">{{ number_format($row['percent'], 0) }}%</td>
            </tr>
        @endforeach
        <tr style="font-weight:600;">
            <td>Presenti</td>
            @foreach($days as $d)<td class="ed-cell">{{ $register['present_per_day'][$d->id] }}/{{ $register['rows']->count() }}</td>@endforeach
            <td></td><td></td>
        </tr>
        </tbody>
    </table>
    <p style="font-size:0.75rem; color:#8A9696; margin-top:10px;">P presente · A assente · AG assente giustificato · R entrata in ritardo · U uscita anticipata · ✎ nota · · appello non ancora fatto. Clicca una giornata per fare l'appello.</p>
    @endif
</div>
