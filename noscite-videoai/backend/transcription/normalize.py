"""Normalizzazione trascrizioni in testo continuo con timestamp di paragrafo.

Funzioni pure (nessuna rete, nessun I/O): testabili in isolamento. Entrambe le
sorgenti — Groq Whisper e youtube-transcript-api — vengono convertite nello
stesso formato di segmento ({text, start, end}) prima di passare di qui.
"""
from backend.ingest.extractor import format_timestamp


def segments_to_paragraphs(segments: list[dict], window_seconds: float = 30.0) -> list[dict]:
    """Raggruppa segmenti consecutivi in paragrafi.

    Un nuovo paragrafo inizia quando dall'inizio del paragrafo corrente sono
    trascorsi almeno `window_seconds`. Segmenti vuoti vengono ignorati.

    Ogni paragrafo: {start, end, text}.
    """
    paragraphs: list[dict] = []
    current: dict | None = None

    for seg in segments:
        text = (seg.get("text") or "").strip()
        if not text:
            continue
        start = float(seg.get("start", 0) or 0)
        end = float(seg.get("end", start) or start)

        if current is None:
            current = {"start": start, "end": end, "text": text}
            continue

        if start - current["start"] >= window_seconds:
            paragraphs.append(current)
            current = {"start": start, "end": end, "text": text}
        else:
            current["text"] = f"{current['text']} {text}"
            current["end"] = max(current["end"], end)

    if current is not None:
        paragraphs.append(current)

    return paragraphs


def paragraphs_to_text(paragraphs: list[dict]) -> str:
    """Rende i paragrafi come testo continuo con prefisso timestamp [MM:SS]."""
    return "\n\n".join(
        f"[{format_timestamp(p['start'])}] {p['text']}" for p in paragraphs
    )


def to_public_segments(segments: list[dict]) -> list[dict]:
    """Converte segmenti interni ({text, start, end}) nel formato pubblico
    dell'API: {start_seconds, end_seconds, text}. Ignora i testi vuoti."""
    out: list[dict] = []
    for seg in segments:
        text = (seg.get("text") or "").strip()
        if not text:
            continue
        start = float(seg.get("start", 0) or 0)
        end = float(seg.get("end", start) or start)
        out.append({
            "start_seconds": round(start, 2),
            "end_seconds": round(end, 2),
            "text": text,
        })
    return out


def normalize_transcript(segments: list[dict], window_seconds: float = 30.0) -> dict:
    """Da segmenti a {text, paragraphs}.

    `text` è il testo continuo con timestamp di paragrafo, pronto per l'output.
    """
    paragraphs = segments_to_paragraphs(segments, window_seconds)
    return {
        "text": paragraphs_to_text(paragraphs),
        "paragraphs": paragraphs,
    }
