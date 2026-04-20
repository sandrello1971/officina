"""
Analisi AI tramite Claude API — chiamata unica.
Risposta in due parti separate dal marcatore ---ESTRATTO---:
  1. JSON puro con i metadati
  2. Testo markdown con l'estratto strutturato
"""

import json
import logging

import anthropic

from config import Config
from api_utils import call_with_retry

log = logging.getLogger(__name__)
client = anthropic.Anthropic()

SYSTEM_PROMPT = """Sei un assistente specializzato nella catalogazione di documenti per un sistema di knowledge management.
Analizza il documento e rispondi con ESATTAMENTE questo formato, rispettando l'ordine:

Prima parte: un oggetto JSON valido (senza backtick, senza intestazioni, il JSON deve essere il primissimo contenuto della risposta):
{
  "title": "titolo descrittivo del documento (non il nome file)",
  "language": "it | en | en+it | it+en | altro",
  "document_type": "report | rivista | presentazione | foglio_calcolo | articolo | manuale | datasheet | specifica_tecnica | brochure | contratto | fattura | email | video | audio | immagine | archivio | codice | brevetto | visura | verbale | certificato | vademecum | agenda | faq | viaggio | spettacolo | schema | progetto | altro",
  "sentiment": "positivo | neutro | negativo | tecnico",
  "complexity": "semplice | intermedio | avanzato | tecnico",
  "document_date": "YYYY-MM-DD, YYYY-MM o YYYY. Usa N/D se non determinabile.",
  "entities": {
    "persons": ["nome1"],
    "organizations": ["org1"],
    "places": ["luogo1"],
    "products": ["prodotto1"]
  },
  "summary": "riassunto in 2-4 frasi",
  "topics": ["argomento1"],
  "tags": ["tag1"],
  "keywords": ["parola1"],
  "related_topics": ["argomento correlato"],
  "potential_links": ["categoria per possibili link"]
}

Seconda parte: scrivi esattamente questa riga su una nuova riga:
---ESTRATTO---

Terza parte: l'estratto strutturato in markdown:
**Tipologia:** [tipo documento]
**Struttura:** [numero sezioni/capitoli principali]

### [Titolo sezione/capitolo 1]
[2-4 frasi: concetti chiave, tecnologie, termini tecnici]

### [Titolo sezione/capitolo 2]
[2-4 frasi di sintesi]

(continua per ogni sezione significativa)

**Concetti trasversali:** [termini tecnici e tecnologie che attraversano il documento]

REGOLE IMPORTANTI:
- NON aggiungere intestazioni, separatori o testo PRIMA del JSON
- NON usare backtick intorno al JSON
- Il JSON deve iniziare immediatamente con la parentesi graffa {
- Il marcatore ---ESTRATTO--- deve apparire su una riga da sola
- tags: 5-15 in minuscolo con underscore (es: machine_learning)
- topics: 2-6 argomenti principali in italiano
- estratto: in italiano, ricco di termini tecnici specifici del dominio
- Per file media senza testo, basa l'analisi su nome file e metadati tecnici
- document_type: usa "datasheet" per schede tecniche di componenti/dispositivi elettronici (es: microcontrollori, sensori, circuiti integrati); usa "specifica_tecnica" per documenti di specifica, requisiti o standard tecnici; usa "brochure" per materiale commerciale/promozionale di prodotti o servizi; usa "visura" per visure camerali e catastali; usa "verbale" per verbali di assemblea, riunione o seduta; usa "certificato" per certificati, attestati e documenti ufficiali di qualifica; usa "vademecum" per guide rapide, istruzioni sintetiche e documenti di riferimento operativo; usa "agenda" per ordini del giorno, programmi di eventi e pianificazioni; usa "faq" per documenti di domande e risposte frequenti; usa "viaggio" per biglietti di trasporto (treno, aereo, bus) e itinerari; usa "spettacolo" per biglietti di eventi culturali, teatrali e concerti; usa "schema" per schemi elettrici, diagrammi tecnici e planimetrie; usa "progetto" per piani di progetto, project charter, gantt, specifiche di progetto, documentazione di avanzamento lavori e qualsiasi documento che descriva la pianificazione o l'esecuzione di un progetto"""


def analyze(filename: str, text: str, tech_meta: dict) -> dict:
    tech_json = json.dumps(tech_meta, ensure_ascii=False)
    text_full = text[:Config.MAX_TEXT_CHARS] if text else ""
    full_note = (f"\n[Testo troncato a {Config.MAX_TEXT_CHARS} car. su {len(text)} totali]"
                 if len(text) > Config.MAX_TEXT_CHARS else "")

    msg = (
        f"Nome file: {filename}\n"
        f"Metadati tecnici: {tech_json}\n\n"
        f"Contenuto:{full_note}\n---\n"
        f"{text_full if text_full else '(nessun testo disponibile)'}\n---\n\n"
        f"Produci la risposta nel formato richiesto: JSON puro, poi ---ESTRATTO---, poi l'estratto markdown."
    )

    try:
        r = call_with_retry(
            client.messages.create,
            model=Config.CLAUDE_MODEL,
            max_tokens=4000,
            system=SYSTEM_PROMPT,
            messages=[{"role": "user", "content": msg}],
        )
        full_response = r.content[0].text.strip()
        tokens_in  = r.usage.input_tokens
        tokens_out = r.usage.output_tokens
        log.info(f"  ✓ Analisi completata ({tokens_in} in / {tokens_out} out)")

        marker = "---ESTRATTO---"

        if marker in full_response:
            json_part, estratto = full_response.split(marker, 1)
        else:
            # Fallback: cerca il JSON con { ... }
            log.warning("  ⚠ Marcatore non trovato, estrazione JSON fallback")
            start = full_response.find("{")
            end   = full_response.rfind("}") + 1
            if start != -1 and end > start:
                json_part = full_response[start:end]
                estratto  = full_response[end:].strip()
            else:
                log.error(f"  ✗ Nessun JSON trovato. Risposta: {repr(full_response[:300])}")
                return _fallback_meta(filename)

        # Pulizia JSON
        json_part = json_part.strip()
        json_part = json_part.removeprefix("```json").removeprefix("```").removesuffix("```").strip()

        # Se il JSON non inizia con { cerca la prima parentesi graffa
        if not json_part.startswith("{"):
            start = json_part.find("{")
            if start != -1:
                json_part = json_part[start:]
            else:
                log.error(f"  ✗ JSON malformato. Inizio risposta: {repr(full_response[:300])}")
                return _fallback_meta(filename)

        meta = json.loads(json_part)
        result = {**_fallback_meta(filename), **meta}
        result["estratto_documento"] = estratto.strip()
        result["_tokens_input"]  = tokens_in
        result["_tokens_output"] = tokens_out
        result["_tokens_total"]  = tokens_in + tokens_out
        return result

    except Exception as e:
        log.error(f"Errore analisi per {filename}: {e}")
        return _fallback_meta(filename)


def _fallback_meta(filename: str) -> dict:
    return {
        "title": filename,
        "summary": "Analisi AI non disponibile.",
        "estratto_documento": "",
        "language": "N/D",
        "document_type": "altro",
        "topics": [],
        "tags": ["non_catalogato"],
        "entities": {"persons": [], "organizations": [], "places": [], "products": []},
        "keywords": [],
        "document_date": "N/D",
        "sentiment": "neutro",
        "complexity": "N/D",
        "related_topics": [],
        "potential_links": [],
        "_tokens_input": 0,
        "_tokens_output": 0,
        "_tokens_total": 0,
    }
