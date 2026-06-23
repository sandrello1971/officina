import json
import re
from pathlib import Path

import anthropic

from backend.config import settings
from backend.rag.embedder import VideoIndex


SYSTEM_PROMPT = """Sei un assistente esperto nell'analisi di video. Rispondi SEMPRE in italiano.
Hai accesso a trascrizioni e analisi visive del video.

REGOLE:
1. Basa le risposte SOLO sul contesto fornito
2. Cita SEMPRE il timestamp con formato [MM:SS] per ogni informazione
3. Distingui tra info da audio [AUDIO MM:SS] e da immagine [FRAME MM:SS]
4. Se vedi disegni o schemi nel contesto frame, descrivili e citali
5. Alla fine aggiungi sempre: '📍 Punti chiave nel video:' con lista timestamp + descrizione 1 riga
6. Se non trovi l'info nel contesto: dillo esplicitamente, non inventare"""


def format_context(chunks: list[dict]) -> str:
    """Formatta i chunk recuperati in testo strutturato per il prompt."""
    if not chunks:
        return "Nessun contesto disponibile."

    parts = []
    for chunk in chunks:
        meta = chunk["metadata"]
        ts = meta.get("timestamp_str", "??:??")
        chunk_type = meta.get("type", "unknown")

        if chunk_type == "transcript":
            header = f"🎤 [AUDIO {ts}]"
        elif chunk_type == "frame":
            content_type = meta.get("content_type", "")
            header = f"🖼️ [FRAME {ts}] ({content_type})"
        else:
            header = f"📄 [{ts}]"

        parts.append(f"{header}\n{chunk['text']}")

    return "\n\n---\n\n".join(parts)


def _extract_timestamps(text: str) -> list[str]:
    """Estrae tutti i timestamp dalla risposta."""
    pattern = r'\[(?:AUDIO |FRAME )?(\d{1,2}:\d{2}(?::\d{2})?)\]'
    matches = re.findall(pattern, text)
    # Rimuovi duplicati mantenendo ordine
    seen = set()
    unique = []
    for ts in matches:
        if ts not in seen:
            seen.add(ts)
            unique.append(ts)
    return unique


def chat(
    video_id: str,
    question: str,
    history: list[dict] | None = None,
    transcript_only: bool = False,
) -> dict:
    """Risponde a una domanda sul video usando RAG.

    Se transcript_only=True, usa solo chunk di tipo transcript.
    """
    history = history or []

    # Recupera contesto
    index = VideoIndex(video_id)
    if not index.exists():
        return {
            "answer": "Il video non è ancora stato indicizzato. Esegui prima l'ingestione.",
            "timestamps": [],
            "sources": [],
        }

    if transcript_only:
        chunks = index.retrieve_by_type(question, "transcript", n_results=6)
    else:
        chunks = index.retrieve(question, n_results=6)
    context = format_context(chunks)

    # Costruisci messaggi
    messages = []
    for msg in history[-10:]:  # Ultimi 10 messaggi di storia
        messages.append({"role": msg["role"], "content": msg["content"]})

    user_message = f"""Contesto dal video:

{context}

---

Domanda: {question}"""

    messages.append({"role": "user", "content": user_message})

    # Chiama Claude
    client = anthropic.Anthropic(api_key=settings.ANTHROPIC_API_KEY)
    response = client.messages.create(
        model="claude-sonnet-4-20250514",
        max_tokens=2048,
        system=SYSTEM_PROMPT,
        messages=messages,
    )

    answer = response.content[0].text
    if transcript_only:
        answer += "\n\n_(Analisi immagini ancora in corso)_"
    timestamps = _extract_timestamps(answer)

    sources = [
        {
            "text": c["text"][:200],
            "type": c["metadata"].get("type"),
            "timestamp_str": c["metadata"].get("timestamp_str"),
            "start": c["metadata"].get("start"),
        }
        for c in chunks
    ]

    print(f"[CHAT] Risposta generata con {len(timestamps)} timestamp e {len(sources)} fonti")
    return {
        "answer": answer,
        "timestamps": timestamps,
        "sources": sources,
    }


def generate_tags(video_id: str) -> list[str]:
    """Genera tag automatici per il video usando AI."""
    try:
        index = VideoIndex(video_id)
        if not index.exists():
            return []

        chunks = index.retrieve("argomenti principali contenuto", n_results=8)
        if not chunks:
            return []

        excerpts = "\n".join(c["text"][:250] for c in chunks)

        client = anthropic.Anthropic(api_key=settings.ANTHROPIC_API_KEY)
        response = client.messages.create(
            model="claude-haiku-4-5-20251001",
            max_tokens=200,
            messages=[{
                "role": "user",
                "content": (
                    "Analizza questi estratti video e genera esattamente 5-8 tag "
                    "in italiano che descrivono gli argomenti trattati.\n"
                    "I tag devono essere: specifici, utili per la ricerca, "
                    "in minuscolo, massimo 2-3 parole ciascuno.\n"
                    'Rispondi SOLO con un JSON array: ["tag1", "tag2", ...]\n'
                    "Niente altro, nessuna spiegazione.\n\n"
                    f"{excerpts}"
                ),
            }],
        )

        raw = response.content[0].text.strip()
        if raw.startswith("```"):
            raw = raw.split("\n", 1)[1] if "\n" in raw else raw[3:]
            if raw.endswith("```"):
                raw = raw[:-3].strip()
        tags = json.loads(raw)
        if isinstance(tags, list):
            tags = [t.strip().lower() for t in tags if isinstance(t, str) and t.strip()]
            print(f"[CHAT] Tag generati: {tags}")
            return tags[:8]
        return []
    except Exception as e:
        print(f"[CHAT] Errore generazione tag: {e}")
        return []


DEFAULT_COLLECTIONS = [
    "Corsi e formazione", "Tutorial tecnico", "Interviste",
    "Presentazioni", "Marketing e vendite", "Tecnologia", "Altro",
]


def auto_assign_collection(video_id: str, all_collections: list[str] | None = None) -> str:
    """Assegna automaticamente una collezione al video."""
    try:
        from backend.storage.database import get_db
        db = get_db()
        video = db.get(video_id)
        if not video:
            return "Altro"

        # Always use default categories as base, merge with existing
        categories = list(DEFAULT_COLLECTIONS)
        if all_collections:
            for c in all_collections:
                if c not in categories and c != "Generale":
                    categories.append(c)

        summary = video.get("summary", "")
        tags = video.get("tags", [])
        if isinstance(tags, str):
            try:
                tags = json.loads(tags)
            except (json.JSONDecodeError, TypeError):
                tags = []

        client = anthropic.Anthropic(api_key=settings.ANTHROPIC_API_KEY)
        response = client.messages.create(
            model="claude-haiku-4-5-20251001",
            max_tokens=50,
            messages=[{
                "role": "user",
                "content": (
                    f"Dato questo video con summary '{summary}' e tag {tags}, "
                    f"assegnalo alla collezione più appropriata tra: {categories}\n"
                    "NON rispondere 'Generale'. Scegli la categoria più specifica.\n"
                    "Rispondi SOLO con il nome esatto della collezione, niente altro."
                ),
            }],
        )

        collection = response.content[0].text.strip().strip('"').strip("'")
        # Validate it's in the list
        for c in categories:
            if c.lower() == collection.lower():
                print(f"[CHAT] Auto-assign: {video_id[:8]}... → {c}")
                return c
        print(f"[CHAT] Auto-assign: {video_id[:8]}... → {collection} (custom)")
        return collection
    except Exception as e:
        print(f"[CHAT] Errore auto-assign collection: {e}")
        return "Altro"


def search_across_videos(question: str, video_ids: list[str]) -> list[dict]:
    """Cerca una domanda attraverso più video."""
    results = []

    for vid in video_ids:
        try:
            index = VideoIndex(vid)
            if not index.exists():
                continue
            chunks = index.retrieve(question, n_results=3)
            # Filter by relevance (lower distance = more relevant)
            relevant = [c for c in chunks if c.get("distance", 1) < 0.7]
            if not relevant:
                continue

            matches = [
                {
                    "timestamp_str": c["metadata"].get("timestamp_str", ""),
                    "text": c["text"][:200],
                    "start": c["metadata"].get("start", 0),
                    "type": c["metadata"].get("type", ""),
                }
                for c in relevant
            ]

            # Best score for sorting
            best_score = min(c.get("distance", 1) for c in relevant)
            results.append({
                "video_id": vid,
                "matches": matches,
                "relevance": 1 - best_score,
            })
        except Exception as e:
            print(f"[SEARCH] Errore su video {vid[:8]}: {e}")

    results.sort(key=lambda r: r["relevance"], reverse=True)
    return results


def generate_video_summary(video_id: str) -> str:
    """Genera una descrizione breve dell'argomento del video."""
    try:
        index = VideoIndex(video_id)
        if not index.exists():
            return ""

        # Recupera i primi 5 chunk di trascrizione
        chunks = index.retrieve_by_type("argomento principale", "transcript", n_results=5)
        if not chunks:
            return ""

        excerpts = "\n".join(c["text"][:300] for c in chunks)

        client = anthropic.Anthropic(api_key=settings.ANTHROPIC_API_KEY)
        response = client.messages.create(
            model="claude-haiku-4-5-20251001",
            max_tokens=100,
            messages=[{
                "role": "user",
                "content": f"In massimo 20 parole descrivi l'argomento principale di questo video basandoti su questi estratti. Rispondi SOLO con la descrizione, niente altro.\n\n{excerpts}",
            }],
        )

        summary = response.content[0].text.strip()
        print(f"[CHAT] Summary generato: {summary}")

        # Salva in metadata.json
        meta_path = settings.DATA_DIR / "videos" / video_id / "metadata.json"
        if meta_path.exists():
            with open(meta_path) as f:
                metadata = json.load(f)
            metadata["summary"] = summary
            with open(meta_path, "w") as f:
                json.dump(metadata, f, indent=2, ensure_ascii=False)

        return summary
    except Exception as e:
        print(f"[CHAT] Errore generazione summary: {e}")
        return ""


GLOBAL_SYSTEM_PROMPT = """Sei un assistente che risponde a domande usando i contenuti di una videoteca personale. Hai accesso a trascrizioni e analisi visive di più video.

REGOLE FONDAMENTALI:
1. Rispondi SEMPRE in italiano
2. Basa le risposte SOLO sul contesto fornito
3. Per ogni informazione cita SEMPRE il video e il timestamp: → [Nome video, MM:SS]
4. Se la stessa info appare in più video, citali tutti
5. Alla fine aggiungi sempre:
   '📹 Video correlati:'
   - [Titolo video] — [timestamp principale] — [descrizione 1 riga]
6. Se non trovi l'informazione: dillo chiaramente
7. Se la domanda è vaga, suggerisci query più specifiche"""


def global_chat(question: str, history: list[dict] | None = None) -> dict:
    """Risponde a domande usando i contenuti di TUTTI i video."""
    from backend.storage.database import get_db

    history = history or []
    db = get_db()
    all_videos = db.search()

    if not all_videos:
        return {
            "answer": "Nessun video disponibile nella videoteca. Carica almeno un video per iniziare.",
            "video_references": [],
            "sources_count": 0,
        }

    # Collect chunks from all videos
    all_chunks = []
    video_map = {}  # video_id -> metadata
    for v in all_videos:
        vid = v["video_id"]
        video_map[vid] = v
        try:
            index = VideoIndex(vid)
            if not index.exists():
                continue
            chunks = index.retrieve(question, n_results=3)
            for c in chunks:
                c["_video_id"] = vid
                c["_title"] = v.get("title", v.get("filename", ""))
            all_chunks.extend(chunks)
        except Exception as e:
            print(f"[GLOBAL-CHAT] Errore su video {vid[:8]}: {e}")

    if not all_chunks:
        return {
            "answer": "Non ho trovato informazioni rilevanti nei video disponibili.",
            "video_references": [],
            "sources_count": len(all_videos),
        }

    # Sort by relevance and take top 10
    all_chunks.sort(key=lambda c: c.get("distance", 1))
    top_chunks = all_chunks[:10]
    consulted_videos = set(c["_video_id"] for c in top_chunks)

    # Build context
    context_parts = []
    for c in top_chunks:
        ts = c["metadata"].get("timestamp_str", "??:??")
        title = c["_title"]
        chunk_type = c["metadata"].get("type", "")
        prefix = "🎤" if chunk_type == "transcript" else "🖼️"
        context_parts.append(f"{prefix} [{title}] [{ts}]\n{c['text']}")

    context = "\n\n---\n\n".join(context_parts)

    # Build messages
    messages = []
    for msg in history[-6:]:
        messages.append({"role": msg["role"], "content": msg["content"]})

    messages.append({
        "role": "user",
        "content": f"Contesto dalla videoteca ({len(consulted_videos)} video consultati):\n\n{context}\n\n---\n\nDomanda: {question}",
    })

    client = anthropic.Anthropic(api_key=settings.ANTHROPIC_API_KEY)
    response = client.messages.create(
        model="claude-sonnet-4-20250514",
        max_tokens=2048,
        system=GLOBAL_SYSTEM_PROMPT,
        messages=messages,
    )

    answer = response.content[0].text

    # Extract video references from chunks used
    video_refs = []
    seen_refs = set()
    for c in top_chunks:
        vid = c["_video_id"]
        ts = c["metadata"].get("timestamp_str", "00:00")
        key = f"{vid}_{ts}"
        if key not in seen_refs:
            seen_refs.add(key)
            video_refs.append({
                "video_id": vid,
                "title": c["_title"],
                "timestamp_str": ts,
                "timestamp_seconds": c["metadata"].get("start", 0),
            })

    print(f"[GLOBAL-CHAT] Risposta con {len(video_refs)} riferimenti da {len(consulted_videos)} video")
    return {
        "answer": answer,
        "video_references": video_refs,
        "sources_count": len(consulted_videos),
    }
