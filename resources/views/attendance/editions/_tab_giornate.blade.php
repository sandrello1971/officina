{{-- Giornate: appello, modifica, eliminazione, aggiunta. --}}
<div class="ed-card">
    @if($days->isEmpty())
        <p style="color:#6B7280;">Nessuna giornata.</p>
    @else
    <table class="ed-table">
        <thead><tr><th>#</th><th>Giornata</th><th>Data</th><th>Orario</th><th>Ore</th><th>Appello</th><th></th></tr></thead>
        <tbody>
        @foreach($days as $d)
            @php $n = $marked[$d->id] ?? 0; $tot = $edition->students->count(); @endphp
            <tr>
                <td>{{ $d->day_number }}</td>
                <td>{{ $d->title }}</td>
                <td>{{ $d->scheduled_at?->locale('it')->isoFormat('ddd D MMM YYYY') }}</td>
                <td>{{ $d->scheduled_at?->format('H:i') }}–{{ $d->endsAt()?->format('H:i') }}</td>
                <td>{{ \App\Support\AttendanceCell::hours($d->duration_minutes / 60) }}</td>
                <td><a href="{{ $nav->url('day', $edition, $d) }}" class="ed-btn {{ $n === 0 ? 'primary' : '' }}">{{ $n === 0 ? 'Fai appello' : "Appello {$n}/{$tot}" }}</a></td>
                <td style="white-space:nowrap; text-align:right;">
                    <button type="button" class="ed-btn" onclick="const r = document.getElementById('move-{{ $d->id }}'); r.style.display = r.style.display === 'none' ? '' : 'none';">&#128197; Sposta / modifica</button>
                    <form method="POST" action="{{ $nav->url('days.destroy', $edition, $d) }}" style="display:inline;" data-busy="Elimino…"
                          onsubmit="return confirm('Eliminare {{ $d->title }}{{ $n ? ' e il suo appello' : '' }}?')">@csrf @method('DELETE')
                        <button type="submit" class="ed-btn danger">Elimina</button></form>
                </td>
            </tr>
            <tr id="move-{{ $d->id }}" style="display:none; background:#F7F9F9;">
                <td colspan="7">
                    <form method="POST" action="{{ $nav->url('days.update', $edition, $d) }}" data-busy="Sposto…" style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap; padding:6px 0;">
                        @csrf @method('PATCH')
                        <div><label class="ed-label">Nuova data</label><input type="date" name="date" value="{{ $d->scheduled_at?->toDateString() }}" required class="ed-input"></div>
                        <div><label class="ed-label">Inizio</label><input type="time" name="start_time" value="{{ $d->scheduled_at?->format('H:i') }}" required class="ed-input"></div>
                        <div><label class="ed-label">Ore</label><input type="number" name="hours" step="0.25" min="0.25" value="{{ round($d->duration_minutes / 60, 2) }}" required class="ed-input" style="width:80px;"></div>
                        <div><label class="ed-label">Titolo</label><input name="title" value="{{ $d->title }}" required class="ed-input" style="width:180px;"></div>
                        <button type="submit" class="ed-btn primary">Salva</button>
                        <span style="font-size:0.75rem; color:#8A9696;">Le giornate si rinumerano in ordine di data{{ $n ? '; le ore dell\'appello vengono ricalcolate' : '' }}.</span>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>

<form method="POST" action="{{ $nav->url('days.store', $edition) }}" class="ed-card" data-busy="Aggiungo le giornate…">
    @csrf
    <h3 style="font-size:0.95rem; font-weight:700; margin-bottom:12px;">Aggiungi giornate</h3>
    @include('attendance.editions._plan_fields', [
        'defaultDate' => $days->last()?->scheduled_at?->copy()->addDay()->toDateString() ?? now()->toDateString(),
        'defaultDays' => 1,
    ])
    <button type="submit" class="ed-btn primary" style="margin-top:6px;">Aggiungi</button>
</form>

<form method="POST" action="{{ $nav->url('update', $edition) }}" class="ed-card" data-busy="Salvo…">
    @csrf @method('PATCH')
    <h3 style="font-size:0.95rem; font-weight:700; margin-bottom:12px;">Dati dell'edizione</h3>
    @include('attendance.editions._edition_fields')
    <button type="submit" class="ed-btn primary" style="margin-top:12px;">Salva</button>
</form>

<form method="POST" action="{{ $nav->url('destroy', $edition) }}" class="ed-card" style="border:1px solid #F4C7C3;" data-busy="Elimino…">
    @csrf @method('DELETE')
    <h3 style="font-size:0.95rem; font-weight:700; color:#B42318; margin-bottom:6px;">Elimina edizione</h3>
    <p style="font-size:0.82rem; color:#6B7280; margin-bottom:8px;">Cancella giornate e appelli. I discenti restano iscritti al corso.</p>
    <label class="ed-label">Scrivi <strong>{{ $edition->name }}</strong> per confermare</label>
    <input name="confirm" autocomplete="off" class="ed-input" style="width:320px;">
    <button type="submit" class="ed-btn danger">Elimina definitivamente</button>
</form>
