"""
Linker semantico — usa Claude per trovare i 3 documenti
più affini al nuovo file catalogato e crea wikilink
bidirezionali nei file .md di entrambi.
"""

import json
import logging
import re
from pathlib import Path

import anthropic

from config import Config
from api_utils import call_with_retry

log = logging.getLogger(__name__)
client = anthropic.Anthropic()

MAX_LINKS    = 10
SECTION_TAG  = "## 🔗 Documenti Correlati"
MARKER_START = "<!-- RELATED_START -->"
MARKER_END   = "<!-- RELATED_END -->"


# ── Entrypoint ───────────────────────────────────────────────────────

def link(new_stem: str, new_meta: dict) -> dict:
    """
    Trova i documenti più affini a new_stem e aggiorna
    i wikilink in entrambi i file.
    Restituisce dict con token usati.
    """
    candidates = _load_candidates(new_stem)
    if not candidates:
        log.info("  ℹ Nessun documento esistente da collegare.")
        return {}

    related, tokens = _find_related(new_stem, new_meta, candidates)
    if not related:
        log.info("  ℹ Nessun documento sufficientemente affine trovato.")
        return tokens

    log.info(f"  🔗 Collegati {len(related)} documenti affini:")
    for r in related:
        log.info(f"     · {r['stem']} (score {r['score']}/10 — {r['reason']})")

    # Aggiorna il nuovo file
    _update_links(new_stem, related)

    # Aggiorna i file correlati (link bidirezionale)
    back_ref = [{
        "stem":   new_stem,
        "title":  new_meta.get("title", new_stem),
        "reason": r["reason"],
        "score":  r["score"],
    } for r in related]

    for i, r in enumerate(related):
        _update_links(r["stem"], [back_ref[i]])

    return tokens


# ── Caricamento candidati ────────────────────────────────────────────

def _load_candidates(exclude_stem: str) -> list[dict]:
    """
    Legge tutti i .md in _metadata e restituisce una lista di
    {stem, title, sommario, tags, topics} per il confronto AI.
    """
    candidates = []
    for md_file in Config.METADATA_DIR.glob("*.md"):
        if md_file.stem in (exclude_stem, "_INDEX", "DASHBOARD"):
            continue
        try:
            info = _extract_summary_info(md_file)
            if info:
                candidates.append(info)
        except Exception:
            pass
    return candidates


def _extract_summary_info(path: Path) -> dict:
    text = path.read_text(encoding="utf-8", errors="replace")
    if not text.startswith("---"):
        return {}
    end = text.find("---", 3)
    if end == -1:
        return {}
    fm = text[3:end]

    info = {"stem": path.stem}
    for line in fm.splitlines():
        line = line.strip()
        if not line or ":" not in line:
            continue
        key, _, val = line.partition(":")
        key = key.strip()
        val = val.strip().strip('"').strip("'")
        if key in ("title", "sommario", "tipo_documento", "lingua"):
            info[key] = val

    info["tags"]      = _parse_yaml_list(fm, "tags")
    info["argomenti"] = _parse_yaml_list(fm, "argomenti")

    # Leggi sezione estratto dal corpo del documento
    estratto = _extract_body_section(text, "📄 Estratto documento")
    if estratto:
        info["estratto_documento"] = estratto

    return info


def _extract_body_section(text: str, section_title: str) -> str:
    """Estrae il contenuto di una sezione ## dal corpo del documento.
    Si ferma solo al prossimo ## (stesso livello), non ai ### interni."""
    marker = f"## {section_title}"
    idx = text.find(marker)
    if idx == -1:
        return ""
    start = text.find("\n", idx) + 1
    # Fine sezione = prossimo ## dello stesso livello (non ###) o ---
    search_from = start
    end_idx = len(text)
    while True:
        next_h2 = text.find("\n## ", search_from)
        next_sep = text.find("\n---", search_from)
        candidates = [i for i in [next_h2, next_sep] if i != -1]
        if not candidates:
            break
        end_idx = min(candidates)
        break
    content = text[start:end_idx].strip()
    return content


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


# ── Analisi AI ───────────────────────────────────────────────────────

def _find_related(new_stem: str, new_meta: dict, candidates: list[dict]) -> list[dict]:
    """
    Chiede a Claude di identificare i MAX_LINKS documenti più affini.
    Restituisce lista di {stem, title, score, reason}.
    """
    new_estratto = new_meta.get("estratto_documento", "") or new_meta.get("summary", "")
    if not new_estratto or len(new_estratto) < 50:
        log.info("  ℹ Linker saltato: nessun estratto disponibile per il documento corrente.")
        return [], {}

    new_summary = {
        "title":    new_meta.get("title", new_stem),
        "estratto": new_estratto[:600],   # ridotto da intero a 600 caratteri
        "tags":     new_meta.get("tags", [])[:8],
        "topics":   new_meta.get("topics", [])[:5],
        "tipo":     new_meta.get("document_type", ""),
    }

    # Prepara lista candidati — usa solo quelli con estratto o sommario significativo
    # Pre-filtro locale: priorità ai documenti con tag/argomenti in comune
    new_tags   = set(t.lower() for t in new_meta.get("tags", []))
    new_topics = set(t.lower() for t in new_meta.get("topics", []))
    new_tipo   = new_meta.get("document_type", "")

    scored_candidates = []
    skipped = 0
    for c in candidates:
        estratto = c.get("estratto_documento", "") or c.get("sommario", "")
        if not estratto or len(estratto) < 50:
            skipped += 1
            continue
        # Calcola overlap locale (non costa nulla)
        c_tags   = set(t.lower() for t in c.get("tags", []))
        c_topics = set(t.lower() for t in c.get("argomenti", []))
        overlap  = len(new_tags & c_tags) + len(new_topics & c_topics)
        # Penalizza candidati con tipo identico e nessun overlap (evita link solo per tipo)
        if overlap == 0 and c.get("tipo_documento") == new_tipo:
            overlap = -1
        scored_candidates.append((overlap, c, estratto))

    if skipped:
        log.info(f"  ℹ {skipped} candidati saltati (nessun estratto disponibile)")

    # Ordina per overlap decrescente e prendi i migliori 35
    scored_candidates.sort(key=lambda x: x[0], reverse=True)
    MAX_CANDIDATES = 35
    top_candidates = scored_candidates[:MAX_CANDIDATES]

    log.info(f"  ℹ {len(top_candidates)} candidati selezionati su {len(scored_candidates)} totali")

    cands_compact = []
    for _, c, estratto in top_candidates:
        cands_compact.append({
            "stem":     c["stem"],
            "title":    c.get("title", c["stem"]),
            "estratto": estratto[:400],   # ridotto da 800 a 400 caratteri
            "tags":     c.get("tags", [])[:6],
            "topics":   c.get("argomenti", [])[:4],
        })

    prompt = (
        "Hai un documento appena catalogato e una lista di documenti esistenti.\n"
        "Identifica i " + str(MAX_LINKS) + " documenti esistenti semanticamente più affini.\n\n"
        "CRITERI DI SCORING — sii molto rigoroso:\n"
        "  10: stesso argomento specifico, stessa tecnologia/prodotto, contenuto quasi sovrapposto\n"
        "   9: argomento molto simile, stessa area tecnica, riferimenti incrociati evidenti\n"
        "   8: correlazione chiara e specifica, non generica (es. stesso settore industriale + stessa tecnologia)\n"
        "   7: correlazione presente ma indiretta o parziale\n"
        "  ≤6: NON includere — correlazione troppo vaga, generica o solo di tipo (es. entrambi 'report')\n\n"
        "IMPORTANTE: preferisci NON includere un documento piuttosto che assegnargli uno score alto per genericità.\n"
        "Se non ci sono documenti con score ≥ 8, restituisci una lista vuota [].\n\n"
        "Rispondi SOLO con JSON valido, nessun testo aggiuntivo:\n"
        "[\n"
        "  {\"stem\": \"nome_file\", \"title\": \"titolo\", \"score\": 9, \"reason\": \"motivazione specifica in italiano (max 10 parole)\"}\n"
        "]\n\n"
        "DOCUMENTO NUOVO:\n" + json.dumps(new_summary, ensure_ascii=False) + "\n\n"
        "DOCUMENTI ESISTENTI:\n" + json.dumps(cands_compact, ensure_ascii=False)
    )

    try:
        response = call_with_retry(
            client.messages.create,
            model=Config.CLAUDE_MODEL_FAST,  # Haiku: sufficiente per confronto semantico
            max_tokens=2000,
            messages=[{"role": "user", "content": prompt}],
        )
        raw = response.content[0].text.strip()
        raw = raw.removeprefix("```json").removeprefix("```").removesuffix("```").strip()

        # Se il JSON è troncato, tenta di recuperare le voci complete
        if not raw.endswith("]"):
            # Taglia all'ultima voce completa (chiusa con })
            last_brace = raw.rfind("}")
            if last_brace != -1:
                raw = raw[:last_brace + 1] + "]"
            else:
                raw = "[]"
        results = json.loads(raw)

        # Valida e filtra score >= 6
        valid = []
        stems_existing = {c["stem"] for c in candidates}
        for r in results:
            if (
                isinstance(r, dict)
                and r.get("stem") in stems_existing
                and isinstance(r.get("score"), int)
                and r["score"] >= 8
            ):
                valid.append(r)
        valid.sort(key=lambda x: x.get("score", 0), reverse=True)
        tokens = {
            "_linker_tokens_input":  response.usage.input_tokens,
            "_linker_tokens_output": response.usage.output_tokens,
        }
        return valid[:MAX_LINKS], tokens

    except Exception as e:
        log.warning(f"  ⚠ Errore linker AI: {e}")
        return [], {}


# ── Aggiornamento file .md ───────────────────────────────────────────

# Pattern per estrarre score da riga tabella
_SCORE_RE = re.compile(r"\|\s*(\d+)/10\s*\|?\s*$")
_STEM_RE  = re.compile(r"\[\[([^\]|\\]+)")


def _row_score(line: str) -> int:
    m = _SCORE_RE.search(line)
    return int(m.group(1)) if m else 0


def _update_links(stem: str, new_links: list[dict]):
    """
    Aggiunge i nuovi link alla sezione Documenti Correlati,
    rimuove duplicati e riordina per score decrescente.
    """
    md_path = Config.METADATA_DIR / (stem + ".md")
    if not md_path.exists():
        return

    content = md_path.read_text(encoding="utf-8")

    # Leggi stem già presenti (normalizzati)
    existing_stems = _get_existing_link_stems(content)

    # Filtra solo i nuovi, deduplicando per stem normalizzato
    seen_stems = set(s.strip().lower() for s in existing_stems)
    to_add = []
    for lnk in new_links:
        key = lnk["stem"].strip().lower()
        if key not in seen_stems:
            seen_stems.add(key)
            to_add.append(lnk)

    if not to_add and MARKER_START in content:
        content = _dedup_and_sort_section(content)
        md_path.write_text(content, encoding="utf-8")
        return

    if not to_add:
        return

    # Costruisci righe nuove
    new_rows = []
    for lnk in to_add:
        reason = lnk.get("reason", "")
        score  = lnk.get("score", "")
        new_rows.append(
            f"| [[{lnk['stem']}\\|{lnk.get('title', lnk['stem'])}]] "
            f"| {reason} | {score}/10 |"
        )

    # Aggiorna o crea la sezione
    if MARKER_START in content:
        content = content.replace(
            MARKER_END,
            "\n".join(new_rows) + "\n" + MARKER_END,
        )
    else:
        section = (
            "\n---\n\n"
            + SECTION_TAG + "\n\n"
            + MARKER_START + "\n"
            + "| Documento | Affinità | Score |\n"
            + "|-----------|----------|-------|\n"
            + "\n".join(new_rows) + "\n"
            + MARKER_END + "\n"
        )
        sig = "*Catalogato automaticamente"
        if sig in content:
            content = content.replace(
                content[content.rfind("\n---\n"):],
                section + content[content.rfind("\n---\n"):],
            )
        else:
            content = content.rstrip() + "\n" + section

    # Deduplica e riordina per score decrescente
    content = _dedup_and_sort_section(content)
    md_path.write_text(content, encoding="utf-8")
    log.info(f"     ✓ Aggiornato: {stem}.md (+{len(to_add)} link)")


def _dedup_and_sort_section(content: str) -> str:
    """
    Nella sezione Documenti Correlati:
    - rimuove righe duplicate (stesso stem, tenendo quella con score più alto)
    - riordina per score decrescente
    """
    start = content.find(MARKER_START)
    end   = content.find(MARKER_END)
    if start == -1 or end == -1:
        return content

    section = content[start + len(MARKER_START):end]
    lines   = section.splitlines()

    structural = []
    data_lines = []
    for line in lines:
        stripped = line.strip()
        if not stripped or stripped.startswith("|---") or stripped.startswith("| Documento"):
            structural.append(line)
        elif stripped.startswith("|"):
            data_lines.append(line)
        else:
            structural.append(line)

    # Deduplica per stem: tieni la riga con score più alto
    seen: dict[str, tuple[int, str]] = {}  # stem_lower -> (score, line)
    for line in data_lines:
        stems_found = re.findall(r"\[\[([^\]|]+)", line)
        stem_key = stems_found[0].strip().lower() if stems_found else line[:40].lower()
        score = _row_score(line)
        if stem_key not in seen or score > seen[stem_key][0]:
            seen[stem_key] = (score, line)

    deduped = [v[1] for v in seen.values()]
    deduped.sort(key=_row_score, reverse=True)

    new_section = "\n".join(structural + deduped)
    return (
        content[:start + len(MARKER_START)]
        + new_section + "\n"
        + content[end:]
    )


def _get_existing_link_stems(content: str) -> set:
    """Estrae gli stem dei wikilink già presenti nella sezione correlati."""
    if MARKER_START not in content:
        return set()
    start = content.find(MARKER_START)
    end   = content.find(MARKER_END)
    section = content[start:end]
    return set(re.findall(r"\[\[([^\]|]+)", section))
