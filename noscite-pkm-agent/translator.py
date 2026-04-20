"""
translator.py — Traduce documenti in inglese e genera file _IT.md
con riassunto esaustivo + testo completo tradotto.
"""

import logging
import anthropic
from pathlib import Path
from config import Config
from api_utils import call_with_retry

log = logging.getLogger(__name__)
client = anthropic.Anthropic()

CHUNK_SIZE = 4000  # caratteri per chunk di traduzione

SYSTEM_PROMPT_SUMMARY = """Sei un assistente specializzato nella sintesi di documenti.
Produci un riassunto esaustivo e strutturato del documento fornito.
Il riassunto deve essere in italiano, completo e ben organizzato per punti principali.
Rispondi SOLO con il riassunto, senza commenti o introduzioni."""

SYSTEM_PROMPT_STRUCTURE = """Sei un esperto nella formattazione di documenti tecnici.
Ricevi testo grezzo estratto da un PDF (senza formattazione markdown) e devi riconoscere la struttura implicita e convertirla in markdown.

REGOLE:
- Testo in MAIUSCOLO o con numeri progressivi (es. "1. INTRODUZIONE", "Chapter 2") → converti in titolo ## o ###
- Righe brevi isolate che sembrano intestazioni → converti in titolo appropriato
- Elenchi impliciti (righe che iniziano con •, -, *, numeri, lettere) → converti in liste markdown
- Dati in colonne o tabelle implicite → converti in tabella markdown con |
- Paragrafi separati da righe vuote → mantieni la separazione
- Testo normale → lascialo invariato
- NON tradurre, NON riassumere, NON aggiungere contenuto
- Rispondi SOLO con il testo strutturato in markdown"""

SYSTEM_PROMPT_TRANSLATE = """Sei un traduttore professionale italiano specializzato in documenti tecnici e report.
Il testo che ricevi è già formattato in markdown con titoli, elenchi e tabelle.
Traduci il testo dall'inglese all'italiano in modo fluente e naturale.
Rispondi SOLO con il testo tradotto, senza commenti, note o introduzioni.

REGOLE DI FORMATTAZIONE — obbligatorie:
- Titoli # ## ### ####: conserva ESATTAMENTE il livello, traduci il testo
- Elenchi - * 1.: conserva il simbolo, traduci il contenuto, mantieni il **grassetto**
- Tabelle |: conserva la struttura, traduci intestazioni e celle
- Grassetto **testo** e corsivo *testo*: conserva i marcatori
- Blockquote >: conserva il simbolo, traduci il contenuto
- Separatori ---: mantieni invariati
- Numeri, %, sigle (AI, UE, PMI, ROI, GVA, FMI, ecc.): NON tradurre
- Righe vuote tra sezioni: conservale"""


def translate(filename: str, text: str, meta: dict, tech_meta: dict, safe_stem: str = None,
              return_content: bool = False) -> dict | None:
    """
    Genera il file _IT.md se il documento è in inglese.
    Restituisce dict con token usati e translation_stem, o None se non applicabile.

    Se return_content=True, NON scrive su filesystem e ritorna anche 'translation_content'
    (per upload via Graph in SharePoint edition).
    """
    language = meta.get("language", "")
    if language not in ("en", "en+it"):
        return None
    if not text or len(text) < 100:
        log.warning(f"    ⚠ Testo troppo corto per tradurre ({len(text) if text else 0} car.) — possibile estrazione PDF fallita")
        return None

    log.info(f"    📄 Testo da tradurre: {len(text):,} caratteri")

    stem = safe_stem if safe_stem is not None else Path(filename).stem
    translation_stem = f"{stem}_IT"
    dest_path = None if return_content else Config.METADATA_DIR / f"{translation_stem}.md"

    tokens_input = 0
    tokens_output = 0

    # 1. Riassunto esaustivo in italiano — usa Sonnet per qualità
    log.info("    📝 Generazione riassunto italiano...")
    text_for_summary = text[:Config.MAX_TEXT_CHARS]
    summary_it = ""
    try:
        r = call_with_retry(client.messages.create,
            model=Config.CLAUDE_MODEL,
            max_tokens=2000,
            system=SYSTEM_PROMPT_SUMMARY,
            messages=[{
                "role": "user",
                "content": f"Documento: {filename}\n\nContenuto:\n---\n{text_for_summary}\n---\n\nGenera un riassunto esaustivo e strutturato in italiano."
            }]
        )
        summary_it = r.content[0].text
        tokens_input += r.usage.input_tokens
        tokens_output += r.usage.output_tokens
    except Exception as e:
        log.error(f"    ⚠ Errore riassunto: {e}")
        summary_it = "_Riassunto non disponibile._"

    # 2. Strutturazione testo — converte testo grezzo PDF in markdown
    log.info("    🔧 Strutturazione testo (Sonnet)...")
    text_to_translate = text[:Config.MAX_TEXT_CHARS]

    # Struttura il testo in un unico passaggio (usa solo i primi 60k car. per la strutturazione)
    # Il resto viene tradotto direttamente senza strutturazione (meno critico)
    STRUCTURE_LIMIT = 60_000
    text_to_structure = text_to_translate[:STRUCTURE_LIMIT]
    text_remainder    = text_to_translate[STRUCTURE_LIMIT:]

    try:
        r = call_with_retry(client.messages.create,
            model=Config.CLAUDE_MODEL,
            max_tokens=8000,
            system=SYSTEM_PROMPT_STRUCTURE,
            messages=[{"role": "user", "content": f"Struttura il seguente testo grezzo in markdown:\n\n{text_to_structure}"}]
        )
        structured_text = r.content[0].text
        tokens_input  += r.usage.input_tokens
        tokens_output += r.usage.output_tokens
        log.info("    ✓ Strutturazione completata")
        # Ricompone il testo con l'eventuale parte non strutturata
        text_to_translate = structured_text + ("\n\n" + text_remainder if text_remainder else "")
    except Exception as e:
        log.warning(f"    ⚠ Strutturazione fallita, procedo senza: {e}")
        # Continua con il testo originale non strutturato

    # 3. Traduzione a chunk — usa Haiku (più economico, qualità sufficiente)
    log.info("    🌐 Traduzione testo (Haiku)...")
    chunks = _chunk_text(text_to_translate)
    translated_chunks = []

    for i, chunk in enumerate(chunks, 1):
        log.info(f"    Chunk {i}/{len(chunks)}...")
        try:
            r = call_with_retry(client.messages.create,
                model=Config.CLAUDE_MODEL_FAST,
                max_tokens=8000,
                system=SYSTEM_PROMPT_TRANSLATE,
                messages=[{"role": "user", "content": f"Traduci il seguente testo dall'inglese all'italiano:\n\n{chunk}"}]
            )
            translated_chunks.append(r.content[0].text)
            tokens_input += r.usage.input_tokens
            tokens_output += r.usage.output_tokens
        except Exception as e:
            log.error(f"    ⚠ Errore chunk {i}: {e}")
            translated_chunks.append(f"_[Traduzione chunk {i} non disponibile]_\n\n{chunk}")

    translated_text = "\n\n".join(translated_chunks)

    # 3. Genera il file .md
    from datetime import date, datetime
    today = date.today().isoformat()
    now = datetime.now().strftime("%d/%m/%Y, %H:%M:%S")

    tags = meta.get("tags", [])
    tags_yaml = "\n".join(f"  - {t}" for t in tags) + "\n  - traduzione_it"

    content = f"""---
title: "{_escape_yaml(meta.get('title', filename))} — Traduzione IT"
file_originale: "[[_archive/{filename}]]"
file_metadati: "[[{stem}]]"
lingua_originale: "{language}"
tipo_documento: {meta.get('document_type', 'altro')}
data_catalogazione: {today}
tags:
{tags_yaml}
created: {now}
---

# {meta.get('title', filename)} — Traduzione italiana

> Traduzione automatica dall'inglese generata da PKM Agent.
> Documento originale: [[_archive/{filename}]] | Metadati: [[{stem}]]

---

## 📝 Riassunto esaustivo

{summary_it}

---

## 📄 Testo completo tradotto

{translated_text}

---

*Traduzione generata automaticamente da PKM Agent — {now}*
"""

    if return_content:
        return {
            "translation_stem": translation_stem,
            "translation_content": content,
            "_trans_tokens_input": tokens_input,
            "_trans_tokens_output": tokens_output,
        }

    dest_path.write_text(content, encoding="utf-8")
    log.info(f"    ✓ Traduzione salvata: {translation_stem}.md")

    return {
        "translation_stem": translation_stem,
        "_trans_tokens_input": tokens_input,
        "_trans_tokens_output": tokens_output,
    }


def _chunk_text(text: str) -> list[str]:
    """
    Suddivide il testo in chunk rispettando paragrafi, titoli e tabelle.
    - Non separa mai un titolo markdown dal paragrafo successivo
    - Non spezza le tabelle markdown a metà
    """
    if len(text) <= CHUNK_SIZE:
        return [text]

    chunks = []
    paragraphs = text.split("\n\n")
    current = ""
    i = 0

    while i < len(paragraphs):
        para = paragraphs[i]

        # Se è un titolo, uniscilo sempre al paragrafo successivo
        if para.lstrip().startswith("#") and i + 1 < len(paragraphs):
            para = para + "\n\n" + paragraphs[i + 1]
            i += 1

        # Se è l'inizio di una tabella, raccoglie tutte le righe della tabella
        elif "|" in para and i + 1 < len(paragraphs) and "|" in paragraphs[i + 1]:
            while i + 1 < len(paragraphs) and "|" in paragraphs[i + 1]:
                para = para + "\n\n" + paragraphs[i + 1]
                i += 1

        # Aggiunge al chunk corrente o apre un nuovo chunk
        if len(current) + len(para) + 2 > CHUNK_SIZE and current:
            chunks.append(current.strip())
            current = para
        else:
            current = current + "\n\n" + para if current else para

        i += 1

    if current.strip():
        chunks.append(current.strip())

    return chunks


def _escape_yaml(s: str) -> str:
    return s.replace('"', '\\"')
