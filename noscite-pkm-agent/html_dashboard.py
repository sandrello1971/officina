"""
Genera dashboard.html nella cartella _metadata.
Legge tutti i file .md con frontmatter YAML e produce
una dashboard interattiva con Chart.js.
"""

import json
import logging
from datetime import datetime
from pathlib import Path

from config import Config

log = logging.getLogger(__name__)


def _atomic_write(path: "Path", content: str) -> None:
    """Scrive il file tramite rename atomico per forzare il rilevamento da parte di iCloud."""
    tmp = path.with_suffix(".tmp")
    tmp.write_text(content, encoding="utf-8")
    if path.exists():
        path.unlink()
    tmp.rename(path)


def update():
    """Rigenera dashboard.html leggendo tutti i .md in _metadata."""
    try:
        docs = _load_all_metadata()
        html = _render_html(docs)
        out = Config.METADATA_DIR / "dashboard.html"
        _atomic_write(out, html)
        # Copia .htm per compatibilità Safari su iPad (iOS non apre .html con Safari)
        _atomic_write(Config.METADATA_DIR / "dashboard.htm", html)
        log.info(f"  ✓ dashboard.html aggiornata ({len(docs)} documenti)")
    except Exception as e:
        log.warning(f"  ⚠ Errore generazione dashboard.html: {e}")


# ── Lettura frontmatter ──────────────────────────────────────────────

def _load_all_metadata() -> list[dict]:
    docs = []
    for md_file in sorted(Config.METADATA_DIR.glob("*.md")):
        if md_file.stem in ("_INDEX", "DASHBOARD"):
            continue
        try:
            doc = _parse_frontmatter(md_file)
            if doc:
                docs.append(doc)
        except Exception:
            pass
    return docs


def _parse_frontmatter(path: Path) -> dict:
    text = path.read_text(encoding="utf-8", errors="replace")
    if not text.startswith("---"):
        return {}
    end = text.find("---", 3)
    if end == -1:
        return {}
    fm = text[3:end]
    doc = {"file_stem": path.stem}
    for line in fm.splitlines():
        line = line.strip()
        if not line or line.startswith("#"):
            continue
        if ":" not in line:
            continue
        key, _, val = line.partition(":")
        key = key.strip()
        val = val.strip().strip('"').strip("'")
        if val:
            doc[key] = val
    for field in ("tags", "argomenti"):
        doc[field] = _parse_yaml_list(fm, field)
    return doc


def _parse_yaml_list(fm: str, field: str) -> list:
    items = []
    in_field = False
    for line in fm.splitlines():
        stripped = line.strip()
        if stripped == field + ":":
            in_field = True
            continue
        if in_field:
            if stripped.startswith("- "):
                items.append(stripped[2:].strip().strip('"').strip("'"))
            elif stripped and not stripped.startswith("-"):
                break
    return items


# ── Rendering HTML ───────────────────────────────────────────────────

def _render_html(docs: list[dict]) -> str:
    import os
    docs_json = json.dumps(docs, ensure_ascii=False)
    now = datetime.now().strftime("%d/%m/%Y %H:%M")
    vault_name = Config.METADATA_DIR.parent.name
    env = os.environ.get("PKM_ENV", "").lower()
    env_badge = ' · <span style="color:var(--orange);letter-spacing:3px;">⬡ DEV</span>' if env == "develop" else ""
    html = _HTML_TEMPLATE.replace("__DOCS_JSON__", docs_json)
    html = html.replace("__NOW__", now)
    html = html.replace("__VAULT_NAME__", vault_name)
    html = html.replace("__ENV_BADGE__", env_badge)
    return html


_HTML_TEMPLATE = """\
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">
<title>PKM Dashboard</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<style>
@import url('https://fonts.googleapis.com/css2?family=Antonio:wght@400;700&display=swap');
:root{
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
  --lcars-font: 'Antonio','Futura','Century Gothic',sans-serif;
}
*{box-sizing:border-box;margin:0;padding:0;}
html,body{height:100%;overflow-x:hidden;max-width:100%;}
body{background:var(--bg);color:var(--text);font-family:var(--lcars-font);font-size:17px;line-height:1.5;letter-spacing:0.03em;}
::-webkit-scrollbar{width:6px;height:6px;}
::-webkit-scrollbar-track{background:var(--bg2);}
::-webkit-scrollbar-thumb{background:var(--purple);border-radius:3px;}
::-webkit-scrollbar-thumb:hover{background:var(--orange);}

/* ── HEADER LCARS ── */
.header{
  background:var(--orange);
  padding:0;
  display:flex;
  align-items:stretch;
  height:64px;
  position:sticky;top:0;z-index:100;
}
.header-corner{
  width:200px;flex-shrink:0;
  background:var(--orange);
  border-radius:0 0 0 0;
  display:flex;align-items:center;
  padding:0 20px;
  gap:10px;
}
.header-gap{width:8px;background:var(--bg);}
.header-bar{
  flex:1;
  background:var(--orange);
  display:flex;align-items:center;justify-content:space-between;
  padding:0 20px;
}
.header h1{
  font-family:var(--lcars-font);
  font-size:1.5em;font-weight:700;
  letter-spacing:4px;text-transform:uppercase;
  color:var(--bg);
}
.logo{font-size:20px;}
.updated{font-size:1em;color:var(--bg);letter-spacing:2px;opacity:0.7;}
.header-right{display:flex;align-items:center;gap:12px;}

/* ── SIDEBAR LCARS ── */
.lcars-layout{display:flex;min-height:calc(100vh - 64px);}
.lcars-sidebar{
  width:60px;flex-shrink:0;
  background:var(--bg2);
  display:flex;flex-direction:column;
  border-right:4px solid var(--purple);
}
.lcars-sidebar-block{
  background:var(--purple);
  margin:4px 0;
  flex:1;
  min-height:40px;
  border-radius:0 0 0 0;
}
.lcars-sidebar-block:first-child{border-radius:0 8px 0 0;min-height:80px;}
.lcars-sidebar-block:last-child{border-radius:0 0 8px 0;flex:3;}
.lcars-sidebar-block.orange{background:var(--orange);}
.lcars-sidebar-block.teal{background:var(--teal);}
.lcars-main{flex:1;display:flex;flex-direction:column;}

/* ── OFFLINE BANNER ── */
.offline-banner{
  display:none;
  background:var(--bg3);
  border-bottom:2px solid var(--yellow);
  padding:8px 20px;
  align-items:center;gap:10px;
  font-size:13px;letter-spacing:2px;
  color:var(--muted);text-transform:uppercase;
}
.offline-banner span{color:var(--yellow);}
.server-only{}
.activity-banner{
  display:none;
  background:#0A0A18;
  border-bottom:2px solid var(--orange);
  padding:10px 20px;
  align-items:center;gap:14px;
  font-family:var(--lcars-font);
}
.activity-banner.visible{display:flex;}
.activity-spinner{
  width:16px;height:16px;flex-shrink:0;
  border:2px solid var(--dim);
  border-top-color:var(--orange);
  border-radius:50%;
  animation:spin .8s linear infinite;
}
@keyframes spin{to{transform:rotate(360deg);}}
.activity-filename{color:var(--orange);font-size:0.9em;font-weight:700;letter-spacing:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:300px;}
.activity-step{color:var(--muted);font-size:0.82em;letter-spacing:2px;text-transform:uppercase;}
.activity-desc{color:var(--text);font-size:0.88em;letter-spacing:1px;flex:1;}
.activity-progress{
  display:flex;gap:4px;align-items:center;flex-shrink:0;
}
.activity-dot{width:8px;height:8px;border-radius:50%;background:var(--dim);}
.activity-dot.done{background:var(--orange);}
.activity-dot.active{background:var(--orange);animation:pulse 1s infinite;}
@keyframes pulse{0%,100%{opacity:1;}50%{opacity:0.3;}}

/* ── STATS LCARS ── */
.stats{
  display:grid;
  grid-template-columns:repeat(auto-fill,minmax(160px,1fr));
  gap:4px;
  padding:8px 12px;
  background:var(--bg2);
  border-bottom:4px solid var(--purple);
  align-items:stretch;
}
.stat-card{
  background:var(--bg3);
  border:1px solid var(--border);
  border-top:3px solid var(--purple);
  padding:12px 14px;
  position:relative;
  display:flex;
  flex-direction:column;
  justify-content:center;
}
.stat-card:nth-child(1){border-top-color:var(--orange);}
.stat-card:nth-child(2){border-top-color:var(--blue);}
.stat-card:nth-child(3){border-top-color:var(--purple);}
.stat-card:nth-child(4){border-top-color:var(--teal);}
.stat-card:nth-child(5){border-top-color:var(--green);}
.stat-card:nth-child(6){border-top-color:var(--red);}
.stat-num{font-size:2em;font-weight:700;line-height:1;margin-bottom:4px;color:var(--orange);}
.stat-card:nth-child(1) .stat-num{color:var(--orange);}
.stat-card:nth-child(2) .stat-num{color:var(--blue);}
.stat-card.filtered-active{border-top-color:var(--orange) !important;}
.stat-card.filtered-active .stat-num{
  color:var(--orange) !important;
  animation:blink-filtered 1.2s ease-in-out infinite;
}
@keyframes blink-filtered{
  0%,100%{opacity:1;}
  50%{opacity:0.25;}
}
.stat-card:nth-child(3) .stat-num{color:var(--purple);}
.stat-card:nth-child(4) .stat-num{color:var(--teal);}
.stat-card:nth-child(5) .stat-num{color:var(--green);}
.stat-card:nth-child(6) .stat-num{color:var(--red);}
.stat-label{font-size:0.92em;color:var(--muted);text-transform:uppercase;letter-spacing:3px;}

/* ── FILTERS LCARS ── */
.filters{
  background:var(--bg2);
  border-bottom:2px solid var(--blue);
  padding:10px 16px;
  display:flex;gap:8px;align-items:stretch;flex-wrap:wrap;
}
.filter-group{display:flex;flex-direction:column;gap:3px;min-width:90px;flex:1;}
.filter-group label{
  font-size:0.92em;color:var(--blue);
  text-transform:uppercase;letter-spacing:3px;font-weight:700;
  white-space:nowrap;
}
.filter-group input,.filter-group select{
  background:var(--bg3);
  border:1px solid var(--blue);
  color:var(--text);
  border-radius:0;
  padding:6px 10px;
  font-size:0.92em;
  font-family:var(--lcars-font);
  letter-spacing:1px;
  width:100%;
  flex:1;
  transition:border-color .15s;
}
.filter-group input:focus,.filter-group select:focus{
  outline:none;border-color:var(--orange);
  box-shadow:0 0 0 1px var(--orange);
}
.filter-group select option{background:var(--bg3);}
#f-search::placeholder{color:rgba(255,180,80,0.5);}
#f-search:focus{background:#4A2800 !important;box-shadow:0 0 0 2px var(--orange) !important;}
.filter-group:has(#f-search) label{color:var(--orange);}
.filter-actions{
  display:flex;gap:6px;align-items:stretch;
  flex-wrap:wrap;
  flex-basis:100%;
  margin-top:4px;
}
.collapsible{cursor:pointer;display:flex;align-items:center;justify-content:space-between;}
.collapsible:hover{color:var(--text);}
.collapse-arrow{font-size:1em;transition:transform .2s;margin-left:8px;}
.tag-cloud.collapsed{display:none;}

/* ── UPLOAD AREA ── */
.upload-area{
  display:none;
  flex-direction:column;
  align-items:center;justify-content:center;
  gap:6px;
  border:2px dashed var(--purple);
  background:var(--bg3);
  padding:10px 20px;
  cursor:default;
  transition:all .2s;
  min-width:180px;
  text-align:center;
  flex-shrink:0;
}
.upload-area{display:none;}
.upload-area.online{display:flex;}
.upload-area.drag-over{
  border-color:var(--orange);
  background:rgba(255,153,0,0.08);
}
.upload-icon{font-size:20px;line-height:1;}
.upload-label{
  font-size:0.75em;color:var(--purple);
  letter-spacing:2px;text-transform:uppercase;
  font-family:var(--lcars-font);font-weight:700;
}
.upload-status{
  font-size:0.7em;color:var(--muted);
  letter-spacing:1px;min-height:16px;
  font-family:var(--lcars-font);
}
.upload-status.ok{color:var(--green);}
.upload-status.err{color:var(--red);}
.upload-status.uploading{color:var(--orange);}

.btn-refresh{
  padding:6px 14px;
  height:100%;box-sizing:border-box;
  background:transparent;
  border:2px solid var(--blue);
  color:var(--blue);
  border-radius:0;cursor:pointer;
  font-size:0.98em;font-family:var(--lcars-font);
  letter-spacing:2px;text-transform:uppercase;
  white-space:nowrap;transition:all .15s;
}
.btn-refresh:hover{background:var(--blue);color:var(--bg);}
.btn-reprocess-all{
  padding:6px 14px;
  height:100%;box-sizing:border-box;
  background:transparent;
  border:2px solid var(--purple);
  color:var(--purple);
  border-radius:0;cursor:pointer;
  font-size:0.98em;font-family:var(--lcars-font);
  letter-spacing:2px;text-transform:uppercase;
  white-space:nowrap;transition:all .15s;
}
.btn-reprocess-all:hover{background:var(--purple);color:var(--bg);}
.btn-reprocess-all.running{border-color:var(--yellow);color:var(--yellow);cursor:wait;}
.btn-reset{
  padding:6px 14px;
  height:100%;box-sizing:border-box;
  background:transparent;
  border:2px solid var(--muted);
  color:var(--muted);
  border-radius:0;cursor:pointer;
  font-size:0.98em;font-family:var(--lcars-font);
  letter-spacing:2px;text-transform:uppercase;
  white-space:nowrap;transition:all .15s;
}
.btn-reset:hover{border-color:var(--red);color:var(--red);}
.results-pill{
  padding:5px 12px;
  background:var(--bg3);
  border:1px solid var(--blue);
  font-size:0.92em;color:var(--muted);
  white-space:nowrap;letter-spacing:2px;
  display:flex;align-items:center;
}
.results-pill span{color:var(--orange);font-weight:700;}

/* ── MODAL ── */
.modal-overlay{
  display:none;position:fixed;top:0;left:0;right:0;bottom:0;
  background:rgba(0,0,8,.85);z-index:1000;
  align-items:center;justify-content:center;
}
.modal-overlay.visible{display:flex;}
.modal{
  background:var(--bg2);
  border:2px solid var(--orange);
  padding:28px 32px;max-width:420px;width:90%;
}
.modal h3{font-size:1.1em;font-weight:700;color:var(--orange);margin-bottom:10px;letter-spacing:3px;text-transform:uppercase;}
.modal p{font-size:0.94em;color:var(--muted);margin-bottom:20px;line-height:1.6;}
.modal-btns{display:flex;gap:10px;justify-content:flex-end;}
.modal-cancel{padding:8px 16px;background:transparent;border:2px solid var(--muted);color:var(--muted);cursor:pointer;font-size:0.94em;font-family:var(--lcars-font);letter-spacing:2px;}
.modal-cancel:hover{border-color:var(--red);color:var(--red);}
.modal-confirm{padding:8px 16px;background:var(--purple);border:none;color:var(--bg);cursor:pointer;font-size:0.94em;font-weight:700;font-family:var(--lcars-font);letter-spacing:2px;}
.modal-confirm:hover{background:var(--orange);}
.modal-progress{font-size:1em;color:var(--muted);margin-top:14px;min-height:20px;letter-spacing:2px;}
.analysis-dialog textarea{
  width:100%;
  background:var(--bg3);
  border:1px solid var(--blue);
  color:var(--text);
  font-family:var(--lcars-font);
  font-size:0.88em;
  letter-spacing:1px;
  padding:10px;
  resize:vertical;
  min-height:80px;
  margin-bottom:10px;
}
.analysis-dialog textarea:focus{outline:none;border-color:var(--orange);}
.analysis-topics{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px;}
.topic-btn{
  padding:4px 12px;
  background:var(--bg3);
  border:1px solid var(--dim);
  color:var(--muted);
  cursor:pointer;
  font-family:var(--lcars-font);
  font-size:0.78em;
  letter-spacing:1px;
  text-transform:uppercase;
  transition:all .15s;
}
.topic-btn:hover{border-color:var(--blue);color:var(--blue);}
.topic-btn.active{background:var(--blue);color:var(--bg);border-color:var(--blue);font-weight:700;}
.analysis-label{
  font-size:0.78em;color:var(--blue);
  letter-spacing:3px;text-transform:uppercase;
  font-family:var(--lcars-font);font-weight:700;
  margin-bottom:6px;display:block;
}

/* ── CHARTS ── */
.charts{
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:4px;
  padding:8px 12px;
}
.chart-card{
  background:var(--bg2);
  border:1px solid var(--border);
  border-left:4px solid var(--purple);
  padding:14px 16px;
  overflow:hidden;
  min-width:0;
}
.chart-card.wide{grid-column:span 2;border-left-color:var(--orange);}
.chart-card:nth-child(2){border-left-color:var(--teal);}
.chart-card:nth-child(3){border-left-color:var(--blue);}
.chart-card h3{
  font-size:0.85em;color:var(--purple);
  text-transform:uppercase;letter-spacing:4px;
  font-weight:700;margin-bottom:12px;
  font-family:var(--lcars-font);
}
.chart-card.wide h3{color:var(--orange);}
.chart-card:nth-child(2) h3{color:var(--teal);}
.chart-card:nth-child(3) h3{color:var(--blue);}
.chart-card canvas{max-height:200px;}
.chart-card.wide canvas{max-height:130px;}

/* ── TAG CLOUD ── */
.tag-cloud{display:flex;flex-wrap:wrap;gap:4px;padding:2px 0;overflow:hidden;width:100%;}
.tag-pill{
  padding:2px 10px;
  font-size:0.92em;cursor:pointer;
  border:1px solid var(--dim);
  background:var(--bg3);color:var(--muted);
  transition:all .15s;line-height:1.6;
  font-family:var(--lcars-font);letter-spacing:1px;
  border-radius:0;
}
.tag-pill:hover{border-color:var(--orange);color:var(--orange);}
.tag-pill.active{background:var(--orange);color:var(--bg);border-color:var(--orange);font-weight:700;}

/* ── TABLE LCARS ── */
.table-section{padding:0 12px 20px;}
.table-header{
  display:flex;align-items:center;justify-content:space-between;
  margin-bottom:6px;padding:8px 0;
  border-bottom:2px solid var(--orange);
}
.table-header h3{
  font-size:0.85em;color:var(--orange);text-transform:uppercase;letter-spacing:4px;font-weight:700;
  font-family:var(--lcars-font);
}
.table-wrap{overflow-x:auto;border:1px solid var(--border);}
table{width:100%;border-collapse:collapse;min-width:600px;}
thead tr{background:var(--bg3);}
th{
  padding:10px 14px;text-align:left;
  font-size:0.85em;color:var(--blue);
  text-transform:uppercase;letter-spacing:3px;font-weight:700;
  font-family:var(--lcars-font);
  cursor:pointer;white-space:nowrap;
  border-bottom:2px solid var(--blue);
  transition:color .15s;user-select:none;
}
th:hover{color:var(--orange);}
th.sort-active{color:var(--orange);border-bottom-color:var(--orange);}
th .arrow{margin-left:4px;opacity:.6;}
td{
  padding:9px 14px;
  border-bottom:1px solid var(--border);
  vertical-align:middle;
  font-size:0.98em;
}
tr:last-child td{border-bottom:none;}
tbody tr{transition:background .1s;}
tbody tr:hover td{background:var(--bg3);}
.doc-link{
  color:var(--text);text-decoration:none;font-weight:500;
  transition:color .15s;letter-spacing:0.5px;
}
.doc-link:hover{color:var(--orange);}

/* ── BADGES ── */
.badge{display:inline-block;padding:2px 10px;font-size:1em;font-weight:700;white-space:nowrap;letter-spacing:2px;text-transform:uppercase;font-family:var(--lcars-font);}
.badge-report         {background:rgba(153,153,255,.2);color:#9999FF;border:1px solid rgba(153,153,255,.4);}
.badge-rivista        {background:rgba(204,102,204,.2);color:#CC66CC;border:1px solid rgba(204,102,204,.4);}
.badge-presentazione  {background:rgba(255,153,0,.2);color:#FF9900;border:1px solid rgba(255,153,0,.4);}
.badge-foglio_calcolo {background:rgba(153,255,153,.2);color:#99FF99;border:1px solid rgba(153,255,153,.4);}
.badge-contratto      {background:rgba(255,102,102,.2);color:#FF6666;border:1px solid rgba(255,102,102,.4);}
.badge-fattura        {background:rgba(255,204,0,.2);color:#FFCC00;border:1px solid rgba(255,204,0,.4);}
.badge-video          {background:rgba(153,204,255,.2);color:#99CCFF;border:1px solid rgba(153,204,255,.4);}
.badge-audio          {background:rgba(153,255,153,.2);color:#99FF99;border:1px solid rgba(153,255,153,.4);}
.badge-manuale        {background:rgba(255,153,0,.15);color:#FF9900;border:1px solid rgba(255,153,0,.3);}
.badge-articolo       {background:rgba(153,204,255,.15);color:#99CCFF;border:1px solid rgba(153,204,255,.3);}
.badge-brevetto       {background:rgba(204,102,204,.15);color:#CC66CC;border:1px solid rgba(204,102,204,.3);}
.badge-datasheet      {background:rgba(0,255,204,.15);color:#00FFCC;border:1px solid rgba(0,255,204,.3);}
.badge-specifica_tecnica{background:rgba(100,200,255,.15);color:#64C8FF;border:1px solid rgba(100,200,255,.3);}
.badge-brochure       {background:rgba(255,180,50,.15);color:#FFB432;border:1px solid rgba(255,180,50,.3);}
.badge-visura         {background:rgba(0,204,153,.15);color:#00CC99;border:1px solid rgba(0,204,153,.3);}
.badge-verbale        {background:rgba(255,200,80,.15);color:#FFC850;border:1px solid rgba(255,200,80,.3);}
.badge-certificato    {background:rgba(255,215,0,.2);color:#FFD700;border:1px solid rgba(255,215,0,.4);}
.badge-vademecum      {background:rgba(255,140,0,.15);color:#FF8C00;border:1px solid rgba(255,140,0,.3);}
.badge-agenda         {background:rgba(100,180,255,.15);color:#64B4FF;border:1px solid rgba(100,180,255,.3);}
.badge-faq            {background:rgba(180,130,255,.15);color:#B482FF;border:1px solid rgba(180,130,255,.3);}
.badge-viaggio        {background:rgba(80,200,230,.15);color:#50C8E6;border:1px solid rgba(80,200,230,.3);}
.badge-spettacolo     {background:rgba(255,100,200,.15);color:#FF64C8;border:1px solid rgba(255,100,200,.3);}
.badge-schema         {background:rgba(100,255,150,.15);color:#64FF96;border:1px solid rgba(100,255,150,.3);}
.badge-progetto       {background:rgba(255,165,0,.2);color:#FFA500;border:1px solid rgba(255,165,0,.4);}
.badge-altro          {background:rgba(136,136,187,.15);color:#8888BB;border:1px solid rgba(136,136,187,.3);}

/* ── ROW TAGS ── */
.row-tag{
  display:inline-block;padding:1px 7px;margin:1px 2px;
  font-size:0.98em;
  background:var(--bg4);color:var(--muted);
  cursor:pointer;transition:all .12s;border:1px solid var(--dim);
  font-family:var(--lcars-font);letter-spacing:1px;
}
.row-tag:hover{background:rgba(255,153,0,.15);color:var(--orange);border-color:var(--orange);}

/* ── TIMELINE NAV ── */
.timeline-nav{
  padding:2px 8px;background:var(--bg3);border:1px solid var(--orange);
  color:var(--orange);cursor:pointer;font-size:1em;
  font-family:var(--lcars-font);letter-spacing:1px;
  transition:all .15s;
}
.timeline-nav:hover{background:var(--orange);color:var(--bg);}
.empty{text-align:center;padding:48px;color:var(--muted);font-size:1em;letter-spacing:3px;text-transform:uppercase;}

/* ── REPROCESS BTN ── */
.btn-reprocess{
  padding:3px 10px;
  background:transparent;
  border:1px solid var(--dim);
  color:var(--muted);
  cursor:pointer;
  font-size:0.92em;font-family:var(--lcars-font);
  letter-spacing:2px;text-transform:uppercase;
  white-space:nowrap;transition:all .15s;
}
.btn-reprocess:hover{border-color:var(--orange);color:var(--orange);}
.btn-reprocess.running{border-color:var(--yellow);color:var(--yellow);cursor:wait;}
.btn-reprocess.done{border-color:var(--green);color:var(--green);}

/* ── FOOTER LCARS ── */
.lcars-footer{
  background:var(--teal);
  padding:6px 20px;
  display:flex;align-items:center;justify-content:center;
  gap:20px;
  position:sticky;bottom:0;z-index:50;
}
.lcars-footer-text{
  font-size:0.85em;font-weight:700;color:var(--bg);
  letter-spacing:4px;text-transform:uppercase;
  font-family:var(--lcars-font);
}

/* ── RESPONSIVE ── */

/* Tutto ciò che è più stretto di 1300px — include iPad 1180px */
@media(max-width:1300px){
  .header-corner{width:120px;}
  /* Filter actions su riga separata */
  .filter-actions{
    flex-basis:100%;
    flex-wrap:wrap;
    gap:6px;
    margin-top:4px;
  }
  .btn-refresh,.btn-reprocess-all,.btn-reset{
    flex:1;min-width:70px;text-align:center;
  }
  .results-pill{text-align:center;}
  /* Tabella più compatta */
  th{padding:8px 10px;font-size:0.78em;letter-spacing:1px;}
  td{padding:7px 10px;font-size:0.9em;}
  /* Nascondi colonna Riprocessa e checkbox su schermi stretti */
  td.server-only, th:last-child{display:none;}
  td.td-select, th.th-select{display:none;}
  #analysis-bar{display:none !important;}
}

/* Mac stretto e tablet */
@media(max-width:1024px){
  .lcars-sidebar{width:40px;}
  .stat-num{font-size:1.7em;}
  .charts{grid-template-columns:1fr;}
  .chart-card.wide{grid-column:span 1;}
}

/* Mobile */
@media(max-width:768px){
  .header{height:auto;flex-wrap:wrap;}
  .header-corner{width:100%;height:44px;border-radius:0;padding:0 14px;}
  .header-gap{display:none;}
  .header-bar{width:100%;padding:8px 14px;}
  .header h1{font-size:1em;letter-spacing:2px;}
  .updated{font-size:0.65em;}
  .lcars-sidebar{display:none;}
  .stat-num{font-size:1.5em;}
  .stat-label{font-size:0.68em;letter-spacing:1px;}
  .filters{flex-direction:column;padding:8px;gap:6px;}
  .filter-group{min-width:0;width:100%;}
  .filter-group input,.filter-group select{font-size:16px;padding:10px 12px;}
  .filter-actions{flex-basis:auto;flex-direction:row;flex-wrap:wrap;gap:6px;width:100%;margin-top:0;}
  .btn-refresh,.btn-reprocess-all,.btn-reset{flex:1;text-align:center;padding:10px 8px;font-size:0.82em;}
  .results-pill{width:100%;text-align:center;}
  .charts{grid-template-columns:1fr;padding:6px 8px;gap:6px;}
  .chart-card.wide{grid-column:span 1;}
  .chart-card canvas{max-height:160px;}
  .tag-pill{font-size:0.78em;padding:4px 10px;}
  .table-section{padding:0 6px 16px;}
  .table-wrap{border:none;}
  table{min-width:500px;font-size:0.85em;}
  th{padding:8px 8px;font-size:0.75em;letter-spacing:1px;}
  td{padding:8px 8px;}
  .lcars-footer{padding:4px 10px;}
  .lcars-footer-text{font-size:0.6em;letter-spacing:2px;}
  .modal{width:95%;padding:20px;}
  .btn-reprocess{padding:6px 12px;font-size:0.8em;}
}

/* ── SELEZIONE E ANALISI ── */
.cb-select{
  width:16px;height:16px;cursor:pointer;
  accent-color:var(--orange);
}
th.th-select{width:36px;cursor:default;}
td.td-select{text-align:center;vertical-align:middle;}
.analysis-bar{
  display:none;
  background:var(--bg3);
  border:2px solid var(--orange);
  padding:10px 16px;
  align-items:center;gap:12px;flex-wrap:wrap;
  margin:0 12px 8px;
}
.analysis-bar.visible{display:flex;}
.analysis-count{
  font-size:0.88em;color:var(--orange);
  letter-spacing:2px;text-transform:uppercase;
  font-family:var(--lcars-font);font-weight:700;
}
.btn-analyze{
  padding:7px 18px;
  background:var(--orange);
  border:none;color:var(--bg);
  cursor:pointer;font-size:0.88em;
  font-family:var(--lcars-font);
  letter-spacing:2px;text-transform:uppercase;
  font-weight:700;transition:all .15s;white-space:nowrap;
}
.btn-analyze:hover{background:#FFB833;}
.btn-analyze:disabled{opacity:0.5;cursor:wait;}
.btn-clear-sel{
  padding:7px 14px;background:transparent;
  border:1px solid var(--muted);color:var(--muted);
  cursor:pointer;font-size:0.85em;
  font-family:var(--lcars-font);letter-spacing:2px;
  text-transform:uppercase;transition:all .15s;
}
.btn-clear-sel:hover{border-color:var(--red);color:var(--red);}
.btn-action{
  padding:7px 14px;background:transparent;
  border:1px solid var(--blue);color:var(--blue);
  cursor:pointer;font-size:0.85em;
  font-family:var(--lcars-font);letter-spacing:2px;
  text-transform:uppercase;transition:all .15s;white-space:nowrap;
}
.btn-action:hover{background:rgba(153,204,255,.1);border-color:#99CCFF;color:#99CCFF;}
.btn-action:disabled{opacity:0.4;cursor:wait;}
.pkm-toast{
  position:fixed;bottom:32px;left:50%;transform:translateX(-50%);
  background:var(--bg3);border:1px solid var(--orange);color:var(--orange);
  padding:10px 28px;font-family:var(--lcars-font);font-size:0.83em;
  letter-spacing:2px;text-transform:uppercase;white-space:nowrap;
  opacity:0;transition:opacity .3s;pointer-events:none;z-index:9999;
}
.pkm-toast.visible{opacity:1;}
.focus-item{
  display:flex;align-items:center;gap:6px;
  background:var(--bg3);border:1px solid var(--dim);
  padding:5px 10px;cursor:pointer;
  font-size:0.82em;color:var(--muted);
  font-family:var(--lcars-font);letter-spacing:1px;
  transition:all .15s;
}
.focus-item:hover{border-color:var(--blue);color:var(--blue);}
.focus-item input[type=checkbox]{accent-color:var(--orange);cursor:pointer;}
.focus-item:has(input:checked){border-color:var(--orange);color:var(--orange);background:rgba(255,153,0,0.08);}

/* ── MODALE ANALISI ── */
.analysis-modal-overlay{
  display:none;position:fixed;top:0;left:0;right:0;bottom:0;
  background:rgba(0,0,8,.88);z-index:2000;
  align-items:center;justify-content:center;
}
.analysis-modal-overlay.visible{display:flex;}
.analysis-modal{
  background:var(--bg2);border:2px solid var(--orange);
  padding:28px 32px;max-width:560px;width:92%;
  font-family:var(--lcars-font);
}
.analysis-modal h3{
  font-size:1.1em;font-weight:700;color:var(--orange);
  margin-bottom:6px;letter-spacing:3px;text-transform:uppercase;
}
.analysis-modal .modal-sub{
  font-size:0.78em;color:var(--muted);letter-spacing:1px;margin-bottom:16px;
}
.analysis-modal label{
  display:block;font-size:0.72em;color:var(--blue);
  letter-spacing:2px;text-transform:uppercase;margin:12px 0 4px;
}
.analysis-modal textarea{
  width:100%;background:var(--bg3);border:1px solid var(--blue);
  color:var(--text);font-family:var(--lcars-font);font-size:0.88em;
  padding:8px 10px;resize:vertical;min-height:70px;
  letter-spacing:0.5px;
}
.analysis-modal textarea:focus{outline:none;border-color:var(--orange);}
.analysis-topics{display:flex;flex-wrap:wrap;gap:6px;margin-top:6px;}
.topic-chip{
  padding:4px 12px;border:1px solid var(--dim);background:var(--bg3);
  color:var(--muted);cursor:pointer;font-size:0.75em;
  font-family:var(--lcars-font);letter-spacing:1px;transition:all .15s;
}
.topic-chip:hover{border-color:var(--blue);color:var(--blue);}
.topic-chip.selected{background:var(--blue);color:var(--bg);border-color:var(--blue);}
.analysis-modal-btns{display:flex;gap:10px;justify-content:flex-end;margin-top:20px;}
.btn-analysis-cancel{
  padding:8px 18px;background:transparent;border:2px solid var(--muted);
  color:var(--muted);cursor:pointer;font-family:var(--lcars-font);
  letter-spacing:2px;font-size:0.82em;
}
.btn-analysis-cancel:hover{border-color:var(--red);color:var(--red);}
.btn-analysis-go{
  padding:8px 22px;background:var(--orange);border:none;
  color:var(--bg);cursor:pointer;font-family:var(--lcars-font);
  letter-spacing:2px;font-size:0.82em;font-weight:700;
}
.btn-analysis-go:hover{background:#FFB833;}
tr.selected td{background:rgba(255,153,0,0.08) !important;}
</style>
</head>
<body>

<div class="header">
  <div class="header-corner">
    <div class="logo">◈</div>
    <h1>PKM</h1>
  </div>
  <div class="header-gap"></div>
  <div class="header-bar">
    <h1>PERSONAL KNOWLEDGE ARCHIVE - NEW FEATURES!</h1>
    <div class="header-right">
      <span class="updated">STARDATE __NOW__</span>
    </div>
  </div>
</div>

<div class="offline-banner" id="offline-banner">
  <span>⚠</span> MODALITÀ SOLA LETTURA — SERVER NON DISPONIBILE. FILTRI E RICERCA ATTIVI.
</div>

<div class="activity-banner server-only" id="activity-banner">
  <div class="activity-spinner"></div>
  <div class="activity-filename" id="activity-filename"></div>
  <div class="activity-desc" id="activity-desc"></div>
  <div class="activity-step" id="activity-step"></div>
  <div class="activity-progress" id="activity-progress"></div>
</div>

<div class="lcars-layout">
<div class="lcars-sidebar">
  <div class="lcars-sidebar-block"></div>
  <div class="lcars-sidebar-block orange"></div>
  <div class="lcars-sidebar-block teal"></div>
  <div class="lcars-sidebar-block"></div>
</div>
<div class="lcars-main">

<div class="stats" id="stats"></div>

<div style="display:flex;align-items:stretch;gap:4px;">
<div class="filters">
  <div class="filter-group">
    <label>🔍 Ricerca</label>
    <input type="text" id="f-search" placeholder="Titolo, sommario, tag..."
      style="background:#3D2000;border:2px solid var(--orange);color:#FFD080;font-weight:700;letter-spacing:1px;">
  </div>
  <div class="filter-group">
    <label>📄 Tipo</label>
    <select id="f-tipo"></select>
  </div>
  <div class="filter-group">
    <label>🌐 Lingua</label>
    <select id="f-lingua"></select>
  </div>
  <div class="filter-group">
    <label>🏢 Organizzazione</label>
    <select id="f-org"></select>
  </div>
  <div class="filter-group">
    <label>📅 Data doc. da</label>
    <input type="date" id="f-date-from">
  </div>
  <div class="filter-group">
    <label>📅 Data doc. a</label>
    <input type="date" id="f-date-to">
  </div>
  <div class="filter-actions">
    <div class="results-pill" id="results-info"><span>—</span></div>
    <button class="btn-refresh server-only" onclick="window.location.href='http://127.0.0.1:5050/refresh'">↺ Aggiorna</button>
    <button class="btn-reprocess-all server-only" onclick="confirmReprocessAll()">⟳ Riprocessa tutti</button>
    <button class="btn-reset" onclick="resetFilters()">✕ Reset</button>
  </div>
</div>

<div class="upload-area" id="upload-area">
  <div class="upload-icon">⬆</div>
  <div class="upload-label">Trascina file qui</div>
  <div class="upload-status" id="upload-status">→ _inbox/</div>
</div>
</div><!-- fine wrapper filtri+upload -->

<div class="charts">
  <div class="chart-card">
    <h3>Tipi documento</h3>
    <canvas id="chart-tipi"></canvas>
  </div>
  <div class="chart-card">
    <h3>Tag più frequenti</h3>
    <canvas id="chart-tags"></canvas>
  </div>
  <div class="chart-card wide">
    <h3 style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
      <span>Timeline per data documento</span>
      <div style="display:flex;align-items:center;gap:6px;flex-shrink:0;">
        <button class="timeline-nav" onclick="shiftYear(-1)">◀</button>
        <span id="timeline-year-label" style="font-size:0.85em;color:var(--text);font-weight:600;min-width:36px;text-align:center;"></span>
        <button class="timeline-nav" onclick="shiftYear(1)">▶</button>
        <button class="timeline-nav" onclick="resetYearFilter()" title="Mostra tutti">✕</button>
      </div>
    </h3>
    <div id="timeline-scroll" style="overflow-x:auto;scroll-behavior:smooth;-webkit-overflow-scrolling:touch;">
      <div id="timeline-wrap">
        <canvas id="chart-timeline" style="max-height:160px;display:block;"></canvas>
      </div>
    </div>
  </div>
  <div class="chart-card wide">
    <h3 class="collapsible" onclick="toggleTagCloud(this)">
      Mappa tag — clicca per filtrare
      <span class="collapse-arrow">▲</span>
    </h3>
    <div class="tag-cloud" id="tag-cloud"></div>
  </div>
</div>

<div id="analysis-bar" class="analysis-bar server-only">
  <span class="analysis-count" id="sel-count">0 selezionati</span>
  <button class="btn-analyze" id="btn-analyze" onclick="openAnalysisModal()">⬡ Analizza selezionati</button>
  <button class="btn-action" onclick="downloadZip()">↓ Scarica zip</button>
  <button class="btn-action" onclick="openWithApp()">⊞ Apri</button>
  <button class="btn-action" onclick="shareFiles()">⬡ Condividi</button>
  <button class="btn-action" onclick="shareWhatsapp()">💬 WhatsApp</button>
  <button class="btn-clear-sel" onclick="clearSelection()">✕ Deseleziona tutto</button>
</div>

<!-- Modale pre-analisi -->
<div class="modal-overlay" id="analysis-modal-overlay">
  <div class="modal" style="max-width:560px;">
    <h3>⬡ Configura analisi</h3>
    <p style="font-size:0.82em;margin-bottom:14px;">Personalizza l'analisi dei <span id="modal-doc-count">0</span> documenti selezionati.</p>

    <div style="margin-bottom:14px;">
      <div style="font-size:0.78em;color:var(--blue);letter-spacing:2px;text-transform:uppercase;margin-bottom:6px;">Richieste specifiche (opzionale)</div>
      <textarea id="analysis-custom-prompt"
        placeholder="Es: confronta le metodologie utilizzate, identifica le date chiave, analizza i rischi..."
        style="width:100%;background:var(--bg3);border:1px solid var(--blue);color:var(--text);
               font-family:var(--lcars-font);font-size:0.88em;padding:8px 10px;
               resize:vertical;min-height:70px;letter-spacing:1px;"></textarea>
    </div>

    <div style="margin-bottom:18px;">
      <div style="font-size:0.78em;color:var(--blue);letter-spacing:2px;text-transform:uppercase;margin-bottom:8px;">Focalizza l'analisi su</div>
      <div style="display:flex;flex-wrap:wrap;gap:8px;">
        <label class="focus-item"><input type="checkbox" value="persone"> Persone</label>
        <label class="focus-item"><input type="checkbox" value="organizzazioni"> Organizzazioni</label>
        <label class="focus-item"><input type="checkbox" value="territori"> Territori e luoghi</label>
        <label class="focus-item"><input type="checkbox" value="tecnologie"> Tecnologie</label>
        <label class="focus-item"><input type="checkbox" value="normative"> Leggi e normative</label>
        <label class="focus-item"><input type="checkbox" value="prodotti"> Prodotti e dispositivi</label>
        <label class="focus-item"><input type="checkbox" value="date"> Date e timeline</label>
        <label class="focus-item"><input type="checkbox" value="dati"> Dati e statistiche</label>
      </div>
    </div>

    <div class="modal-btns">
      <button class="modal-cancel" onclick="closeAnalysisModal()">Annulla</button>
      <button class="modal-confirm" id="btn-start-analysis" onclick="launchAnalysis()">⬡ Avvia analisi</button>
    </div>
    <div class="modal-progress" id="analysis-progress"></div>
  </div>
</div>

<div class="table-section">
  <div class="table-header">
    <h3>Documenti</h3>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr id="thead-row"></tr></thead>
      <tbody id="tbody"></tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="modal-overlay">
  <div class="modal">
    <h3>Riprocessa tutti i documenti</h3>
    <p>Questa operazione eliminerà e rigenererà i metadati di tutti i documenti in archivio. Potrebbe richiedere diversi minuti e consumerà token API per ogni documento.<br><br>Sei sicuro di voler continuare?</p>
    <div class="modal-progress" id="modal-progress"></div>
    <div class="modal-btns">
      <button class="modal-cancel" id="modal-cancel" onclick="closeModal()">Annulla</button>
      <button class="modal-confirm" id="modal-confirm" onclick="startReprocessAll()">Sì, riprocessa tutto</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="analysis-dialog-overlay">
  <div class="modal analysis-dialog" style="max-width:560px;width:95%;">
    <h3>⬡ Analisi documenti selezionati</h3>
    <p style="margin-bottom:14px;">Verranno analizzati <strong id="analysis-doc-count">0</strong> documenti. Puoi aggiungere istruzioni specifiche o selezionare argomenti di focus.</p>

    <span class="analysis-label">Argomenti di focus (opzionale)</span>
    <div class="analysis-topics">
      <button class="topic-btn" onclick="toggleTopic(this)" data-topic="persone">Persone</button>
      <button class="topic-btn" onclick="toggleTopic(this)" data-topic="date e cronologia">Date e cronologia</button>
      <button class="topic-btn" onclick="toggleTopic(this)" data-topic="territori e luoghi">Territori e luoghi</button>
      <button class="topic-btn" onclick="toggleTopic(this)" data-topic="eventi">Eventi</button>
      <button class="topic-btn" onclick="toggleTopic(this)" data-topic="leggi e normative">Leggi e normative</button>
      <button class="topic-btn" onclick="toggleTopic(this)" data-topic="aziende e organizzazioni">Aziende</button>
      <button class="topic-btn" onclick="toggleTopic(this)" data-topic="tecnologie e prodotti">Tecnologie</button>
      <button class="topic-btn" onclick="toggleTopic(this)" data-topic="dati e statistiche">Dati e statistiche</button>
    </div>

    <span class="analysis-label">Istruzioni aggiuntive (opzionale)</span>
    <textarea id="analysis-instructions" placeholder="Es: confronta le metodologie adottate, identifica i rischi comuni, analizza l'evoluzione temporale..."></textarea>

    <div class="modal-btns">
      <button class="modal-cancel" onclick="closeAnalysisDialog()">Annulla</button>
      <button class="modal-confirm" onclick="startAnalysis()">▶ Avvia analisi</button>
    </div>
  </div>
</div>

<script>
const ALL_DOCS = __DOCS_JSON__;
const VAULT_NAME = "__VAULT_NAME__";

const COLS = [
  {key:"_select",label:""},
  {key:"title",label:"Documento"},
  {key:"tipo_documento",label:"Tipo"},
  {key:"lingua",label:"Lingua"},
  {key:"organizzazioni",label:"Organizzazione"},
  {key:"data_documento",label:"Data doc."},
  {key:"data_catalogazione",label:"Catalogato"},
  {key:"tags",label:"Tag"},
  {key:"_actions",label:""}
];

const PALETTE_CHART = [
  "#FF9900","#CC66CC","#9999FF","#99CCFF","#99FF99",
  "#FF6666","#FFCC00","#FF9966","#CC99FF","#66CCFF"
];

let sortCol = "data_catalogazione";
let sortAsc = false;
let activeTag = "";
let filterNoDate = false;
let timelineYear = null; // null = mostra tutti gli anni
let chartTipi, chartTags, chartTimeline;

function fmtDate(v) {
  if (!v || v === "N/D" || v === "-") return v || "-";
  const s = String(v).trim();
  const m = s.match(/^(\\d{4})-(\\d{2})-(\\d{2})/);
  if (m) return m[3] + "/" + m[2] + "/" + m[1];
  const m2 = s.match(/^(\\d{4})-(\\d{2})$/);
  if (m2) return m2[2] + "/" + m2[1];
  return s;
}

function uniqueSorted(arr) {
  return [...new Set(arr.filter(Boolean))].sort();
}

function getFiltered() {
  const search = (document.getElementById("f-search").value || "").toLowerCase();
  const tipo   = document.getElementById("f-tipo").value;
  const lingua = document.getElementById("f-lingua").value;
  const org    = document.getElementById("f-org").value;
  const dfrom  = document.getElementById("f-date-from").value;
  const dto    = document.getElementById("f-date-to").value;
  return ALL_DOCS.filter(function(d) {
    if (search) {
      function toStr(v) {
        if (!v) return "";
        if (Array.isArray(v)) return v.join(" ");
        return String(v);
      }
      const hay = [
        d.title, d.sommario, d.file_stem,
        toStr(d.argomenti), toStr(d.tags),
        toStr(d.parole_chiave), toStr(d.persone),
        toStr(d.organizzazioni), toStr(d.luoghi)
      ].join(" ").toLowerCase();
      if (hay.indexOf(search) === -1) return false;
    }
    if (tipo   && d.tipo_documento !== tipo)   return false;
    if (lingua && d.lingua         !== lingua) return false;
    if (org) {
      const orgs = Array.isArray(d.organizzazioni)
        ? d.organizzazioni
        : String(d.organizzazioni || "").split(",").map(function(s){return s.trim();});
      const orgLower = org.toLowerCase();
      if (!orgs.some(function(o){ return o.trim().toLowerCase() === orgLower; })) return false;
    }
    if (activeTag && !(Array.isArray(d.tags) && d.tags.indexOf(activeTag) !== -1)) return false;
    if (filterNoDate) {
      const dval = d.data_documento;
      if (dval && dval !== "N/D" && dval !== "-") return false;
    }
    if (dfrom || dto) {
      const dval = d.data_documento;
      if (!dval || dval === "N/D" || dval === "-") return false;
      // Normalizza data parziale: "2021-06" → "2021-06-01" per il confronto
      let normalized = String(dval).trim();
      if (normalized.match(/^\\d{4}$/))       normalized = normalized + "-01-01";
      else if (normalized.match(/^\\d{4}-\\d{2}$/)) normalized = normalized + "-01";
      if (dfrom && normalized < dfrom) return false;
      if (dto   && normalized > dto)   return false;
    }
    return true;
  });
}

// ── STATS ──
function renderStats(filtered) {
  const tipiSet = new Set(ALL_DOCS.map(function(d){return d.tipo_documento;}));
  const allTagsFlat = [];
  ALL_DOCS.forEach(function(d){if(Array.isArray(d.tags))d.tags.forEach(function(t){allTagsFlat.push(t);});});
  const tagsSet = new Set(allTagsFlat);
  const statsData = [
    {num:ALL_DOCS.length, label:"Totali"},
    {num:filtered.length, label:"Filtrati"},
    {num:tipiSet.size,    label:"Tipi"},
    {num:tagsSet.size,    label:"Tag unici"},
    {num:ALL_DOCS.filter(function(d){return d.lingua==="it";}).length, label:"In italiano"},
    {num:ALL_DOCS.filter(function(d){return d.lingua==="en";}).length, label:"In inglese"}
  ];
  const el = document.getElementById("stats");
  el.innerHTML = "";
  statsData.forEach(function(s, i) {
    const card = document.createElement("div");
    card.className = "stat-card";
    // Fa lampeggiare "Filtrati" quando i filtri sono attivi
    if (i === 1 && filtered.length < ALL_DOCS.length) {
      card.classList.add("filtered-active");
    }
    card.innerHTML = "<div class=\\"stat-num\\">" + s.num + "</div><div class=\\"stat-label\\">" + s.label + "</div>";
    el.appendChild(card);
  });
}

// ── CHARTS ──
const chartDefaults = {
  color: "#6b6d8a",
  plugins: {legend:{labels:{color:"#a9b1d6",font:{size:11},boxWidth:12,padding:12}}},
  scales: {
    x:{ticks:{color:"#6b6d8a",font:{size:10}},grid:{color:"rgba(46,46,80,.5)"}},
    y:{ticks:{color:"#6b6d8a",font:{size:10}},grid:{color:"rgba(46,46,80,.5)"}}
  }
};

function buildCharts() {
  // Torta tipi
  const tipiCount = {};
  ALL_DOCS.forEach(function(d){const k=d.tipo_documento||"altro";tipiCount[k]=(tipiCount[k]||0)+1;});
  const tipiKeys = Object.keys(tipiCount).sort(function(a,b){return tipiCount[b]-tipiCount[a];});
  if (chartTipi) chartTipi.destroy();
  chartTipi = new Chart(document.getElementById("chart-tipi"), {
    type: "doughnut",
    data: {
      labels: tipiKeys,
      datasets: [{
        data: tipiKeys.map(function(k){return tipiCount[k];}),
        backgroundColor: PALETTE_CHART,
        borderWidth: 3,
        borderColor: "#16162a",
        hoverBorderColor: "#1e1e35",
        hoverOffset: 6
      }]
    },
    options: {
      cutout: "65%",
      plugins: {
        legend: {position:"right", labels:{color:"#a9b1d6",font:{size:11},boxWidth:10,padding:10}}
      },
      onClick: function(evt, elements) {
        if (!elements.length) return;
        const label = tipiKeys[elements[0].index];
        const sel = document.getElementById("f-tipo");
        sel.value = (sel.value === label) ? "" : label;
        refresh();
      },
      onHover: function(evt, elements) {
        evt.native.target.style.cursor = elements.length ? "pointer" : "default";
      }
    }
  });

  // Barre tag orizzontali
  const tagCount = {};
  ALL_DOCS.forEach(function(d){(d.tags||[]).forEach(function(t){tagCount[t]=(tagCount[t]||0)+1;});});
  const topTags = Object.keys(tagCount).sort(function(a,b){return tagCount[b]-tagCount[a];}).slice(0,12);
  const tagColors = topTags.map(function(_,i){return PALETTE_CHART[i % PALETTE_CHART.length];});
  if (chartTags) chartTags.destroy();
  chartTags = new Chart(document.getElementById("chart-tags"), {
    type: "bar",
    data: {
      labels: topTags,
      datasets: [{
        data: topTags.map(function(t){return tagCount[t];}),
        backgroundColor: tagColors.map(function(c){return c+"33";}),
        borderColor: tagColors,
        borderWidth: 1,
        borderRadius: 5,
        borderSkipped: false
      }]
    },
    options: {
      indexAxis: "y",
      plugins: {legend:{display:false}},
      scales: {
        x: {ticks:{color:"#6b6d8a",font:{size:10}}, grid:{color:"rgba(46,46,80,.5)"}},
        y: {ticks:{color:"#c0caf5",font:{size:11}}, grid:{display:false}}
      },
      onClick: function(evt, elements) {
        if (!elements.length) return;
        const tag = topTags[elements[0].index];
        activeTag = (activeTag === tag) ? "" : tag;
        buildTagCloud();
        const f = getFiltered();
        renderTable(f);
        renderStats(f);
        updateResultsPill(f.length);
      },
      onHover: function(evt, elements) {
        evt.native.target.style.cursor = elements.length ? "pointer" : "default";
      }
    }
  });

  // Timeline
  const byMonth = {};
  ALL_DOCS.forEach(function(d) {
    const raw = d.data_documento || "";
    if (!raw || raw === "N/D" || raw === "-") return;
    let s = String(raw).trim();
    if (s.match(/^\\d{4}$/))            s = s + "-01";
    if (s.match(/^\\d{4}-\\d{2}/))      s = s.substring(0, 7);
    if (s.match(/^\\d{4}-\\d{2}$/)) { byMonth[s] = (byMonth[s]||0)+1; }
  });
  const noDateCount = ALL_DOCS.filter(function(d) {
    const v = d.data_documento;
    return !v || v === "N/D" || v === "-";
  }).length;

  const allMonths = Object.keys(byMonth).sort();

  // Calcola anni disponibili
  const availableYears = [];
  allMonths.forEach(function(m) {
    const y = parseInt(m.substring(0,4));
    if (availableYears.indexOf(y) === -1) availableYears.push(y);
  });
  availableYears.sort();

  // Se timelineYear non è impostato o non esiste, usa tutti
  if (timelineYear && availableYears.indexOf(timelineYear) === -1) timelineYear = null;

  // Filtra mesi per anno selezionato
  const months = timelineYear
    ? allMonths.filter(function(m){ return parseInt(m.substring(0,4)) === timelineYear; })
    : allMonths;

  // Aggiorna label anno
  const yearLabel = document.getElementById("timeline-year-label");
  if (yearLabel) yearLabel.textContent = timelineYear ? String(timelineYear) : "Tutti";

  const allLabels = months.map(function(m){const p=m.split("-");return p[1]+"/"+p[0];});
  const allData   = months.map(function(m){return byMonth[m];});
  const allColors = months.map(function(){return "rgba(187,154,247,.25)";});
  const allBorders= months.map(function(){return "#FF9900";});

  if (!timelineYear && noDateCount > 0) {
    allLabels.push("Senza data");
    allData.push(noDateCount);
    allColors.push("rgba(107,109,138,.3)");
    allBorders.push("#6b6d8a");
  }

  // Larghezza canvas
  const BAR_W = window.innerWidth <= 1200 ? 38 : 55;
  const wrap = document.getElementById("timeline-wrap");
  if (wrap) {
    if (timelineYear) {
      // Vista anno singolo: occupa tutta la larghezza disponibile
      wrap.style.width = "100%";
    } else {
      // Vista tutti: larghezza proporzionale a tutti i mesi con scroll
      const totalW = Math.max(allMonths.length * BAR_W + (noDateCount > 0 ? BAR_W : 0), 600);
      wrap.style.width = totalW + "px";
    }
  }

  // Auto-scroll nella vista "Tutti": posiziona sull'anno corrente se disponibile
  if (!timelineYear) {
    setTimeout(function() {
      const scroller = document.getElementById("timeline-scroll");
      if (!scroller) return;
      const currentYear = new Date().getFullYear();
      const idx = allMonths.indexOf(currentYear + "-01");
      if (idx >= 0) scroller.scrollLeft = idx * BAR_W;
      else scroller.scrollLeft = scroller.scrollWidth;
    }, 50);
  }
  if (chartTimeline) { chartTimeline.destroy(); chartTimeline = null; }
  chartTimeline = new Chart(document.getElementById("chart-timeline"), {
    type: "bar",
    data: {
      labels: allLabels,
      datasets: [{
        data: allData,
        backgroundColor: allColors,
        borderColor: allBorders,
        borderWidth: 1,
        borderRadius: 4,
        borderSkipped: false
      }]
    },
    options: {
      plugins: {legend:{display:false}},
      scales: {
        x: {ticks:{color:"#6b6d8a",font:{size:9},maxRotation:45}, grid:{color:"rgba(46,46,80,.4)"}},
        y: {ticks:{color:"#6b6d8a",stepSize:1,font:{size:10}}, grid:{color:"rgba(46,46,80,.4)"}}
      },
      onClick: function(evt, elements) {
        if (!elements.length) return;
        const idx = elements[0].index;
        const from = document.getElementById("f-date-from");
        const to   = document.getElementById("f-date-to");
        // Barra "Senza data" (solo quando non filtrato per anno)
        if (!timelineYear && noDateCount > 0 && allLabels[idx] === "Senza data") {
          filterNoDate = !filterNoDate;
          from.value = ""; to.value = "";
          buildTagCloud();
          refresh();
          return;
        }
        // Barra mese normale
        filterNoDate = false;
        const month = months[idx];
        const parts = month.split("-");
        const lastDay = new Date(parseInt(parts[0]), parseInt(parts[1]), 0).getDate();
        const dateFrom = month + "-01";
        const dateTo   = month + "-" + (lastDay < 10 ? "0" + lastDay : "" + lastDay);
        if (from.value === dateFrom && to.value === dateTo) {
          from.value = ""; to.value = "";
        } else {
          from.value = dateFrom;
          to.value   = dateTo;
        }
        refresh();
      },
      onHover: function(evt, elements) {
        evt.native.target.style.cursor = elements.length ? "pointer" : "default";
      }
    }
  });
}

// ── TAG CLOUD ──
function buildTagCloud() {
  const tagCount = {};
  ALL_DOCS.forEach(function(d){(d.tags||[]).forEach(function(t){tagCount[t]=(tagCount[t]||0)+1;});});
  const sorted = Object.keys(tagCount).sort(function(a,b){return tagCount[b]-tagCount[a];});
  const max = tagCount[sorted[0]] || 1;
  const cloud = document.getElementById("tag-cloud");
  cloud.innerHTML = "";
  sorted.forEach(function(tag) {
    const ratio = tagCount[tag] / max;
    const size = 0.72 + ratio * 0.6;
    const pill = document.createElement("span");
    pill.className = "tag-pill" + (activeTag === tag ? " active" : "");
    pill.textContent = tag + " (" + tagCount[tag] + ")";
    pill.style.fontSize = size + "em";
    pill.onclick = function() {
      activeTag = (activeTag === tag) ? "" : tag;
      buildTagCloud();
      const f = getFiltered();
      renderTable(f);
      renderStats(f);
      updateResultsPill(f.length);
    };
    cloud.appendChild(pill);
  });
}

// ── TABLE ──
function renderTable(filtered) {
  const thead = document.getElementById("thead-row");
  thead.innerHTML = "";
  COLS.forEach(function(col) {
    const th = document.createElement("th");
    if (col.key === "_select") {
      th.className = "th-select";
      const cbAll = document.createElement("input");
      cbAll.type = "checkbox";
      cbAll.className = "cb-select";
      cbAll.title = "Seleziona tutti";
      cbAll.onchange = function() { toggleSelectAll(this.checked); };
      th.appendChild(cbAll);
      thead.appendChild(th);
      return;
    }
    if (col.key === "_actions") {
      th.style.width = "90px";
      th.style.cursor = "default";
      thead.appendChild(th);
      return;
    }
    th.className = sortCol === col.key ? "sort-active" : "";
    const arrow = sortCol === col.key ? (sortAsc ? " ▲" : " ▼") : "";
    th.innerHTML = col.label + "<span class=\\"arrow\\">" + arrow + "</span>";
    th.onclick = function() {
      if (sortCol === col.key) sortAsc = !sortAsc;
      else { sortCol = col.key; sortAsc = true; }
      renderTable(getFiltered());
    };
    thead.appendChild(th);
  });

  const sorted = filtered.slice().sort(function(a,b) {
    let va = a[sortCol]||""; let vb = b[sortCol]||"";
    if (Array.isArray(va)) va = va.join(",");
    if (Array.isArray(vb)) vb = vb.join(",");
    const r = String(va).localeCompare(String(vb),"it");
    return sortAsc ? r : -r;
  });

  const tbody = document.getElementById("tbody");
  tbody.innerHTML = "";
  if (sorted.length === 0) {
    tbody.innerHTML = "<tr><td colspan=\\"8\\" class=\\"empty\\">Nessun documento trovato con i filtri selezionati.</td></tr>";
    return;
  }
  sorted.forEach(function(d) {
    const tipo = (d.tipo_documento || "altro").replace(/\\s/g,"_");
    const tags = Array.isArray(d.tags) ? d.tags : [];
    const tagHtml = tags.slice(0,5).map(function(t) {
      return "<span class=\\"row-tag\\" onclick=\\"setTagFilter('" + t.replace(/'/g,"\\\\'") + "');\\">" + t + "</span>";
    }).join("");
    const filePath = "_metadata/" + d.file_stem + ".md";
    const tr = document.createElement("tr");
    if (selectedStems.has(d.file_stem)) tr.classList.add("selected");

    const tdSel = document.createElement("td");
    tdSel.className = "td-select";
    const cb = document.createElement("input");
    cb.type = "checkbox";
    cb.className = "cb-select";
    cb.checked = selectedStems.has(d.file_stem);
    cb.onchange = function() { toggleSelect(d.file_stem, tr, this.checked); };
    tdSel.appendChild(cb);
    tr.appendChild(tdSel);

    const tdDoc = document.createElement("td");
    const docLink = document.createElement("a");
    docLink.className = "doc-link";
    docLink.href = "#";
    docLink.textContent = d.title || d.file_stem;
    docLink.onclick = function(e) {
      e.preventDefault();
      if (serverAvailable) {
        fetch("http://127.0.0.1:5050/open?file=" + encodeURIComponent(filePath) + "&newLeaf=true").catch(function(){});
      } else {
        var vaultName = encodeURIComponent(VAULT_NAME);
        var file = encodeURIComponent(filePath);
        var a = document.createElement("a");
        a.href = "obsidian://open?vault=" + vaultName + "&file=" + file;
        a.target = "_blank";
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
      }
    };
    tdDoc.appendChild(docLink);
    tr.appendChild(tdDoc);

    const tdTipo = document.createElement("td");
    const badge = document.createElement("span");
    badge.className = "badge badge-" + tipo;
    badge.textContent = d.tipo_documento || "altro";
    tdTipo.appendChild(badge);
    tr.appendChild(tdTipo);

    const tdLingua = document.createElement("td");
    tdLingua.textContent = d.lingua || "-";
    tr.appendChild(tdLingua);

    const tdOrg = document.createElement("td");
    tdOrg.style.maxWidth = "160px";
    tdOrg.style.overflow = "hidden";
    tdOrg.style.textOverflow = "ellipsis";
    tdOrg.style.whiteSpace = "nowrap";
    const org = Array.isArray(d.organizzazioni)
      ? d.organizzazioni[0] || "-"
      : (String(d.organizzazioni || "").split(",")[0].trim() || "-");
    tdOrg.textContent = org;
    tdOrg.title = org;
    tr.appendChild(tdOrg);

    const tdDataDoc = document.createElement("td");
    tdDataDoc.textContent = fmtDate(d.data_documento);
    tr.appendChild(tdDataDoc);

    const tdDataCat = document.createElement("td");
    tdDataCat.textContent = fmtDate(d.data_catalogazione);
    tr.appendChild(tdDataCat);

    const tdTags = document.createElement("td");
    tags.slice(0,5).forEach(function(t) {
      const pill = document.createElement("span");
      pill.className = "row-tag";
      pill.textContent = t;
      pill.onclick = function() { setTagFilter(t); };
      tdTags.appendChild(pill);
    });
    tr.appendChild(tdTags);

    const tdAction = document.createElement("td");
    tdAction.className = "server-only";
    const btn = document.createElement("button");
    btn.className = "btn-reprocess";
    btn.textContent = "↺ Riprocessa";
    btn.onclick = function() {
      btn.textContent = "⏳ In corso...";
      btn.className = "btn-reprocess running";
      btn.disabled = true;
      reprocessingActive = true;
      (function showBannerNow() {
        var banner = document.getElementById("activity-banner");
        if (!banner) return;
        document.getElementById("activity-filename").textContent = d.file_stem;
        document.getElementById("activity-desc").textContent = "Avvio riprocessamento...";
        document.getElementById("activity-step").textContent = "STEP 0/5";
        var prog = document.getElementById("activity-progress");
        prog.innerHTML = "";
        for (var i = 1; i <= 5; i++) {
          var dot = document.createElement("div");
          dot.className = "activity-dot";
          prog.appendChild(dot);
        }
        banner.classList.add("visible");
      })();
      fetch("http://127.0.0.1:5050/status")
        .then(function(r){ return r.json(); })
        .then(function(s0) {
          var opBefore = s0.op;
          fetch("http://127.0.0.1:5050/reprocess?stem=" + encodeURIComponent(d.file_stem))
            .then(function(r){ return r.json(); })
            .then(function(data) {
              if (!data.ok) {
                btn.textContent = "✗ Errore";
                btn.className = "btn-reprocess";
                btn.disabled = false;
                reprocessingActive = false;
                return;
              }
              var poll = setInterval(function() {
                fetch("http://127.0.0.1:5050/status")
                  .then(function(r){ return r.json(); })
                  .then(function(s) {
                    if (s.op !== opBefore) {
                      clearInterval(poll);
                      reprocessingActive = false;
                      btn.textContent = "✓ Fatto";
                      btn.className = "btn-reprocess done";
                      setTimeout(function(){
                        window.location.href = "http://127.0.0.1:5050?t=" + Date.now();
                      }, 1500);
                    }
                  })
                  .catch(function(){ clearInterval(poll); reprocessingActive = false; btn.disabled = false; });
              }, 3000);
            })
            .catch(function() {
              btn.textContent = "✗ Errore";
              btn.className = "btn-reprocess";
              btn.disabled = false;
              reprocessingActive = false;
            });
        })
        .catch(function() {
          btn.textContent = "✗ Errore";
          btn.className = "btn-reprocess";
          btn.disabled = false;
          reprocessingActive = false;
        });
    };
    tdAction.appendChild(btn);
    tr.appendChild(tdAction);

    tbody.appendChild(tr);
  });
}

// ── DROPDOWNS ──
function buildDropdowns() {
  const tipi   = uniqueSorted(ALL_DOCS.map(function(d){return d.tipo_documento;}));
  const lingue = uniqueSorted(ALL_DOCS.map(function(d){return d.lingua;}));
  const orgs = uniqueSorted(ALL_DOCS.flatMap(function(d) {
    const raw = Array.isArray(d.organizzazioni)
      ? d.organizzazioni
      : String(d.organizzazioni || "").split(",").map(function(s){return s.trim();}).filter(Boolean);
    return raw.map(function(o){ return o.trim(); }).filter(Boolean);
  }));
  function fillSel(id, opts, placeholder) {
    const sel = document.getElementById(id);
    sel.innerHTML = "<option value=\\"\\">" + placeholder + "</option>";
    opts.forEach(function(o){sel.innerHTML += "<option value=\\""+o+"\\">"+o+"</option>";});
    sel.onchange = refresh;
  }
  fillSel("f-tipo",   tipi,   "Tutti i tipi");
  fillSel("f-lingua", lingue, "Tutte le lingue");
  fillSel("f-org",    orgs,   "Tutte le organizzazioni");
}

// ── HELPERS ──
function updateResultsPill(n) {
  document.getElementById("results-info").innerHTML =
    "<span>" + n + "</span> di " + ALL_DOCS.length + " doc.";
}

function refresh() {
  const f = getFiltered();
  renderStats(f);
  renderTable(f);
  updateResultsPill(f.length);
}

function resetFilters() {
  document.getElementById("f-search").value = "";
  document.getElementById("f-tipo").value = "";
  document.getElementById("f-lingua").value = "";
  document.getElementById("f-org").value = "";
  document.getElementById("f-date-from").value = "";
  document.getElementById("f-date-to").value = "";
  filterNoDate = false;
  activeTag = "";
  buildTagCloud();
  refresh();
}

function setTagFilter(tag) {
  activeTag = (activeTag === tag) ? "" : tag;
  buildTagCloud();
  const f = getFiltered();
  renderTable(f);
  renderStats(f);
  updateResultsPill(f.length);
}

// ── TOGGLE TAG CLOUD ──
function toggleTagCloud(h3) {
  const cloud = document.getElementById("tag-cloud");
  const arrow = h3.querySelector(".collapse-arrow");
  const collapsed = cloud.classList.toggle("collapsed");
  arrow.style.transform = collapsed ? "rotate(180deg)" : "";
  sessionStorage.setItem("tagCloudCollapsed", collapsed ? "1" : "0");
}

function confirmReprocessAll() {
  document.getElementById("modal-overlay").classList.add("visible");
  document.getElementById("modal-progress").textContent = "";
  document.getElementById("modal-cancel").disabled = false;
  document.getElementById("modal-confirm").disabled = false;
  document.getElementById("modal-confirm").textContent = "Sì, riprocessa tutto";
}

function closeModal() {
  document.getElementById("modal-overlay").classList.remove("visible");
}

function startReprocessAll() {
  const confirmBtn = document.getElementById("modal-confirm");
  const cancelBtn  = document.getElementById("modal-cancel");
  const progress   = document.getElementById("modal-progress");
  const allBtn     = document.querySelector(".btn-reprocess-all");

  confirmBtn.disabled = true;
  cancelBtn.disabled  = true;
  confirmBtn.textContent = "⏳ Avviato...";
  progress.textContent = "Riprocessamento avviato in background. La dashboard si aggiornerà al termine.";
  allBtn.className = "btn-reprocess-all running";
  allBtn.textContent = "⏳ In corso...";

  fetch("http://127.0.0.1:5050/reprocess_all")
    .then(function(r){ return r.json(); })
    .then(function(data) {
      if (data.ok) {
        progress.textContent = "✓ " + data.message;
        setTimeout(function(){ closeModal(); allBtn.className = "btn-reprocess-all"; allBtn.textContent = "⟳ Riprocessa tutti"; }, 3000);
      } else {
        progress.textContent = "✗ Errore: " + data.error;
        cancelBtn.disabled = false;
        allBtn.className = "btn-reprocess-all";
        allBtn.textContent = "⟳ Riprocessa tutti";
      }
    })
    .catch(function() {
      progress.textContent = "✗ Impossibile contattare il server.";
      cancelBtn.disabled = false;
      allBtn.className = "btn-reprocess-all";
      allBtn.textContent = "⟳ Riprocessa tutti";
    });
}

function shiftYear(delta) {
  const byMonth = {};
  ALL_DOCS.forEach(function(d) {
    const raw = d.data_documento || "";
    if (!raw || raw === "N/D" || raw === "-") return;
    let s = String(raw).trim();
    if (s.match(/^\\d{4}$/)) s = s + "-01";
    if (s.match(/^\\d{4}-\\d{2}/)) s = s.substring(0,7);
    if (s.match(/^\\d{4}-\\d{2}$/)) byMonth[s] = 1;
  });
  const years = [];
  Object.keys(byMonth).forEach(function(m){
    const y = parseInt(m.substring(0,4));
    if (years.indexOf(y) === -1) years.push(y);
  });
  years.sort();
  if (!years.length) return;
  if (timelineYear === null) {
    timelineYear = delta > 0 ? years[0] : years[years.length-1];
  } else {
    const idx = years.indexOf(timelineYear);
    const newIdx = idx + delta;
    if (newIdx < 0 || newIdx >= years.length) return;
    timelineYear = years[newIdx];
  }
  // Imposta il range date sull'intero anno
  document.getElementById("f-date-from").value = timelineYear + "-01-01";
  document.getElementById("f-date-to").value   = timelineYear + "-12-31";
  filterNoDate = false;
  buildCharts();
  refresh();
}

function resetYearFilter() {
  timelineYear = null;
  document.getElementById("f-date-from").value = "";
  document.getElementById("f-date-to").value   = "";
  filterNoDate = false;
  buildCharts();
  refresh();
}

// ── INIT ──
document.getElementById("f-search").oninput = refresh;
document.getElementById("f-date-from").oninput = refresh;
document.getElementById("f-date-to").oninput = refresh;

// ── SELEZIONE E ANALISI ──────────────────────────────────────
var selectedStems = new Set();

buildDropdowns();
buildCharts();
buildTagCloud();
refresh();

// Ripristina stato tag cloud
(function() {
  var collapsed = sessionStorage.getItem("tagCloudCollapsed");
  if (collapsed === null) collapsed = "1";
  if (collapsed === "1") {
    var cloud = document.getElementById("tag-cloud");
    var arrow = document.querySelector(".collapse-arrow");
    if (cloud) cloud.classList.add("collapsed");
    if (arrow) arrow.style.transform = "rotate(180deg)";
  }
})();

// ── AUTO-RELOAD su operazioni completate ──
var lastOpCount = null;
var reprocessingActive = false;

function toggleSelect(stem, tr, checked) {
  if (checked) { selectedStems.add(stem); tr.classList.add("selected"); }
  else          { selectedStems.delete(stem); tr.classList.remove("selected"); }
  updateSelectionBar();
}

function toggleSelectAll(checked) {
  var trs = document.querySelectorAll("#tbody tr");
  var cbs = document.querySelectorAll("#tbody .cb-select");
  var rows = document.querySelectorAll("#tbody tr");
  // Recupera gli stem visibili
  ALL_DOCS.forEach(function(d) {
    var f = getFiltered();
    if (f.some(function(x){return x.file_stem===d.file_stem;})) {
      if (checked) selectedStems.add(d.file_stem);
      else selectedStems.delete(d.file_stem);
    }
  });
  renderTable(getFiltered());
  updateSelectionBar();
}

function clearSelection() {
  selectedStems.clear();
  renderTable(getFiltered());
  updateSelectionBar();
}

function updateSelectionBar() {
  var n = selectedStems.size;
  var bar = document.getElementById("analysis-bar");
  var cnt = document.getElementById("sel-count");
  cnt.textContent = n + (n===1 ? " documento selezionato" : " documenti selezionati");
  if (n > 0) bar.classList.add("visible");
  else bar.classList.remove("visible");
}

function analyzeSelected() {
  if (selectedStems.size === 0) return;
  document.getElementById("modal-doc-count").textContent = selectedStems.size;
  var ta = document.getElementById("analysis-custom-prompt");
  if (ta) ta.value = "";
  document.querySelectorAll("#analysis-modal-overlay input[type=checkbox]").forEach(function(cb){ cb.checked = false; });
  document.getElementById("analysis-progress").textContent = "";
  document.getElementById("analysis-modal-overlay").classList.add("visible");
}

function closeAnalysisModal() {
  document.getElementById("analysis-modal-overlay").classList.remove("visible");
}

function downloadZip() {
  var stems = Array.from(selectedStems);
  if (!stems.length) return;
  fetch("http://127.0.0.1:5050/download_zip", {
    method: "POST",
    headers: {"Content-Type": "application/json"},
    body: JSON.stringify({stems: stems})
  }).then(function(r) {
    if (!r.ok) { alert("Errore durante la creazione dello zip."); return null; }
    return r.blob();
  }).then(function(blob) {
    if (!blob) return;
    var url = URL.createObjectURL(blob);
    var a = document.createElement("a");
    a.href = url;
    a.download = stems.length === 1 ? stems[0] + ".zip" : "documenti.zip";
    document.body.appendChild(a); a.click(); document.body.removeChild(a);
    URL.revokeObjectURL(url);
  }).catch(function(e) { console.error(e); });
}

function openWithApp() {
  var stems = Array.from(selectedStems);
  if (!stems.length) return;
  fetch("http://127.0.0.1:5050/open_files", {
    method: "POST",
    headers: {"Content-Type": "application/json"},
    body: JSON.stringify({stems: stems})
  }).catch(function(e) { console.error(e); });
}

function shareWhatsapp() {
  var stems = Array.from(selectedStems);
  if (!stems.length) return;
  fetch("http://127.0.0.1:5050/share_whatsapp", {
    method: "POST",
    headers: {"Content-Type": "application/json"},
    body: JSON.stringify({stems: stems})
  }).then(function(r){ return r.json(); })
  .then(function(d){ if (d.toast) showToast(d.toast); })
  .catch(function(e){ console.error(e); });
}

function shareFiles() {
  var stems = Array.from(selectedStems);
  if (!stems.length) return;
  fetch("http://127.0.0.1:5050/share_files", {
    method: "POST",
    headers: {"Content-Type": "application/json"},
    body: JSON.stringify({stems: stems})
  }).catch(function(e) { console.error(e); });
}

function showToast(msg) {
  var t = document.getElementById("pkm-toast");
  if (!t) {
    t = document.createElement("div");
    t.id = "pkm-toast";
    t.className = "pkm-toast";
    document.body.appendChild(t);
  }
  t.textContent = msg;
  t.classList.add("visible");
  clearTimeout(t._timer);
  t._timer = setTimeout(function(){ t.classList.remove("visible"); }, 5000);
}

function launchAnalysis() {
  var btn = document.getElementById("btn-start-analysis");
  if (btn) { btn.disabled = true; btn.textContent = "⏳ Invio..."; }
  var progress = document.getElementById("analysis-progress");
  if (progress) progress.textContent = "Avvio analisi in corso...";

  var customPrompt = (document.getElementById("analysis-custom-prompt") || {}).value || "";
  var focusItems = [];
  document.querySelectorAll("#analysis-modal-overlay input[type=checkbox]:checked").forEach(function(cb){
    focusItems.push(cb.value);
  });

  fetch("http://127.0.0.1:5050/analyze_selection", {
    method: "POST",
    headers: {"Content-Type":"application/json"},
    body: JSON.stringify({
      stems: Array.from(selectedStems),
      custom_prompt: customPrompt.trim(),
      focus_items: focusItems,
    })
  })
  .then(function(r){ return r.json(); })
  .then(function(data) {
    if (btn) { btn.disabled = false; btn.textContent = "⬡ Avvia analisi"; }
    if (data.ok) {
      if (progress) progress.textContent = "✓ Analisi avviata — il report si aprirà in Obsidian al termine.";
      setTimeout(function(){ closeAnalysisModal(); clearSelection(); }, 2500);
    } else {
      if (progress) progress.textContent = "✗ Errore: " + (data.error || "sconosciuto");
    }
  })
  .catch(function() {
    if (btn) { btn.disabled = false; btn.textContent = "⬡ Avvia analisi"; }
    if (progress) progress.textContent = "✗ Errore di connessione al server.";
  });
}

function openAnalysisModal() { analyzeSelected(); }
function toggleTopic(el) { el.classList.toggle("selected"); }

var serverAvailable = false;

// Rileva se il server Flask è disponibile (funziona solo su Mac locale)
function detectServer() {
  fetch("http://127.0.0.1:5050/status", { signal: AbortSignal.timeout(2000) })
    .then(function(r){ return r.json(); })
    .then(function(data) {
      serverAvailable = true;
      lastOpCount = data.op;
      document.querySelectorAll(".server-only").forEach(function(el){ el.style.display = ""; });
      var ua = document.getElementById("upload-area");
      if (ua) ua.classList.add("online");
      setInterval(pollStatus, 3000);
      setInterval(pollActivity, 500);
    })
    .catch(function() {
      serverAvailable = false;
      // Nascondi controlli che richiedono il server
      document.querySelectorAll(".server-only").forEach(function(el){ el.style.display = "none"; });
      // Mostra avviso solo su dispositivi non-Mac
      if (!navigator.userAgent.includes("Macintosh")) {
        var banner = document.getElementById("offline-banner");
        if (banner) banner.style.display = "flex";
      }
    });
}

function pollStatus() {
  if (!serverAvailable) return;
  fetch("http://127.0.0.1:5050/status")
    .then(function(r){ return r.json(); })
    .then(function(data) {
      if (lastOpCount === null) {
        lastOpCount = data.op;
      } else if (data.op !== lastOpCount) {
        lastOpCount = data.op;
        if (!reprocessingActive) {
          window.location.href = "http://127.0.0.1:5050?t=" + Date.now();
        }
      }
    })
    .catch(function(){});
}

function pollActivity() {
  if (!serverAvailable) return;
  fetch("http://127.0.0.1:5050/activity")
    .then(function(r){ return r.json(); })
    .then(function(data) {
      var banner = document.getElementById("activity-banner");
      if (!banner) return;
      if (data && data.filename) {
        document.getElementById("activity-filename").textContent = data.filename;
        document.getElementById("activity-desc").textContent = data.description || "";
        document.getElementById("activity-step").textContent =
          "STEP " + data.step + "/" + data.total_steps;
        var prog = document.getElementById("activity-progress");
        prog.innerHTML = "";
        for (var i = 1; i <= data.total_steps; i++) {
          var dot = document.createElement("div");
          dot.className = "activity-dot" +
            (i < data.step ? " done" : i === data.step ? " active" : "");
          prog.appendChild(dot);
        }
        banner.classList.add("visible");
      } else if (!reprocessingActive) {
        banner.classList.remove("visible");
      }
    })
    .catch(function(){});
}
// ── UPLOAD DRAG & DROP ───────────────────────────────────────
(function() {
  var area = document.getElementById("upload-area");
  if (!area) return;

  function setStatus(msg, cls) {
    var s = document.getElementById("upload-status");
    if (!s) return;
    s.textContent = msg;
    s.className = "upload-status" + (cls ? " " + cls : "");
  }

  function uploadFile(file) {
    setStatus("Caricamento...", "uploading");
    var fd = new FormData();
    fd.append("file", file);
    fetch("http://127.0.0.1:5050/upload", { method: "POST", body: fd })
      .then(function(r){ return r.json(); })
      .then(function(data) {
        if (data.ok) {
          if (data.duplicate && data.duplicate_where === "archivio") {
            setStatus("⚠ già archiviato → _remove/", "err");
            setTimeout(function(){ setStatus("→ _inbox/"); }, 6000);
          } else if (data.duplicate && data.duplicate_where === "inbox") {
            setStatus("⚠ già in inbox → " + data.filename, "err");
            setTimeout(function(){ setStatus("→ _inbox/"); }, 6000);
          } else {
            setStatus("✓ " + data.filename, "ok");
            setTimeout(function(){ setStatus("→ _inbox/"); }, 4000);
          }
        } else {
          setStatus("✗ " + (data.error || "errore"), "err");
        }
      })
      .catch(function(){ setStatus("✗ connessione fallita", "err"); });
  }

  area.addEventListener("dragover", function(e) {
    e.preventDefault();
    area.classList.add("drag-over");
  });
  area.addEventListener("dragleave", function() {
    area.classList.remove("drag-over");
  });
  area.addEventListener("drop", function(e) {
    e.preventDefault();
    area.classList.remove("drag-over");
    var files = e.dataTransfer.files;
    if (files.length === 0) return;
    if (files.length === 1) {
      uploadFile(files[0]);
    } else {
      // Upload multiplo sequenziale
      setStatus("Caricamento " + files.length + " file...", "uploading");
      var i = 0;
      function next() {
        if (i >= files.length) {
          setStatus("✓ " + files.length + " file caricati", "ok");
          setTimeout(function(){ setStatus("→ _inbox/"); }, 4000);
          return;
        }
        var fd = new FormData();
        fd.append("file", files[i]);
        fetch("http://127.0.0.1:5050/upload", { method: "POST", body: fd })
          .then(function(){ i++; next(); })
          .catch(function(){ setStatus("✗ errore file " + (i+1), "err"); });
      }
      next();
    }
  });
})();

detectServer();

// ── RELOAD ──

</script>
</div><!-- lcars-main -->
</div><!-- lcars-layout -->

<!-- MODALE ANALISI -->
<div class="analysis-modal-overlay" id="analysis-modal-overlay">
  <div class="analysis-modal">
    <h3>⬡ Analisi documenti</h3>
    <div class="modal-sub" id="analysis-modal-sub"></div>

    <label>Richiesta specifica (opzionale)</label>
    <textarea id="analysis-request" placeholder="Es: confronta i requisiti normativi, identifica le date chiave, analizza i rischi..."></textarea>

    <label>Argomenti specifici da analizzare</label>
    <div class="analysis-topics" id="analysis-topics">
      <div class="topic-chip" onclick="toggleTopic(this)" data-topic="persone">Persone</div>
      <div class="topic-chip" onclick="toggleTopic(this)" data-topic="date">Date</div>
      <div class="topic-chip" onclick="toggleTopic(this)" data-topic="territori">Territori</div>
      <div class="topic-chip" onclick="toggleTopic(this)" data-topic="eventi">Eventi</div>
      <div class="topic-chip" onclick="toggleTopic(this)" data-topic="leggi e normative">Leggi / Normative</div>
      <div class="topic-chip" onclick="toggleTopic(this)" data-topic="aziende">Aziende</div>
      <div class="topic-chip" onclick="toggleTopic(this)" data-topic="tecnologie">Tecnologie</div>
      <div class="topic-chip" onclick="toggleTopic(this)" data-topic="prodotti">Prodotti</div>
      <div class="topic-chip" onclick="toggleTopic(this)" data-topic="dati numerici e statistiche">Dati numerici</div>
      <div class="topic-chip" onclick="toggleTopic(this)" data-topic="rischi">Rischi</div>
    </div>

    <div class="analysis-modal-btns">
      <button class="btn-analysis-cancel" onclick="closeAnalysisModal()">Annulla</button>
      <button class="btn-analysis-go" onclick="launchAnalysis()">▶ Avvia analisi</button>
    </div>
  </div>
</div>

<div class="lcars-footer">
  <span class="lcars-footer-text">■ ■ ■</span>
  <span class="lcars-footer-text">STARFLEET ARCHIVE SYSTEM · PKM AGENT v2.2__ENV_BADGE__</span>
  <span class="lcars-footer-text">■ ■ ■</span>
</div>

</body>
</html>"""
