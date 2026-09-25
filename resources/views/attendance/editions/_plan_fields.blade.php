{{-- Campi di pianificazione giornate (nuova edizione e "aggiungi giornate"). --}}
<div style="display:grid; grid-template-columns:repeat(4, 1fr); gap:12px;">
    <div><label class="ed-label">Prima giornata</label><input type="date" name="first_date" value="{{ old('first_date', $defaultDate ?? now()->toDateString()) }}" required class="ed-input plan-in" style="width:100%;"></div>
    <div><label class="ed-label">Numero di giornate</label><input type="number" name="days" min="1" max="120" value="{{ old('days', $defaultDays ?? 5) }}" required class="ed-input plan-in" style="width:100%;"></div>
    <div><label class="ed-label">Inizio (ora)</label><input type="time" name="start_time" value="{{ old('start_time', '09:00') }}" required class="ed-input plan-in" style="width:100%;"></div>
    <div><label class="ed-label">Ore per giornata</label><input type="number" name="hours_per_day" min="0.25" max="24" step="0.25" value="{{ old('hours_per_day', 4) }}" required class="ed-input plan-in" style="width:100%;"></div>
</div>
<div style="margin-top:12px;">
    <span class="ed-label">Giorni della settimana</span>
    @php $wd = old('weekdays', [1,2,3,4,5]); @endphp
    @foreach([1=>'Lun',2=>'Mar',3=>'Mer',4=>'Gio',5=>'Ven',6=>'Sab',7=>'Dom'] as $n => $l)
        <label style="margin-right:10px; font-size:0.85rem;"><input type="checkbox" name="weekdays[]" value="{{ $n }}" class="plan-in" @checked(in_array($n, array_map('intval', $wd)))> {{ $l }}</label>
    @endforeach
</div>
<p class="plan-preview" style="margin-top:10px; font-size:0.8rem; color:#3A8C89;"></p>
<script>
    (function () {
        const box = document.currentScript.closest('form');
        const out = box.querySelector('.plan-preview');
        function preview() {
            const first = box.querySelector('[name=first_date]').value;
            const n = parseInt(box.querySelector('[name=days]').value || '0', 10);
            const wd = Array.from(box.querySelectorAll('[name="weekdays[]"]:checked')).map(c => parseInt(c.value, 10));
            const h = parseFloat(box.querySelector('[name=hours_per_day]').value || '0');
            if (!first || !n || !wd.length) { out.textContent = ''; return; }
            const d = new Date(first + 'T00:00:00'); const dates = [];
            for (let g = 0; dates.length < n && g < 3660; g++, d.setDate(d.getDate() + 1)) {
                const iso = d.getDay() === 0 ? 7 : d.getDay();
                if (wd.includes(iso)) dates.push(d.toLocaleDateString('it-IT', { weekday: 'short', day: '2-digit', month: '2-digit' }));
            }
            out.textContent = 'Giornate: ' + dates.join(' · ') + ' — totale ' + (n * h).toLocaleString('it-IT') + ' ore';
        }
        box.querySelectorAll('.plan-in').forEach(el => el.addEventListener('input', preview));
        preview();
    })();
</script>
