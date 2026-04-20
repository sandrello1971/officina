"""
Indice globale — con Dataview la tabella è dinamica e generata
automaticamente dal frontmatter YAML dei file in _metadata/.
Questo modulo si limita a creare il file _INDEX.md se non esiste
e ad aggiornare il contatore documenti nel frontmatter.
"""

import logging

from config import Config

log = logging.getLogger(__name__)


def update(filename: str, meta: dict, tech_meta: dict):
    """Assicura che _INDEX.md esista. La tabella è gestita da Dataview."""
    try:
        _ensure_index_exists()
        log.info("  ✓ Indice Dataview pronto")
    except Exception as e:
        log.warning(f"  ⚠ Impossibile aggiornare indice: {e}")


def regen():
    """Rigenera _INDEX.md sovrascrivendo la versione esistente."""
    try:
        _write_index()
        log.info("  ✓ _INDEX.md rigenerato")
    except Exception as e:
        log.warning(f"  ⚠ Impossibile rigenerare indice: {e}")


def _ensure_index_exists():
    if Config.INDEX_FILE.exists():
        return
    _write_index()


def _write_index():
    content = """\
---
title: "📚 Indice Documenti PKM"
tags: [indice, dataview]
ricerca_tipo: ""
ricerca_lingua: ""
ricerca_tag: ""
ricerca_argomento: ""
ricerca_parola: ""
ricerca_persona: ""
ricerca_org: ""
---

# 📚 Indice Documenti PKM

> Navigazione completa al vault — funziona interamente in Obsidian senza dashboard esterna.
> Richiede il plugin **Dataview**. Aggiorna con `Ctrl+R`.
>
> **Come usare i filtri:** apri il pannello **Properties** (icona in cima alla nota) e modifica i campi `ricerca_*`. Ogni campo alimenta la sezione corrispondente qui sotto.

---

> [!abstract]- 📊 Panoramica per tipo
>
> ```dataview
> TABLE rows.title AS "Documenti", length(rows) AS "#"
> FROM "_metadata"
> WHERE file.name != "_INDEX"
> GROUP BY tipo_documento AS "Tipo"
> SORT length(rows) DESC
> ```

> [!abstract]- 🔍 Tutti i documenti
>
> ```dataview
> TABLE WITHOUT ID
>   title AS "Titolo",
>   tipo_documento AS "Tipo",
>   lingua AS "Lingua",
>   data_documento AS "Data"
> FROM "_metadata"
> WHERE file.name != "_INDEX"
> SORT data_catalogazione DESC
> ```

> [!abstract]- 🕐 Ultimi 7 giorni
>
> ```dataview
> TABLE WITHOUT ID
>   title AS "Titolo",
>   tipo_documento AS "Tipo",
>   lingua AS "Lingua",
>   data_documento AS "Data",
>   data_catalogazione AS "Catalogato"
> FROM "_metadata"
> WHERE file.name != "_INDEX" AND date(data_catalogazione) >= date(today) - dur(7 days)
> SORT data_catalogazione DESC
> ```

> [!abstract]- 🕐 Ultimi 30 giorni
>
> ```dataview
> TABLE WITHOUT ID
>   title AS "Titolo",
>   tipo_documento AS "Tipo",
>   lingua AS "Lingua",
>   data_documento AS "Data",
>   data_catalogazione AS "Catalogato"
> FROM "_metadata"
> WHERE file.name != "_INDEX" AND date(data_catalogazione) >= date(today) - dur(30 days)
> SORT data_catalogazione DESC
> ```

> [!abstract]- 📄 Filtra per tipo  —  `ricerca_tipo`
>
> Imposta `ricerca_tipo` in Properties. Valori supportati:
> `report` · `rivista` · `presentazione` · `foglio_calcolo` · `articolo` · `manuale` · `datasheet` · `specifica_tecnica` · `brochure` · `contratto` · `fattura` · `email` · `video` · `audio` · `immagine` · `archivio` · `codice` · `brevetto` · `visura` · `verbale` · `certificato` · `vademecum` · `agenda` · `faq` · `viaggio` · `spettacolo` · `schema` · `progetto` · `altro`
>
> ```dataview
> TABLE WITHOUT ID
>   title AS "Titolo",
>   lingua AS "Lingua",
>   argomenti AS "Argomenti",
>   sommario AS "Sommario",
>   data_documento AS "Data"
> FROM "_metadata"
> WHERE file.name != "_INDEX" AND tipo_documento = this.ricerca_tipo
> SORT data_documento DESC
> ```

> [!abstract]- 🗂️ Filtra per lingua  —  `ricerca_lingua`
>
> Imposta `ricerca_lingua` in Properties (es. `it`, `en`, `it+en`, `de`).
>
> ```dataview
> TABLE WITHOUT ID
>   title AS "Titolo",
>   tipo_documento AS "Tipo",
>   argomenti AS "Argomenti",
>   data_documento AS "Data"
> FROM "_metadata"
> WHERE file.name != "_INDEX" AND lingua = this.ricerca_lingua
> SORT data_documento DESC
> ```

> [!abstract]- 🏷️ Filtra per tag  —  `ricerca_tag`
>
> Imposta `ricerca_tag` in Properties con qualsiasi tag presente nei tuoi documenti.
>
> ```dataview
> TABLE WITHOUT ID
>   title AS "Titolo",
>   tipo_documento AS "Tipo",
>   lingua AS "Lingua",
>   argomenti AS "Argomenti",
>   data_documento AS "Data"
> FROM "_metadata"
> WHERE file.name != "_INDEX" AND contains(tags, this.ricerca_tag)
> SORT data_documento DESC
> ```

> [!abstract]- 💡 Cerca per argomento  —  `ricerca_argomento`
>
> Imposta `ricerca_argomento` in Properties (es. `finanza`, `tecnico`, `legale`).
>
> ```dataview
> TABLE WITHOUT ID
>   title AS "Titolo",
>   tipo_documento AS "Tipo",
>   lingua AS "Lingua",
>   data_documento AS "Data"
> FROM "_metadata"
> WHERE file.name != "_INDEX" AND contains(argomenti, this.ricerca_argomento)
> SORT data_catalogazione DESC
> ```

> [!abstract]- 🔎 Cerca nel sommario  —  `ricerca_parola`
>
> Imposta `ricerca_parola` in Properties con qualsiasi termine da cercare nei sommari.
>
> ```dataview
> TABLE WITHOUT ID
>   title AS "Titolo",
>   tipo_documento AS "Tipo",
>   sommario AS "Sommario",
>   data_documento AS "Data"
> FROM "_metadata"
> WHERE file.name != "_INDEX" AND contains(lower(sommario), this.ricerca_parola)
> SORT data_catalogazione DESC
> ```

> [!abstract]- 👤 Cerca per persona  —  `ricerca_persona`
>
> Imposta `ricerca_persona` in Properties con il nome di una persona citata nei documenti.
>
> ```dataview
> TABLE WITHOUT ID
>   title AS "Titolo",
>   persone AS "Persone",
>   tipo_documento AS "Tipo",
>   data_documento AS "Data"
> FROM "_metadata"
> WHERE file.name != "_INDEX" AND contains(lower(persone), this.ricerca_persona)
> SORT data_documento DESC
> ```

> [!abstract]- 🏢 Cerca per organizzazione  —  `ricerca_org`
>
> Imposta `ricerca_org` in Properties con il nome di un'azienda o ente.
>
> ```dataview
> TABLE WITHOUT ID
>   title AS "Titolo",
>   organizzazioni AS "Organizzazioni",
>   tipo_documento AS "Tipo",
>   data_documento AS "Data"
> FROM "_metadata"
> WHERE file.name != "_INDEX" AND contains(lower(organizzazioni), this.ricerca_org)
> SORT data_documento DESC
> ```

> [!abstract]- 🌍 Documenti con traduzione italiana
>
> ```dataview
> TABLE WITHOUT ID
>   title AS "Titolo originale",
>   traduzione_it AS "Traduzione IT",
>   lingua AS "Lingua originale",
>   data_documento AS "Data"
> FROM "_metadata"
> WHERE file.name != "_INDEX" AND traduzione_it
> SORT data_catalogazione DESC
> ```

---

*Indice gestito automaticamente da [Obsidian AI Metadata Agent](https://github.com/luckehall/obsidian-pkm-agent) + Dataview*
"""
    Config.INDEX_FILE.write_text(content, encoding="utf-8")
    log.info("  ✓ _INDEX.md creato")
