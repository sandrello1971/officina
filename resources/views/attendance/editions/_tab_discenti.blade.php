{{-- Discenti dell'edizione e aggiunta (ricerca + selezione multipla). --}}
<div class="ed-card">
    <h3 style="font-size:0.95rem; font-weight:700; margin-bottom:10px;">Discenti dell'edizione ({{ $edition->students->count() }})</h3>
    @if($edition->students->isEmpty())
        <p style="color:#6B7280;">Nessun discente ancora: aggiungili qui sotto.</p>
    @else
    <table class="ed-table">
        <thead><tr><th>Nome</th><th>Email</th><th></th></tr></thead>
        <tbody>
        @foreach($edition->students as $s)
            <tr>
                <td>{{ $s->name }}</td><td>{{ $s->email }}</td>
                <td style="text-align:right;">
                    <form method="POST" action="{{ $nav->url('students.destroy', $edition, $s) }}" data-busy="Tolgo…"
                          onsubmit="return confirm('Togliere {{ addslashes($s->name) }} dall\'edizione? I suoi appelli verranno cancellati; resta iscritto al corso.')">
                        @csrf @method('DELETE')
                        <button type="submit" class="ed-btn danger">Togli</button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>

@php $available = $candidates->reject(fn ($c) => $edition->students->contains('id', $c->id)); @endphp
<style>.cand.cand-off { display:none !important; }</style>
<form method="POST" action="{{ $nav->url('students.store', $edition) }}" class="ed-card" data-busy="Aggiungo…" id="add-students">
    @csrf
    <h3 style="font-size:0.95rem; font-weight:700; margin-bottom:4px;">Aggiungi discenti</h3>
    <p style="font-size:0.78rem; color:#8A9696; margin-bottom:10px;">
        Scrivi una parte del nome o dell'email (es. il dominio dell'azienda), poi "Seleziona tutti i risultati".
        Chi non è ancora iscritto al corso viene iscritto automaticamente; chi è già in un'altra edizione di questo corso non compare.
    </p>
    @if($available->isEmpty())
        <p style="color:#6B7280;">Nessun discente disponibile. Creali prima dall'anagrafica discenti.</p>
    @else
    <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:8px;">
        <input type="search" id="cand-q" placeholder="Cerca per nome o email… (es. mavigex)" class="ed-input" style="flex:1; min-width:240px;" autocomplete="off">
        <button type="button" class="ed-btn" id="cand-all">Seleziona tutti i risultati</button>
        <button type="button" class="ed-btn" id="cand-none" style="border-color:#D1D5DB; color:#6B7280;">Deseleziona tutti</button>
    </div>
    <p id="cand-info" style="font-size:0.78rem; color:#6B7280; margin-bottom:6px;"></p>
    <div style="max-height:360px; overflow-y:auto; border:1px solid #E5E7EB; border-radius:6px;">
        @foreach($available as $c)
            <label class="cand" data-k="{{ strtolower($c->name . ' ' . $c->email) }}" style="display:flex; gap:10px; padding:7px 10px; border-bottom:1px solid #F0F2F2; font-size:0.85rem; cursor:pointer;">
                <input type="checkbox" name="student_ids[]" value="{{ $c->id }}">
                <span style="flex:1;">{{ $c->name }} <span style="color:#8A9696;">{{ $c->email }}</span></span>
                @if(in_array($c->id, $enrolledIds, true))<span style="font-size:0.72rem; color:#3A8C89;">iscritto al corso</span>@endif
            </label>
        @endforeach
        <p id="cand-empty" style="display:none; padding:12px; color:#8A9696; font-size:0.85rem;">Nessun discente corrisponde alla ricerca.</p>
    </div>
    <button type="submit" class="ed-btn primary" id="cand-submit" style="margin-top:10px;" disabled>Aggiungi selezionati</button>
    <script>
        (function () {
            const form = document.getElementById('add-students');
            const q = document.getElementById('cand-q');
            const rows = Array.from(form.querySelectorAll('.cand'));
            const info = document.getElementById('cand-info');
            const submit = document.getElementById('cand-submit');
            const visible = () => rows.filter(r => !r.classList.contains('cand-off'));
            function refresh() {
                const sel = rows.filter(r => r.querySelector('input').checked).length;
                info.textContent = visible().length + ' visibili su ' + rows.length + ' · ' + sel + ' selezionati';
                submit.disabled = sel === 0;
                submit.textContent = sel ? 'Aggiungi ' + sel + ' ' + (sel === 1 ? 'discente' : 'discenti') : 'Aggiungi selezionati';
                document.getElementById('cand-empty').style.display = visible().length ? 'none' : '';
            }
            q.addEventListener('input', function () {
                const term = q.value.trim().toLowerCase();
                // Classe e non style.display: azzerare lo stile toglierebbe il display:flex della riga.
                rows.forEach(r => r.classList.toggle('cand-off', !!term && !r.dataset.k.includes(term)));
                refresh();
            });
            // Invio nella ricerca non deve inviare il form.
            q.addEventListener('keydown', e => { if (e.key === 'Enter') e.preventDefault(); });
            document.getElementById('cand-all').addEventListener('click', () => { visible().forEach(r => r.querySelector('input').checked = true); refresh(); });
            document.getElementById('cand-none').addEventListener('click', () => { rows.forEach(r => r.querySelector('input').checked = false); refresh(); });
            form.addEventListener('change', refresh);
            refresh();
        })();
    </script>
    @endif
</form>
