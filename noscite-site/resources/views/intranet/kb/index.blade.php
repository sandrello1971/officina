<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Knowledge Base — Noscite</title>
<link rel="icon" type="image/png" href="/images/logo.png">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<style>
@import url('https://fonts.googleapis.com/css2?family=Antonio:wght@400;700&display=swap');
:root {
    --bg:      #000008;
    --bg2:     #08080F;
    --bg3:     #0D0D1A;
    --bg4:     #111122;
    --border:  #1A1A30;
    --text:    #EEEEFF;
    --muted:   #8888BB;
    --dim:     #333355;
    --orange:  #FF9900;
    --purple:  #CC66CC;
    --blue:    #9999FF;
    --teal:    #99CCFF;
    --green:   #99FF99;
    --red:     #FF6666;
    --yellow:  #FFCC00;
    --font:    'Antonio','Futura','Century Gothic',sans-serif;
}
* { box-sizing:border-box; margin:0; padding:0; }
body { background:var(--bg); color:var(--text); font-family:var(--font); font-size:16px; line-height:1.5; letter-spacing:0.03em; }
::-webkit-scrollbar { width:6px; }
::-webkit-scrollbar-track { background:var(--bg2); }
::-webkit-scrollbar-thumb { background:var(--purple); border-radius:3px; }

.header {
    background:var(--orange); height:64px;
    display:flex; align-items:stretch; position:sticky; top:0; z-index:100;
}
.header-corner {
    width:220px; flex-shrink:0; background:var(--orange);
    display:flex; align-items:center; padding:0 20px; gap:10px;
}
.header-gap { width:8px; background:var(--bg); }
.header-bar {
    flex:1; background:var(--orange);
    display:flex; align-items:center; justify-content:space-between; padding:0 20px;
}
.header h1 { font-size:1.4em; font-weight:700; letter-spacing:4px; text-transform:uppercase; color:var(--bg); }
.header-right { display:flex; align-items:center; gap:12px; }
.btn-lcars {
    padding:6px 16px; background:transparent;
    border:2px solid var(--bg); color:var(--bg);
    font-family:var(--font); font-size:0.9em;
    letter-spacing:2px; text-transform:uppercase;
    cursor:pointer; text-decoration:none;
    transition:all .15s;
}
.btn-lcars:hover { background:var(--bg); color:var(--orange); }
.back-link {
    color:var(--bg); font-size:0.9em; letter-spacing:2px;
    text-decoration:none; text-transform:uppercase;
    opacity:0.8;
}
.back-link:hover { opacity:1; }

.lcars-layout { display:flex; min-height:calc(100vh - 64px); }
.lcars-sidebar {
    width:56px; flex-shrink:0; background:var(--bg2);
    display:flex; flex-direction:column;
    border-right:4px solid var(--purple);
}
.lcars-sidebar-block { background:var(--purple); margin:4px 0; flex:1; min-height:40px; }
.lcars-sidebar-block:first-child { border-radius:0 8px 0 0; min-height:80px; background:var(--orange); }
.lcars-sidebar-block:nth-child(2) { background:var(--teal); }
.lcars-sidebar-block:last-child { border-radius:0 0 8px 0; flex:3; }
.lcars-main { flex:1; display:flex; flex-direction:column; overflow:hidden; }

.stats {
    display:grid;
    grid-template-columns:repeat(auto-fill,minmax(150px,1fr));
    gap:4px; padding:8px 12px;
    background:var(--bg2); border-bottom:4px solid var(--purple);
}
.stat-card {
    background:var(--bg3); border:1px solid var(--border);
    border-top:3px solid var(--purple); padding:12px 14px;
    cursor:pointer; transition:all .15s;
}
.stat-card:nth-child(1) { border-top-color:var(--orange); }
.stat-card:nth-child(2) { border-top-color:var(--blue); }
.stat-card:nth-child(3) { border-top-color:var(--purple); }
.stat-card:nth-child(4) { border-top-color:var(--teal); }
.stat-card:nth-child(5) { border-top-color:var(--green); }
.stat-card.active { border-top-color:var(--orange) !important; }
.stat-num { font-size:2em; font-weight:700; line-height:1; margin-bottom:4px; color:var(--orange); }
.stat-card:nth-child(2) .stat-num { color:var(--blue); }
.stat-card:nth-child(3) .stat-num { color:var(--purple); }
.stat-card:nth-child(4) .stat-num { color:var(--teal); }
.stat-card:nth-child(5) .stat-num { color:var(--green); }
.stat-label { font-size:0.85em; color:var(--muted); text-transform:uppercase; letter-spacing:3px; }

.filters {
    background:var(--bg2); border-bottom:2px solid var(--blue);
    padding:10px 16px; display:flex; gap:8px; align-items:flex-end; flex-wrap:wrap;
}
.filter-group { display:flex; flex-direction:column; gap:3px; min-width:100px; flex:1; }
.filter-group label { font-size:0.85em; color:var(--blue); text-transform:uppercase; letter-spacing:3px; font-weight:700; }
.filter-group input, .filter-group select {
    background:var(--bg3); border:1px solid var(--blue);
    color:var(--text); padding:7px 10px;
    font-family:var(--font); font-size:0.92em; letter-spacing:1px;
    width:100%; transition:border-color .15s;
}
.filter-group input:focus, .filter-group select:focus {
    outline:none; border-color:var(--orange);
}
.filter-group select option { background:var(--bg3); }
#f-search { border-color:var(--orange); }
#f-search:focus { background:#1A0800; }
.btn-reset {
    padding:7px 14px; background:transparent;
    border:2px solid var(--muted); color:var(--muted);
    font-family:var(--font); letter-spacing:2px; text-transform:uppercase;
    cursor:pointer; transition:all .15s; white-space:nowrap;
}
.btn-reset:hover { border-color:var(--red); color:var(--red); }
.results-pill {
    padding:6px 12px; background:var(--bg3); border:1px solid var(--blue);
    font-size:0.88em; color:var(--muted); white-space:nowrap; letter-spacing:2px;
}
.results-pill span { color:var(--orange); font-weight:700; }

.charts {
    display:grid; grid-template-columns:1fr 1fr;
    gap:4px; padding:8px 12px;
}
.chart-card {
    background:var(--bg2); border:1px solid var(--border);
    border-left:4px solid var(--purple); padding:14px 16px; overflow:hidden;
}
.chart-card.wide { grid-column:span 2; border-left-color:var(--orange); }
.chart-card:nth-child(2) { border-left-color:var(--teal); }
.chart-card h3 {
    font-size:0.82em; color:var(--purple); text-transform:uppercase;
    letter-spacing:4px; font-weight:700; margin-bottom:12px;
}
.chart-card.wide h3 { color:var(--orange); }
.chart-card:nth-child(2) h3 { color:var(--teal); }
.chart-card canvas { max-height:180px; }
.chart-card.wide canvas { max-height:110px; }

.tag-cloud { display:flex; flex-wrap:wrap; gap:4px; }
.tag-pill {
    padding:2px 10px; font-size:0.88em; cursor:pointer;
    border:1px solid var(--dim); background:var(--bg3); color:var(--muted);
    transition:all .15s; font-family:var(--font); letter-spacing:1px;
}
.tag-pill:hover { border-color:var(--orange); color:var(--orange); }
.tag-pill.active { background:var(--orange); color:var(--bg); border-color:var(--orange); font-weight:700; }

.table-section { padding:0 12px 24px; }
.table-header {
    display:flex; align-items:center; justify-content:space-between;
    margin-bottom:6px; padding:8px 0; border-bottom:2px solid var(--orange);
}
.table-header h3 {
    font-size:0.82em; color:var(--orange); text-transform:uppercase;
    letter-spacing:4px; font-weight:700;
}
.table-wrap { overflow-x:auto; border:1px solid var(--border); }
table { width:100%; border-collapse:collapse; min-width:700px; }
thead tr { background:var(--bg3); }
th {
    padding:10px 14px; text-align:left;
    font-size:0.82em; color:var(--blue); text-transform:uppercase;
    letter-spacing:3px; font-weight:700; cursor:pointer;
    border-bottom:2px solid var(--blue); transition:color .15s;
    white-space:nowrap; user-select:none;
}
th:hover { color:var(--orange); }
th.sort-asc::after { content:' ↑'; color:var(--orange); }
th.sort-desc::after { content:' ↓'; color:var(--orange); }
td { padding:9px 14px; border-bottom:1px solid var(--border); vertical-align:middle; }
tbody tr:hover td { background:var(--bg3); }
.doc-link { color:var(--text); text-decoration:none; font-weight:500; transition:color .15s; }
.doc-link:hover { color:var(--orange); }

.badge {
    display:inline-block; padding:2px 10px; font-size:0.85em;
    font-weight:700; letter-spacing:2px; text-transform:uppercase;
    font-family:var(--font);
}
.badge-report        { background:rgba(153,153,255,.2); color:#9999FF; border:1px solid rgba(153,153,255,.4); }
.badge-proposta      { background:rgba(255,153,0,.2);   color:#FF9900; border:1px solid rgba(255,153,0,.4); }
.badge-contratto     { background:rgba(255,102,102,.2); color:#FF6666; border:1px solid rgba(255,102,102,.4); }
.badge-fattura       { background:rgba(255,204,0,.2);   color:#FFCC00; border:1px solid rgba(255,204,0,.4); }
.badge-verbale       { background:rgba(255,200,80,.2);  color:#FFC850; border:1px solid rgba(255,200,80,.4); }
.badge-presentazione { background:rgba(153,204,255,.2); color:#99CCFF; border:1px solid rgba(153,204,255,.4); }
.badge-manuale       { background:rgba(255,153,0,.15);  color:#FF9900; border:1px solid rgba(255,153,0,.3); }
.badge-altro         { background:rgba(136,136,187,.15);color:#8888BB; border:1px solid rgba(136,136,187,.3); }

.row-tag {
    display:inline-block; padding:1px 7px; margin:1px 2px;
    font-size:0.88em; background:var(--bg4); color:var(--muted);
    cursor:pointer; border:1px solid var(--dim); transition:all .12s;
    font-family:var(--font); letter-spacing:1px;
}
.row-tag:hover { background:rgba(255,153,0,.15); color:var(--orange); border-color:var(--orange); }

.upload-zone {
    border:2px dashed var(--purple); background:var(--bg3);
    padding:10px 20px; cursor:pointer; transition:all .2s;
    display:flex; align-items:center; gap:10px; min-width:200px;
}
.upload-zone:hover, .upload-zone.drag-over { border-color:var(--orange); background:rgba(255,153,0,.05); }
.upload-label { font-size:0.8em; color:var(--purple); letter-spacing:2px; text-transform:uppercase; font-weight:700; }
.upload-status { font-size:0.75em; color:var(--muted); letter-spacing:1px; min-height:16px; }
.upload-status.ok  { color:var(--green); }
.upload-status.err { color:var(--red); }
.upload-status.uploading { color:var(--orange); }
</style>
</head>
<body>

<div class="header">
    <div class="header-corner">
        <img src="/images/logo.png" alt="N" style="height:32px; filter:brightness(0) invert(1);">
        <a href="/intranet" class="back-link">← Intranet</a>
    </div>
    <div class="header-gap"></div>
    <div class="header-bar">
        <h1>Knowledge Base</h1>
        <div class="header-right">
            <span style="color:var(--bg); font-size:0.85em; letter-spacing:2px; opacity:0.7;">
                {{ now()->format('d/m/Y H:i') }}
            </span>
            @if(session('intranet_user')['is_admin'] ?? false)
            <div class="upload-zone" id="upload-zone" onclick="document.getElementById('file-input').click()">
                <span style="font-size:1.2em;">⬆</span>
                <div>
                    <div class="upload-label">Carica documento</div>
                    <div class="upload-status" id="upload-status">Trascina o clicca</div>
                </div>
            </div>
            <input type="file" id="file-input" multiple style="display:none"
                   accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.md,.csv">
            <a href="/intranet/kb/sync" class="btn-lcars">🔄 Sync</a>
            @endif
            <a href="/intranet" class="btn-lcars">← Dashboard</a>
        </div>
    </div>
</div>

<div class="lcars-layout">
    <div class="lcars-sidebar">
        <div class="lcars-sidebar-block"></div>
        <div class="lcars-sidebar-block"></div>
        <div class="lcars-sidebar-block"></div>
        <div class="lcars-sidebar-block"></div>
    </div>

    <div class="lcars-main">

        @if(session('success'))
        <div style="background:#0A1A0A; border-bottom:2px solid var(--green); padding:10px 20px; color:var(--green); font-size:0.88em; letter-spacing:2px;">
            ✓ {{ session('success') }}
        </div>
        @endif

        @php
        $totalDocs = $documents->total();
        $tipiCount = $tipi->count();
        $tagsCount = $allTags->count();
        $oggi = $documents->getCollection()->where('data_catalogazione', today())->count();
        @endphp
        <div class="stats">
            <div class="stat-card" onclick="resetFilters()">
                <div class="stat-num">{{ $totalDocs }}</div>
                <div class="stat-label">Documenti</div>
            </div>
            <div class="stat-card">
                <div class="stat-num">{{ $tipiCount }}</div>
                <div class="stat-label">Tipologie</div>
            </div>
            <div class="stat-card">
                <div class="stat-num">{{ $tagsCount }}</div>
                <div class="stat-label">Tag unici</div>
            </div>
            <div class="stat-card">
                <div class="stat-num">{{ $stats['inbox'] }}</div>
                <div class="stat-label">In Inbox</div>
            </div>
            <div class="stat-card">
                <div class="stat-num">{{ $stats['metadata'] }}</div>
                <div class="stat-label">Catalogati</div>
            </div>
        </div>

        <div class="filters">
            <div class="filter-group" style="flex:2;">
                <label>🔍 Cerca</label>
                <input type="text" id="f-search" placeholder="Titolo, sommario, tag..."
                       value="{{ $search }}" oninput="filterDebounce()">
            </div>
            <div class="filter-group">
                <label>Tipo</label>
                <select id="f-tipo" onchange="applyFilters()">
                    <option value="">Tutti</option>
                    @foreach($tipi as $t)
                    <option value="{{ $t->tipo_documento }}" {{ $tipo === $t->tipo_documento ? 'selected' : '' }}>
                        {{ $t->tipo_documento }} ({{ $t->count }})
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <label>Lingua</label>
                <select id="f-lingua" onchange="applyFilters()">
                    <option value="">Tutte</option>
                    <option value="it" {{ request('lingua') === 'it' ? 'selected' : '' }}>🇮🇹 Italiano</option>
                    <option value="en" {{ request('lingua') === 'en' ? 'selected' : '' }}>🇬🇧 English</option>
                </select>
            </div>
            <button class="btn-reset" onclick="resetFilters()">✕ Reset</button>
            <div class="results-pill"><span id="results-count">{{ $totalDocs }}</span> risultati</div>
        </div>

        @if($allTags->count() > 0)
        <div style="background:var(--bg2); border-bottom:1px solid var(--border); padding:10px 16px;">
            <div class="tag-cloud">
                @foreach($allTags as $tagName => $count)
                <span class="tag-pill {{ $tag === $tagName ? 'active' : '' }}"
                      onclick="filterByTag('{{ $tagName }}')">
                    #{{ $tagName }} <span style="opacity:.5;">{{ $count }}</span>
                </span>
                @endforeach
            </div>
        </div>
        @endif

        @if($tipi->count() > 0)
        <div class="charts">
            <div class="chart-card">
                <h3>Distribuzione per tipo</h3>
                <canvas id="chart-tipi"></canvas>
            </div>
            <div class="chart-card">
                <h3>Tag più frequenti</h3>
                <canvas id="chart-tags"></canvas>
            </div>
            <div class="chart-card wide">
                <h3>Timeline catalogazione</h3>
                <canvas id="chart-timeline"></canvas>
            </div>
        </div>
        @endif

        <div class="table-section">
            <div class="table-header">
                <h3>Archivio documenti</h3>
            </div>
            <div class="table-wrap">
                <table id="docs-table">
                    <thead>
                        <tr>
                            <th onclick="sortTable(0)">Titolo</th>
                            <th onclick="sortTable(1)">Tipo</th>
                            <th onclick="sortTable(2)">Lingua</th>
                            <th onclick="sortTable(3)">Tag</th>
                            <th onclick="sortTable(4)">Data</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="docs-tbody">
                        @forelse($documents as $doc)
                        <tr data-title="{{ strtolower($doc->title ?? $doc->file_stem) }}"
                            data-tipo="{{ $doc->tipo_documento }}"
                            data-tags="{{ implode(' ', $doc->tags ?? []) }}"
                            data-sommario="{{ strtolower($doc->sommario ?? '') }}">
                            <td>
                                <a href="/intranet/kb/{{ $doc->id }}" class="doc-link">
                                    {{ $doc->title ?? $doc->file_stem }}
                                </a>
                                @if($doc->sommario)
                                <div style="font-size:0.8em; color:var(--muted); margin-top:3px; letter-spacing:0.5px;">
                                    {{ \Illuminate\Support\Str::limit($doc->sommario, 80) }}
                                </div>
                                @endif
                            </td>
                            <td>
                                @if($doc->tipo_documento)
                                <span class="badge badge-{{ str_replace([' ','/'], '_', $doc->tipo_documento) }}">
                                    {{ $doc->tipo_documento }}
                                </span>
                                @else
                                <span style="color:var(--dim);">—</span>
                                @endif
                            </td>
                            <td style="color:var(--muted);">{{ $doc->lingua ?? 'it' }}</td>
                            <td>
                                @foreach(($doc->tags ?? []) as $t)
                                <span class="row-tag" onclick="filterByTag('{{ $t }}')">{{ $t }}</span>
                                @endforeach
                            </td>
                            <td style="color:var(--muted); white-space:nowrap;">
                                {{ $doc->data_catalogazione?->format('d/m/Y') ?? '—' }}
                            </td>
                            <td>
                                @if($doc->file_path && file_exists($doc->file_path))
                                <a href="/intranet/kb/{{ $doc->id }}/download"
                                   style="color:var(--teal); font-size:0.85em; letter-spacing:1px; text-decoration:none;">
                                    ⬇ Scarica
                                </a>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" style="text-align:center; padding:40px; color:var(--muted); letter-spacing:2px;">
                                NESSUN DOCUMENTO — Carica file nella inbox
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div style="margin-top:12px; color:var(--muted); font-size:0.85em;">
                {{ $documents->appends(request()->query())->links() }}
            </div>
        </div>

    </div>
</div>

<script>
const tipiData = @json($tipi->pluck('count', 'tipo_documento'));
const tagsData = @json($allTags->take(10));
const timelineData = @json(
    \App\Models\KbDocument::selectRaw("DATE(data_catalogazione) as date, COUNT(*) as count")
        ->whereNotNull('data_catalogazione')
        ->groupBy('date')
        ->orderBy('date')
        ->get()
        ->pluck('count', 'date')
);

const COLORS = ['#FF9900','#CC66CC','#9999FF','#99CCFF','#99FF99','#FF6666','#FFCC00','#FF66CC'];

if (Object.keys(tipiData).length > 0) {
    new Chart(document.getElementById('chart-tipi'), {
        type: 'doughnut',
        data: {
            labels: Object.keys(tipiData),
            datasets: [{ data: Object.values(tipiData), backgroundColor: COLORS, borderWidth: 0 }]
        },
        options: {
            plugins: { legend: { labels: { color: '#8888BB', font: { family: 'Antonio' }, boxWidth: 12 } } },
            cutout: '60%'
        }
    });
}

if (Object.keys(tagsData).length > 0) {
    new Chart(document.getElementById('chart-tags'), {
        type: 'bar',
        data: {
            labels: Object.keys(tagsData),
            datasets: [{ data: Object.values(tagsData), backgroundColor: '#CC66CC', borderWidth: 0 }]
        },
        options: {
            indexAxis: 'y',
            plugins: { legend: { display: false } },
            scales: {
                x: { ticks: { color: '#8888BB' }, grid: { color: '#1A1A30' } },
                y: { ticks: { color: '#EEEEFF', font: { family: 'Antonio' } }, grid: { display: false } }
            }
        }
    });
}

if (Object.keys(timelineData).length > 0) {
    new Chart(document.getElementById('chart-timeline'), {
        type: 'bar',
        data: {
            labels: Object.keys(timelineData),
            datasets: [{ data: Object.values(timelineData), backgroundColor: '#FF9900', borderWidth: 0 }]
        },
        options: {
            plugins: { legend: { display: false } },
            scales: {
                x: { ticks: { color: '#8888BB', font: { family: 'Antonio' } }, grid: { color: '#1A1A30' } },
                y: { ticks: { color: '#8888BB' }, grid: { color: '#1A1A30' } }
            }
        }
    });
}

let searchTimer = null;
let activeTag = '{{ $tag ?? "" }}';

function filterDebounce() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 300);
}

function applyFilters() {
    const q = document.getElementById('f-search').value.toLowerCase();
    const tipo = document.getElementById('f-tipo').value;
    const lingua = document.getElementById('f-lingua').value;
    const rows = document.querySelectorAll('#docs-tbody tr[data-title]');
    let visible = 0;

    rows.forEach(row => {
        const title = row.dataset.title || '';
        const rowTipo = row.dataset.tipo || '';
        const tags = row.dataset.tags || '';
        const sommario = row.dataset.sommario || '';
        const lang = row.querySelector('td:nth-child(3)')?.textContent?.trim() || '';

        const matchQ = !q || title.includes(q) || tags.includes(q) || sommario.includes(q);
        const matchTipo = !tipo || rowTipo === tipo;
        const matchLang = !lingua || lang === lingua;
        const matchTag = !activeTag || tags.includes(activeTag);

        const show = matchQ && matchTipo && matchLang && matchTag;
        row.style.display = show ? '' : 'none';
        if (show) visible++;
    });

    document.getElementById('results-count').textContent = visible;
}

function filterByTag(tag) {
    if (activeTag === tag) {
        activeTag = '';
    } else {
        activeTag = tag;
    }
    document.querySelectorAll('.tag-pill').forEach(p => {
        p.classList.toggle('active', p.textContent.trim().startsWith('#' + activeTag) && activeTag);
    });
    applyFilters();
}

function resetFilters() {
    document.getElementById('f-search').value = '';
    document.getElementById('f-tipo').value = '';
    document.getElementById('f-lingua').value = '';
    activeTag = '';
    document.querySelectorAll('.tag-pill').forEach(p => p.classList.remove('active'));
    applyFilters();
}

let sortCol = -1, sortDir = 1;
function sortTable(col) {
    const tbody = document.getElementById('docs-tbody');
    const rows = Array.from(tbody.querySelectorAll('tr[data-title]'));
    const ths = document.querySelectorAll('th');

    if (sortCol === col) sortDir *= -1; else { sortCol = col; sortDir = 1; }
    ths.forEach((th, i) => {
        th.classList.remove('sort-asc','sort-desc');
        if (i === col) th.classList.add(sortDir === 1 ? 'sort-asc' : 'sort-desc');
    });

    rows.sort((a, b) => {
        const av = a.cells[col]?.textContent?.trim() || '';
        const bv = b.cells[col]?.textContent?.trim() || '';
        return av.localeCompare(bv, 'it') * sortDir;
    });
    rows.forEach(r => tbody.appendChild(r));
}

const zone = document.getElementById('upload-zone');
const input = document.getElementById('file-input');
const statusEl = document.getElementById('upload-status');

if (zone) {
    zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
    zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
    zone.addEventListener('drop', e => {
        e.preventDefault();
        zone.classList.remove('drag-over');
        uploadFiles(e.dataTransfer.files);
    });
    input?.addEventListener('change', () => uploadFiles(input.files));
}

async function uploadFiles(files) {
    if (!files || !files.length) return;
    statusEl.textContent = `Caricando ${files.length} file...`;
    statusEl.className = 'upload-status uploading';

    const fd = new FormData();
    Array.from(files).forEach(f => fd.append('files[]', f));
    fd.append('_token', '{{ csrf_token() }}');

    try {
        const res = await fetch('/intranet/kb/upload', { method: 'POST', body: fd });
        const data = await res.json();
        statusEl.textContent = `✓ ${data.count} file caricati`;
        statusEl.className = 'upload-status ok';
        setTimeout(() => location.reload(), 2000);
    } catch(e) {
        statusEl.textContent = 'Errore upload';
        statusEl.className = 'upload-status err';
    }
}
</script>

</body>
</html>
