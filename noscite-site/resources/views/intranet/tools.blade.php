@extends('layouts.intranet')
@section('title', 'Strumenti')
@section('content')

@php
$isAdmin = session('intranet_user')['is_admin'] ?? false;
$typeOptions = [
    'tool'     => '🔧 Strumento',
    'poc'      => '🧪 POC',
    'demo'     => '🎬 Demo',
    'servizio' => '🏢 Servizio',
    'mvp'      => '🚀 MVP',
];
@endphp

<style>
.tools-wrap { width:100%; }
.tools-filters { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:14px; }
.tools-filters input, .tools-filters select {
    padding:8px 12px; border:1px solid #C8D0D0; border-radius:8px;
    font-size:0.875rem; outline:none; background:white;
}
.tools-filters input:focus, .tools-filters select:focus { border-color:#55B1AE; }
.tools-filters input[type="text"] { flex:1; min-width:180px; }
.tools-table-wrap {
    overflow-x:auto; background:white; border-radius:12px;
    box-shadow:0 1px 4px rgba(0,0,0,0.04);
}
.tools-table { width:100%; border-collapse:collapse; font-size:0.875rem; min-width:1100px; }
.tools-table thead tr { background:#F5F7F7; }
.tools-table th {
    padding:10px 12px; text-align:left; font-size:0.72rem;
    color:#8A9696; text-transform:uppercase; letter-spacing:0.08em;
    font-weight:700; border-bottom:2px solid #E8F5F5;
    white-space:nowrap;
}
.tools-table td { padding:6px 10px; border-bottom:1px solid #F5F7F7; vertical-align:middle; white-space:nowrap; }
.tools-table tbody tr:hover td { background:#fafbfb; }

/* Colonne a larghezza contenuto */
.col-shrink { width:1%; }

/* Colonna descrizione con ellipsis */
.col-description {
    max-width:280px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
.col-description input { max-width:100%; }

.cell-input, .cell-select {
    width:100%; padding:5px 8px; font-size:0.875rem;
    border:1px solid transparent; border-radius:5px;
    background:transparent; color:#1A1F1F;
    font-family:inherit; outline:none;
    transition:background-color .15s, border-color .15s;
}
.cell-input:hover, .cell-select:hover { border-color:#E8F5F5; background:#fafbfb; }
.cell-input:focus, .cell-select:focus { border-color:#55B1AE; background:white; }

/* Min-width per i select che contengono testi lunghi */
.cell-select[data-field="type"]      { min-width:130px; }
.cell-select[data-field="server_id"] { min-width:200px; }
.cell-input[data-field="status"]     { min-width:90px; width:90px; }
.cell-input[data-field="name"]       { min-width:180px; }
.cell-input[data-field="description"]{ min-width:220px; }
.cell-input[data-field="url"]        { min-width:260px; }
.cell-saved { background:#E8F5F5 !important; transition:background-color .5s; }
.cell-error { background:#fff3ec !important; border-color:#E28A53 !important; }
.cell-icon { width:42px; text-align:center; font-size:1.2rem; }
.cell-icon-input { text-align:center; padding:5px 4px; }
.cell-actions { white-space:nowrap; }

/* URL cell con icona link accanto */
.url-cell { display:flex; align-items:center; gap:6px; min-width:280px; }
.url-cell .cell-input { flex:1; min-width:260px; }
.url-link-btn {
    flex-shrink:0; width:28px; height:28px; display:flex;
    align-items:center; justify-content:center;
    border-radius:6px; color:#55B1AE; text-decoration:none;
    transition:background .15s; font-size:0.9rem;
}
.url-link-btn:hover { background:#E8F5F5; }
.url-link-btn.disabled { opacity:0.3; pointer-events:none; }

.btn-del {
    background:transparent; color:#E28A53; border:1px solid #E28A53;
    padding:3px 10px; border-radius:6px; font-size:0.72rem; cursor:pointer;
    font-family:inherit;
}
.btn-del:hover { background:#fff3ec; }
.type-badge {
    display:inline-block; padding:2px 8px; border-radius:4px;
    font-size:0.72rem; font-weight:700; white-space:nowrap;
}
.type-tool     { background:#E8F5F5; color:#3A8C89; }
.type-poc      { background:#fff3ec; color:#c97a45; }
.type-demo     { background:#f0e8f5; color:#7c3aed; }
.type-servizio { background:#e8f0f5; color:#1d4ed8; }
.type-mvp      { background:#f5f0e8; color:#b45309; }
.vps-badge {
    display:inline-block; padding:2px 8px; background:#F5F7F7;
    color:#4A5252; border-radius:4px; font-size:0.72rem;
}
.row-inactive { opacity:0.5; }
.checkbox-cell { text-align:center; }
.add-btn {
    padding:8px 18px; background:#55B1AE; color:white; border:none;
    border-radius:8px; font-size:0.875rem; font-weight:700; cursor:pointer;
    font-family:inherit;
}
.add-btn:hover { background:#3A8C89; }
dialog#add-dialog {
    border:none; border-radius:12px; padding:24px; max-width:560px;
    width:90%; box-shadow:0 4px 20px rgba(0,0,0,0.15);
}
dialog#add-dialog::backdrop { background:rgba(0,0,0,0.4); }
dialog#add-dialog h3 { font-weight:700; color:#1A1F1F; margin-bottom:16px; font-size:1rem; }
dialog#add-dialog label { font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:4px; }
dialog#add-dialog input, dialog#add-dialog select, dialog#add-dialog textarea {
    width:100%; padding:8px 12px; border:1px solid #C8D0D0;
    border-radius:6px; font-size:0.875rem; outline:none;
    font-family:inherit; margin-bottom:10px;
}
dialog#add-dialog input:focus, dialog#add-dialog select:focus, dialog#add-dialog textarea:focus {
    border-color:#55B1AE;
}
</style>

<div class="tools-wrap">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
        <h1 style="font-size:1.25rem; font-weight:700; color:#1A1F1F;">🔧 Strumenti aziendali</h1>
        @if($isAdmin)
        <button type="button" class="add-btn" onclick="document.getElementById('add-dialog').showModal()">+ Nuova voce</button>
        @endif
    </div>

    @if(session('success'))
    <div style="margin-bottom:16px; padding:10px 14px; background:#E8F5F5; border-left:4px solid #55B1AE; border-radius:6px; color:#3A8C89; font-size:0.875rem;">
        ✓ {{ session('success') }}
    </div>
    @endif

    <div class="tools-filters">
        <input type="text" id="f-q" placeholder="Cerca per nome o descrizione..." oninput="filterRows()">
        <select id="f-type" onchange="filterRows()">
            <option value="">Tutti i tipi</option>
            @foreach($typeOptions as $k => $v)
            <option value="{{ $k }}">{{ $v }}</option>
            @endforeach
        </select>
        <select id="f-server" onchange="filterRows()">
            <option value="">Tutti i VPS</option>
            <option value="__none__">Nessun VPS</option>
            @foreach($servers as $s)
            <option value="{{ $s->id }}">{{ $s->name }}</option>
            @endforeach
        </select>
    </div>

    <datalist id="status-list">
        <option value="LIVE">
        <option value="WIP">
        <option value="BETA">
        <option value="PROD">
        <option value="DEV">
    </datalist>

    <div class="tools-table-wrap">
        <table class="tools-table" id="tools-table">
            <thead>
                <tr>
                    <th class="col-shrink"></th>
                    <th>Nome</th>
                    <th>Tipo</th>
                    <th>Descrizione</th>
                    <th>URL</th>
                    <th>VPS</th>
                    <th>Status</th>
                    <th class="col-shrink">Attivo</th>
                    @if($isAdmin)<th class="col-shrink"></th>@endif
                </tr>
            </thead>
            <tbody id="tools-tbody">
                @forelse($tools as $t)
                <tr data-id="{{ $t->id }}"
                    data-type="{{ $t->type }}"
                    data-server="{{ $t->server_id ?? '__none__' }}"
                    data-name="{{ strtolower($t->name) }}"
                    data-description="{{ strtolower($t->description ?? '') }}"
                    class="{{ $t->active ? '' : 'row-inactive' }}">

                    {{-- ICONA --}}
                    <td class="cell-icon col-shrink">
                        @if($isAdmin)
                        <input type="text" class="cell-input cell-icon-input" data-field="icon"
                               value="{{ $t->icon }}" maxlength="4">
                        @else
                        {{ $t->icon }}
                        @endif
                    </td>

                    {{-- NOME --}}
                    <td>
                        @if($isAdmin)
                        <input type="text" class="cell-input" data-field="name" value="{{ $t->name }}"
                               style="font-weight:600; min-width:180px;">
                        @else
                        <strong style="color:#1A1F1F;">{{ $t->name }}</strong>
                        @endif
                    </td>

                    {{-- TIPO --}}
                    <td>
                        @if($isAdmin)
                        <select class="cell-select" data-field="type">
                            @foreach($typeOptions as $k => $v)
                            <option value="{{ $k }}" {{ $t->type === $k ? 'selected' : '' }}>{{ $v }}</option>
                            @endforeach
                        </select>
                        @else
                        <span class="type-badge type-{{ $t->type }}">{{ $typeOptions[$t->type] ?? $t->type }}</span>
                        @endif
                    </td>

                    {{-- DESCRIZIONE --}}
                    <td class="col-description" title="{{ $t->description }}">
                        @if($isAdmin)
                        <input type="text" class="cell-input" data-field="description"
                               value="{{ $t->description }}" placeholder="—">
                        @else
                        <span style="color:#8A9696;">{{ $t->description }}</span>
                        @endif
                    </td>

                    {{-- URL --}}
                    <td>
                        @if($isAdmin)
                        <div class="url-cell">
                            <input type="url" class="cell-input" data-field="url" value="{{ $t->url }}">
                            <a href="{{ $t->url ?: '#' }}" target="_blank" rel="noopener"
                               class="url-link-btn {{ $t->url ? '' : 'disabled' }}" title="Apri URL">🔗</a>
                        </div>
                        @else
                        <a href="{{ $t->url }}" target="_blank" rel="noopener"
                           style="color:#55B1AE; font-size:0.85rem;">{{ $t->label ?? parse_url($t->url, PHP_URL_HOST) }}</a>
                        @endif
                    </td>

                    {{-- VPS --}}
                    <td>
                        @if($isAdmin)
                        <select class="cell-select" data-field="server_id">
                            <option value="">— nessuno —</option>
                            @foreach($servers as $s)
                            <option value="{{ $s->id }}" {{ $t->server_id == $s->id ? 'selected' : '' }}>
                                {{ $s->name }}{{ $s->provider ? ' ('.$s->provider.')' : '' }}
                            </option>
                            @endforeach
                        </select>
                        @else
                        @if($t->server)
                        <span class="vps-badge">🖥 {{ $t->server->name }}</span>
                        @else
                        <span style="color:#C8D0D0;">—</span>
                        @endif
                        @endif
                    </td>

                    {{-- STATUS --}}
                    <td>
                        @if($isAdmin)
                        <input type="text" class="cell-input" data-field="status"
                               list="status-list" value="{{ $t->status }}" placeholder="—">
                        @else
                        @if($t->status)
                        <span style="font-size:0.72rem; padding:2px 8px; background:#E8F5F5; color:#3A8C89; border-radius:4px; font-weight:700;">
                            {{ $t->status }}
                        </span>
                        @else
                        <span style="color:#C8D0D0;">—</span>
                        @endif
                        @endif
                    </td>

                    {{-- ATTIVO --}}
                    <td class="checkbox-cell col-shrink">
                        @if($isAdmin)
                        <input type="checkbox" class="cell-check" data-field="active" {{ $t->active ? 'checked' : '' }}>
                        @else
                        {{ $t->active ? '✓' : '○' }}
                        @endif
                    </td>

                    {{-- AZIONI --}}
                    @if($isAdmin)
                    <td class="cell-actions col-shrink">
                        <form method="POST" action="/intranet/manage/{{ $t->id }}" style="display:inline;"
                              onsubmit="return confirm('Eliminare {{ addslashes($t->name) }}?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn-del">Elimina</button>
                        </form>
                    </td>
                    @endif
                </tr>
                @empty
                <tr>
                    <td colspan="{{ $isAdmin ? 9 : 8 }}" style="padding:40px; text-align:center; color:#8A9696;">
                        Nessuno strumento. Aggiungine uno con "+ Nuova voce".
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- DIALOG NUOVA VOCE --}}
@if($isAdmin)
<dialog id="add-dialog">
    <h3>+ Nuova voce</h3>
    <form method="POST" action="/intranet/manage">
        @csrf
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
            <div>
                <label>Tipo *</label>
                <select name="type" required>
                    @foreach($typeOptions as $k => $v)
                    <option value="{{ $k }}">{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>Icona</label>
                <input type="text" name="icon" placeholder="🔧" maxlength="4">
            </div>
            <div style="grid-column:1/-1;">
                <label>Nome *</label>
                <input type="text" name="name" required>
            </div>
            <div style="grid-column:1/-1;">
                <label>Descrizione</label>
                <textarea name="description" rows="2"></textarea>
            </div>
            <div style="grid-column:1/-1;">
                <label>URL *</label>
                <input type="url" name="url" required placeholder="https://...">
            </div>
            <div>
                <label>Label</label>
                <input type="text" name="label">
            </div>
            <div>
                <label>Status</label>
                <input type="text" name="status" list="status-list">
            </div>
            <div style="grid-column:1/-1;">
                <label>VPS</label>
                <select name="server_id">
                    <option value="">— nessuno —</option>
                    @foreach($servers as $s)
                    <option value="{{ $s->id }}">{{ $s->name }} ({{ $s->provider }})</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:14px;">
            <button type="button" onclick="document.getElementById('add-dialog').close()"
                    style="padding:8px 18px; border:1px solid #C8D0D0; color:#4A5252; background:white; border-radius:6px; cursor:pointer; font-family:inherit; font-size:0.875rem;">
                Annulla
            </button>
            <button type="submit" class="add-btn">Crea</button>
        </div>
    </form>
</dialog>
@endif

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const debounceTimers = {};

function flashCell(el, ok = true) {
    el.classList.remove('cell-saved', 'cell-error');
    el.classList.add(ok ? 'cell-saved' : 'cell-error');
    setTimeout(() => el.classList.remove('cell-saved', 'cell-error'), 800);
}

async function saveField(row, el) {
    const id = row.dataset.id;
    const field = el.dataset.field;
    let value;
    if (el.type === 'checkbox') value = el.checked ? 1 : 0;
    else value = el.value;

    try {
        const res = await fetch(`/intranet/manage/${id}/field`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify({ field, value })
        });
        const data = await res.json();
        if (data.ok) {
            flashCell(el, true);
            if (field === 'type') row.dataset.type = el.value;
            if (field === 'name') row.dataset.name = el.value.toLowerCase();
            if (field === 'description') row.dataset.description = el.value.toLowerCase();
            if (field === 'server_id') row.dataset.server = el.value || '__none__';
            if (field === 'active') row.classList.toggle('row-inactive', !el.checked);
        } else {
            flashCell(el, false);
            console.warn('save error:', data.error);
        }
    } catch (e) {
        flashCell(el, false);
        console.error(e);
    }
}

document.querySelectorAll('#tools-tbody tr').forEach(row => {
    row.querySelectorAll('.cell-input').forEach(input => {
        input.addEventListener('input', () => {
            clearTimeout(debounceTimers[input]);
            debounceTimers[input] = setTimeout(() => saveField(row, input), 500);

            // Aggiorna href del link URL dinamicamente
            if (input.dataset.field === 'url') {
                const anchor = input.parentElement.querySelector('.url-link-btn');
                if (anchor) {
                    const v = input.value.trim();
                    anchor.href = v || '#';
                    anchor.classList.toggle('disabled', !v);
                }
            }
        });
        input.addEventListener('blur', () => {
            clearTimeout(debounceTimers[input]);
            saveField(row, input);
        });
    });
    row.querySelectorAll('.cell-select').forEach(sel => {
        sel.addEventListener('change', () => saveField(row, sel));
    });
    row.querySelectorAll('.cell-check').forEach(cb => {
        cb.addEventListener('change', () => saveField(row, cb));
    });
});

// ── FILTRI CLIENT-SIDE ──
function filterRows() {
    const q = document.getElementById('f-q').value.toLowerCase();
    const type = document.getElementById('f-type').value;
    const server = document.getElementById('f-server').value;

    document.querySelectorAll('#tools-tbody tr[data-id]').forEach(row => {
        const name = row.dataset.name || '';
        const desc = row.dataset.description || '';
        const rowType = row.dataset.type || '';
        const rowServer = row.dataset.server || '';

        const matchQ = !q || name.includes(q) || desc.includes(q);
        const matchType = !type || rowType === type;
        const matchServer = !server || rowServer === server;

        row.style.display = (matchQ && matchType && matchServer) ? '' : 'none';
    });
}
</script>
@endsection
