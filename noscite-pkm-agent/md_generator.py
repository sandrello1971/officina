"""
Generazione file .md per Obsidian con frontmatter YAML,
corpo strutturato e wikilink per il grafo.
"""

import re
from datetime import datetime
from pathlib import Path


def generate(filename: str, meta: dict, tech_meta: dict, translation_stem: str = None, safe_stem: str = None, full_text: str = "") -> str:
    """
    Genera il contenuto completo del file .md dei metadati.
    """
    now = datetime.now().strftime("%Y-%m-%d %H:%M")
    today = datetime.now().strftime("%Y-%m-%d")
    tags = meta.get("tags", [])
    topics = meta.get("topics", [])
    entities = meta.get("entities", {})

    # ── FRONTMATTER YAML ────────────────────────────────────────────
    frontmatter_tags   = "\n".join(f"  - {_slugify(t)}" for t in tags)
    frontmatter_topics = "\n".join(f"  - \"{t}\"" for t in topics)

    # Entità
    entities     = meta.get("entities", {})
    ent_persons  = ", ".join(entities.get("persons", [])) or "N/D"
    ent_orgs     = ", ".join(entities.get("organizations", [])) or "N/D"
    ent_places   = ", ".join(entities.get("places", [])) or "N/D"

    # Metadati tecnici opzionali
    file_size  = tech_meta.get("file_size", "N/D")
    extra_lines = []
    if tech_meta.get("pages"):    extra_lines.append(f"pagine: {tech_meta['pages']}")
    if tech_meta.get("slides"):   extra_lines.append(f"slide: {tech_meta['slides']}")
    if tech_meta.get("sheets"):   extra_lines.append(f"fogli: {tech_meta['sheets']}")
    if tech_meta.get("duration_hms"): extra_lines.append(f"durata: \"{tech_meta['duration_hms']}\"")
    if tech_meta.get("token_analisi"): extra_lines.append(f'token_analisi: "{tech_meta["token_analisi"]}"')
    if tech_meta.get("token_linker"):  extra_lines.append(f'token_linker: "{tech_meta["token_linker"]}"')
    if translation_stem: extra_lines.append(f'traduzione_it: "[[{translation_stem}]]"')
    extra_block = ("\n" + "\n".join(extra_lines)) if extra_lines else ""

    keywords_yaml = ", ".join(f'"{k}"' for k in meta.get("keywords", []))

    frontmatter = f"""---
title: "{_escape_yaml(meta.get('title', filename))}"
file_originale: "[[_archive/{filename}]]"
file_nome: "{filename}"
file_stem: "{safe_stem if safe_stem is not None else Path(filename).stem}"
data_documento: {meta.get('document_date', 'N/D')}
data_catalogazione: {today}
tipo_documento: {meta.get('document_type', 'altro')}
lingua: {meta.get('language', 'N/D')}
sentiment: {meta.get('sentiment', 'neutro')}
complessita: {meta.get('complexity', 'N/D')}
dimensione_file: "{file_size}"{extra_block}
sommario: "{_escape_yaml(meta.get('summary', 'N/D'))}"
persone: "{ent_persons}"
organizzazioni: "{ent_orgs}"
luoghi: "{ent_places}"
parole_chiave: [{keywords_yaml}]
tags:
{frontmatter_tags if frontmatter_tags else '  - non_catalogato'}
argomenti:
{frontmatter_topics if frontmatter_topics else '  - N/D'}
created: {today}
---"""

    # ── CORPO DEL DOCUMENTO ─────────────────────────────────────────

    # 1. Titolo e sommario
    title = meta.get("title", filename)
    summary = meta.get("summary", "N/D")

    # 2. Metadati tecnici
    tech_lines = "\n".join(
        f"| {k.replace('_', ' ').title()} | {v} |"
        for k, v in tech_meta.items()
        if v not in (None, "", {})
    )

    # 3. Parole chiave
    keywords = meta.get("keywords", [])
    keywords_str = "  ".join(f"`{k}`" for k in keywords) if keywords else "_nessuna_"

    # 4. Entità
    entity_sections = []
    entity_labels = {
        "persons": "👤 Persone",
        "organizations": "🏢 Organizzazioni",
        "places": "📍 Luoghi",
        "dates": "📅 Date",
        "products": "📦 Prodotti",
    }
    for key, label in entity_labels.items():
        items = entities.get(key, [])
        if items:
            entity_sections.append(f"**{label}:** {', '.join(items)}")
    entities_block = "\n".join(entity_sections) if entity_sections else "_nessuna entità rilevata_"


    body = f"""# {title}

> {summary}

---

## 📋 Informazioni Generali

| Campo | Valore |
|-------|--------|
| **File originale** | [[_archive/{filename}]] |
| **Tipo documento** | {meta.get('document_type', 'N/D')} |
| **Data documento** | {meta.get('document_date', 'N/D')} |
| **Lingua** | {meta.get('language', 'N/D')} |
| **Tono/Sentiment** | {meta.get('sentiment', 'N/D')} |
| **Complessità** | {meta.get('complexity', 'N/D')} |
| **Catalogato il** | {today} |{f"{chr(10)}| **Traduzione IT** | [[{translation_stem}]] |" if translation_stem else ""}

---

## 🔧 Metadati Tecnici

| Proprietà | Valore |
|-----------|--------|
{tech_lines if tech_lines else "| — | — |"}

---

## 🏷️ Parole Chiave

{keywords_str}

---

## 🔍 Entità Rilevate

{entities_block}

---
{f"""
## 📄 Estratto documento

{meta.get('estratto_documento', '')}

---
""" if meta.get('estratto_documento') else ''}
{f"""
## 📜 Trascrizione letterale

{full_text}

---
""" if full_text and full_text.strip() else ''}
*Catalogato automaticamente da Obsidian AI Metadata Agent — {now}*
"""

    return frontmatter + "\n\n" + body


# ── UTILITY ──────────────────────────────────────────────────────────

def _slugify(text: str) -> str:
    """Converti stringa in slug per wikilink e tag."""
    text = text.lower().strip()
    text = re.sub(r"\s+", "_", text)
    text = re.sub(r"[^\w]", "", text)
    return text


def _escape_yaml(text: str) -> str:
    return text.replace('"', '\\"')
