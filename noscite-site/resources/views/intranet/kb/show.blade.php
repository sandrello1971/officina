<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $document->title ?? $document->file_stem }} — Knowledge Base</title>
<link rel="icon" type="image/png" href="/images/logo.png">
<style>
@import url('https://fonts.googleapis.com/css2?family=Antonio:wght@400;700&display=swap');
:root {
    --bg:#000008; --bg2:#08080F; --bg3:#0D0D1A; --bg4:#111122;
    --border:#1A1A30; --text:#EEEEFF; --muted:#8888BB; --dim:#333355;
    --orange:#FF9900; --purple:#CC66CC; --blue:#9999FF; --teal:#99CCFF;
    --green:#99FF99; --red:#FF6666; --yellow:#FFCC00;
    --font:'Antonio','Futura','Century Gothic',sans-serif;
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
.back-link { color:var(--bg); font-size:0.9em; letter-spacing:2px; text-decoration:none; text-transform:uppercase; opacity:0.8; }
.back-link:hover { opacity:1; }
.btn-lcars { padding:6px 16px; background:transparent; border:2px solid var(--bg); color:var(--bg); font-family:var(--font); font-size:0.9em; letter-spacing:2px; text-transform:uppercase; cursor:pointer; text-decoration:none; transition:all .15s; }
.btn-lcars:hover { background:var(--bg); color:var(--orange); }
.btn-danger { padding:6px 16px; background:transparent; border:2px solid var(--red); color:var(--red); font-family:var(--font); font-size:0.9em; letter-spacing:2px; text-transform:uppercase; cursor:pointer; }
.btn-danger:hover { background:var(--red); color:var(--bg); }

.lcars-layout { display:flex; min-height:calc(100vh - 64px); }
.lcars-sidebar { width:56px; flex-shrink:0; background:var(--bg2); display:flex; flex-direction:column; border-right:4px solid var(--purple); }
.lcars-sidebar-block { background:var(--purple); margin:4px 0; flex:1; min-height:40px; }
.lcars-sidebar-block:first-child { border-radius:0 8px 0 0; min-height:80px; background:var(--orange); }
.lcars-sidebar-block:nth-child(2) { background:var(--teal); }
.lcars-sidebar-block:last-child { border-radius:0 0 8px 0; flex:3; }
.lcars-main { flex:1; padding:20px 24px; overflow-y:auto; }

.panel { background:var(--bg2); border:1px solid var(--border); border-left:4px solid var(--purple); padding:20px 24px; margin-bottom:16px; }
.panel.orange { border-left-color:var(--orange); }
.panel.teal { border-left-color:var(--teal); }
.panel.blue { border-left-color:var(--blue); }
.panel.green { border-left-color:var(--green); }
.panel.red { border-left-color:var(--red); }
.panel h2 { font-size:0.8em; color:var(--purple); text-transform:uppercase; letter-spacing:4px; font-weight:700; margin-bottom:12px; }
.panel.orange h2 { color:var(--orange); }
.panel.teal h2 { color:var(--teal); }
.panel.blue h2 { color:var(--blue); }
.panel.green h2 { color:var(--green); }
.panel.red h2 { color:var(--red); }

.doc-title { font-size:1.6em; font-weight:700; letter-spacing:3px; text-transform:uppercase; color:var(--text); margin-bottom:8px; }
.doc-stem { font-size:0.85em; color:var(--muted); letter-spacing:2px; font-family:monospace; }

.meta-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:16px; margin-top:16px; }
.meta-item { background:var(--bg3); border-left:2px solid var(--dim); padding:10px 14px; }
.meta-label { font-size:0.72em; color:var(--muted); text-transform:uppercase; letter-spacing:3px; font-weight:700; margin-bottom:4px; }
.meta-value { font-size:1em; color:var(--text); letter-spacing:1px; }
.meta-value.sentiment-positivo { color:var(--green); }
.meta-value.sentiment-negativo { color:var(--red); }
.meta-value.sentiment-neutro { color:var(--muted); }
.meta-value.complessita-alta { color:var(--orange); }
.meta-value.complessita-media { color:var(--yellow); }
.meta-value.complessita-bassa { color:var(--green); }

.sommario { font-size:1em; color:var(--text); line-height:1.7; letter-spacing:0.5px; font-style:italic; }

.chip-list { display:flex; flex-wrap:wrap; gap:6px; }
.chip {
    display:inline-block; padding:3px 12px; font-size:0.88em;
    background:var(--bg3); color:var(--text); border:1px solid var(--dim);
    font-family:var(--font); letter-spacing:1px; text-decoration:none;
    transition:all .15s;
}
.chip:hover { border-color:var(--orange); color:var(--orange); }
.chip.tag { border-color:var(--purple); color:var(--purple); }
.chip.tag:hover { background:var(--purple); color:var(--bg); }
.chip.keyword { border-color:var(--blue); color:var(--blue); }
.chip.person { border-color:var(--teal); color:var(--teal); }
.chip.place { border-color:var(--green); color:var(--green); }
.chip.topic { border-color:var(--orange); color:var(--orange); }

.markdown-body { color:var(--text); font-size:0.95em; line-height:1.7; letter-spacing:0.3px; }
.markdown-body h1, .markdown-body h2, .markdown-body h3, .markdown-body h4 {
    color:var(--orange); font-weight:700; letter-spacing:2px; text-transform:uppercase;
    margin:1.5em 0 0.5em; padding-bottom:4px; border-bottom:1px solid var(--dim);
}
.markdown-body h1 { font-size:1.3em; }
.markdown-body h2 { font-size:1.1em; color:var(--teal); }
.markdown-body h3 { font-size:1em; color:var(--purple); border-bottom:none; }
.markdown-body h4 { font-size:0.95em; color:var(--blue); border-bottom:none; }
.markdown-body p { margin:0.75em 0; }
.markdown-body strong { color:var(--orange); font-weight:700; }
.markdown-body em { color:var(--muted); font-style:italic; }
.markdown-body code { background:var(--bg3); color:var(--teal); padding:2px 8px; font-family:monospace; font-size:0.9em; border:1px solid var(--dim); }
.markdown-body pre { background:var(--bg3); padding:12px; overflow-x:auto; border-left:3px solid var(--purple); margin:1em 0; }
.markdown-body pre code { background:transparent; border:none; padding:0; color:var(--text); }
.markdown-body blockquote { border-left:3px solid var(--teal); padding:8px 16px; color:var(--muted); margin:1em 0; background:var(--bg3); font-style:italic; }
.markdown-body ul, .markdown-body ol { margin:0.75em 0 0.75em 1.5em; }
.markdown-body li { margin:0.3em 0; }
.markdown-body hr { border:none; border-top:1px solid var(--dim); margin:1.5em 0; }
.markdown-body table { border-collapse:collapse; width:100%; margin:1em 0; font-size:0.9em; }
.markdown-body th { background:var(--bg3); color:var(--blue); text-align:left; padding:8px 12px; border-bottom:2px solid var(--blue); letter-spacing:1px; text-transform:uppercase; font-size:0.85em; }
.markdown-body td { padding:8px 12px; border-bottom:1px solid var(--border); }
.markdown-body a { color:var(--teal); text-decoration:none; border-bottom:1px dotted var(--teal); }
.markdown-body a:hover { color:var(--orange); border-bottom-color:var(--orange); }
</style>
</head>
<body>

<div class="header">
    <div class="header-corner">
        <img src="/images/logo.png" alt="N" style="height:32px; filter:brightness(0) invert(1);">
        <a href="/intranet/kb" class="back-link">← KB</a>
    </div>
    <div class="header-gap"></div>
    <div class="header-bar">
        <h1>{{ \Illuminate\Support\Str::limit($document->title ?? $document->file_stem, 60) }}</h1>
        <div style="display:flex; gap:12px; align-items:center;">
            <a href="/intranet/kb/download/{{ $document->file_stem }}"
               style="display:inline-block; padding:10px 24px;
                      background:transparent; border:2px solid var(--teal);
                      color:var(--teal); font-family:var(--font);
                      font-size:0.875rem; font-weight:700;
                      letter-spacing:2px; text-transform:uppercase;
                      text-decoration:none; transition:all .15s;"
               onmouseover="this.style.background='rgba(153,204,255,.15)'"
               onmouseout="this.style.background='transparent'">
                ⬇ Scarica originale
            </a>
            @if(session('intranet_user')['is_admin'] ?? false)
            <form method="POST" action="/intranet/kb/{{ $document->id }}"
                  onsubmit="return confirm('Eliminare questo documento?')"
                  style="display:inline;">
                @csrf @method('DELETE')
                <button type="submit" class="btn-danger">✕ Elimina</button>
            </form>
            @endif
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

        {{-- HEADER DOCUMENTO --}}
        <div class="panel orange">
            <div class="doc-title">{{ $document->title ?? $document->file_stem }}</div>
            <div class="doc-stem">{{ $document->file_stem }}</div>

            <div class="meta-grid">
                @if($document->tipo_documento)
                <div class="meta-item">
                    <div class="meta-label">Tipo documento</div>
                    <div class="meta-value">{{ $document->tipo_documento }}</div>
                </div>
                @endif

                @if($document->lingua)
                <div class="meta-item">
                    <div class="meta-label">Lingua</div>
                    <div class="meta-value">{{ strtoupper($document->lingua) }}</div>
                </div>
                @endif

                @if($document->organizzazioni)
                <div class="meta-item">
                    <div class="meta-label">Organizzazione</div>
                    <div class="meta-value">{{ $document->organizzazioni }}</div>
                </div>
                @endif

                @if($document->sentiment)
                <div class="meta-item">
                    <div class="meta-label">Sentiment</div>
                    <div class="meta-value sentiment-{{ $document->sentiment }}">
                        @switch($document->sentiment)
                            @case('positivo') 🟢 @break
                            @case('negativo') 🔴 @break
                            @default ⚪
                        @endswitch
                        {{ ucfirst($document->sentiment) }}
                    </div>
                </div>
                @endif

                @if($document->complessita)
                <div class="meta-item">
                    <div class="meta-label">Complessità</div>
                    <div class="meta-value complessita-{{ strtolower($document->complessita) }}">
                        {{ ucfirst($document->complessita) }}
                    </div>
                </div>
                @endif

                @if($document->data_documento)
                <div class="meta-item">
                    <div class="meta-label">Data documento</div>
                    <div class="meta-value">{{ $document->data_documento->format('d/m/Y') }}</div>
                </div>
                @endif

                @if($document->data_catalogazione)
                <div class="meta-item">
                    <div class="meta-label">Data catalogazione</div>
                    <div class="meta-value">{{ $document->data_catalogazione->format('d/m/Y') }}</div>
                </div>
                @endif

                @if($document->file_type)
                <div class="meta-item">
                    <div class="meta-label">Formato</div>
                    <div class="meta-value">{{ strtoupper($document->file_type) }}</div>
                </div>
                @endif
            </div>
        </div>

        {{-- SOMMARIO --}}
        @if($document->sommario)
        <div class="panel teal">
            <h2>📝 Sommario</h2>
            <div class="sommario">{{ $document->sommario }}</div>
        </div>
        @endif

        {{-- TRASCRIZIONE / CORPO MARKDOWN --}}
        @if($document->body_md)
        <div class="panel blue">
            <h2>📄 Trascrizione / Analisi completa</h2>
            <div class="markdown-body">
                {!! \Illuminate\Support\Str::markdown($document->body_md) !!}
            </div>
        </div>
        @endif

        {{-- TAG --}}
        @if(!empty($document->tags))
        <div class="panel">
            <h2>🏷️ Tag</h2>
            <div class="chip-list">
                @foreach($document->tags as $tag)
                <a href="/intranet/kb?tag={{ $tag }}" class="chip tag">#{{ $tag }}</a>
                @endforeach
            </div>
        </div>
        @endif

        {{-- ARGOMENTI --}}
        @if(!empty($document->argomenti))
        <div class="panel orange">
            <h2>🔗 Argomenti</h2>
            <div class="chip-list">
                @foreach($document->argomenti as $arg)
                <span class="chip topic">{{ $arg }}</span>
                @endforeach
            </div>
        </div>
        @endif

        {{-- PAROLE CHIAVE --}}
        @if(!empty($document->parole_chiave))
        <div class="panel blue">
            <h2>🔑 Parole chiave</h2>
            <div class="chip-list">
                @foreach($document->parole_chiave as $kw)
                <span class="chip keyword">{{ $kw }}</span>
                @endforeach
            </div>
        </div>
        @endif

        {{-- PERSONE --}}
        @if(!empty($document->persone))
        <div class="panel teal">
            <h2>👤 Persone</h2>
            <div class="chip-list">
                @foreach($document->persone as $p)
                <span class="chip person">{{ $p }}</span>
                @endforeach
            </div>
        </div>
        @endif

        {{-- LUOGHI --}}
        @if(!empty($document->luoghi))
        <div class="panel green">
            <h2>📍 Luoghi</h2>
            <div class="chip-list">
                @foreach($document->luoghi as $l)
                <span class="chip place">{{ $l }}</span>
                @endforeach
            </div>
        </div>
        @endif

    </div>
</div>

</body>
</html>
