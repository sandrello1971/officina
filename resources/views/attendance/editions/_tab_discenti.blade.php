{{-- Discenti dell'edizione e aggiunta. --}}
<div class="ed-card">
    <h3 style="font-size:0.95rem; font-weight:700; margin-bottom:10px;">Discenti dell'edizione</h3>
    @if($edition->students->isEmpty())
        <p style="color:#6B7280;">Nessun discente ancora.</p>
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
<form method="POST" action="{{ $nav->url('students.store', $edition) }}" class="ed-card" data-busy="Aggiungo…">
    @csrf
    <h3 style="font-size:0.95rem; font-weight:700; margin-bottom:4px;">Aggiungi discenti</h3>
    <p style="font-size:0.78rem; color:#8A9696; margin-bottom:10px;">Chi non è ancora iscritto al corso viene iscritto automaticamente. Chi è già in un'altra edizione di questo corso non compare.</p>
    @if($available->isEmpty())
        <p style="color:#6B7280;">Nessun discente disponibile. Creali prima dall'anagrafica discenti.</p>
    @else
    <div style="display:flex; gap:10px; margin-bottom:8px;">
        <input type="search" placeholder="Cerca per nome o email…" class="ed-input" style="flex:1;"
               oninput="const q=this.value.toLowerCase(); this.form.querySelectorAll('.cand').forEach(r => r.style.display = r.dataset.k.includes(q) ? '' : 'none')">
        <label style="font-size:0.82rem; align-self:center;"><input type="checkbox" onchange="this.form.querySelectorAll('.cand').forEach(r => { if (r.style.display !== 'none') r.querySelector('input').checked = this.checked })"> Seleziona visibili</label>
    </div>
    <div style="max-height:320px; overflow-y:auto; border:1px solid #E5E7EB; border-radius:6px;">
        @foreach($available as $c)
            <label class="cand" data-k="{{ strtolower($c->name . ' ' . $c->email) }}" style="display:flex; gap:10px; padding:7px 10px; border-bottom:1px solid #F0F2F2; font-size:0.85rem; cursor:pointer;">
                <input type="checkbox" name="student_ids[]" value="{{ $c->id }}">
                <span style="flex:1;">{{ $c->name }} <span style="color:#8A9696;">{{ $c->email }}</span></span>
                @if(in_array($c->id, $enrolledIds, true))<span style="font-size:0.72rem; color:#3A8C89;">iscritto al corso</span>@endif
            </label>
        @endforeach
    </div>
    <button type="submit" class="ed-btn primary" style="margin-top:10px;">Aggiungi selezionati</button>
    @endif
</form>
