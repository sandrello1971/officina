import hashlib
from difflib import SequenceMatcher

from backend.config import settings
from backend.ingest.extractor import format_timestamp


def _text_similarity(a: str, b: str) -> float:
    """Calcola similarità tra due stringhe (0-1)."""
    if not a or not b:
        return 0.0
    return SequenceMatcher(None, a.lower(), b.lower()).ratio()


def _make_id(text: str, prefix: str) -> str:
    """Genera ID deterministico per un chunk."""
    h = hashlib.md5(text.encode()).hexdigest()[:12]
    return f"{prefix}_{h}"


def _build_transcript_chunks(
    segments: list[dict],
    window_seconds: int,
    overlap_seconds: int,
) -> list[dict]:
    """Crea chunk dalla trascrizione con finestre scorrevoli."""
    if not segments:
        return []

    chunks = []
    start_time = segments[0]["start"]
    end_time = segments[-1]["end"]

    window_start = start_time
    while window_start < end_time:
        window_end = window_start + window_seconds

        # Raccogli segmenti in questa finestra
        window_texts = []
        actual_start = None
        actual_end = None

        for seg in segments:
            if seg["end"] > window_start and seg["start"] < window_end:
                window_texts.append(seg["text"])
                if actual_start is None:
                    actual_start = seg["start"]
                actual_end = seg["end"]

        if window_texts and actual_start is not None:
            text = " ".join(window_texts)
            chunks.append({
                "id": _make_id(text, "transcript"),
                "text": text,
                "type": "transcript",
                "start": round(actual_start, 2),
                "end": round(actual_end, 2),
                "timestamp_str": format_timestamp(actual_start),
            })

        window_start += window_seconds - overlap_seconds

    return chunks


def _build_frame_chunks(frame_analyses: list[dict]) -> list[dict]:
    """Crea chunk dai frame analizzati."""
    chunks = []

    for analysis in frame_analyses:
        if not analysis.get("has_content", False):
            continue

        # Combina testo dal frame
        parts = []
        if analysis.get("summary"):
            parts.append(analysis["summary"])
        if analysis.get("text_content"):
            parts.append(f"Testo visibile: {analysis['text_content']}")
        if analysis.get("visual_description"):
            parts.append(f"Descrizione visiva: {analysis['visual_description']}")

        text = " | ".join(parts)
        if not text.strip():
            continue

        timestamp_seconds = analysis.get("timestamp_seconds", 0)
        chunks.append({
            "id": _make_id(text, "frame"),
            "text": text,
            "type": "frame",
            "content_type": analysis.get("content_type", "other"),
            "start": round(timestamp_seconds, 2),
            "end": round(timestamp_seconds + 2, 2),  # Frame copre ~2 secondi
            "timestamp_str": analysis.get("timestamp_str", format_timestamp(timestamp_seconds)),
        })

    return chunks


def _deduplicate(chunks: list[dict], threshold: float = 0.9) -> list[dict]:
    """Rimuovi chunk con testo quasi identico."""
    if not chunks:
        return []

    unique = [chunks[0]]
    for chunk in chunks[1:]:
        is_duplicate = False
        for existing in unique:
            if _text_similarity(chunk["text"], existing["text"]) > threshold:
                is_duplicate = True
                break
        if not is_duplicate:
            unique.append(chunk)

    removed = len(chunks) - len(unique)
    if removed > 0:
        print(f"[CHUNKER] Rimossi {removed} chunk duplicati")
    return unique


def build_chunks(
    transcript_segments: list[dict],
    frame_analyses: list[dict],
) -> list[dict]:
    """Costruisce chunk combinati da trascrizione e frame."""
    print("[CHUNKER] Creazione chunk...")

    transcript_chunks = _build_transcript_chunks(
        transcript_segments,
        settings.CHUNK_WINDOW_SECONDS,
        settings.CHUNK_OVERLAP_SECONDS,
    )
    frame_chunks = _build_frame_chunks(frame_analyses)

    all_chunks = transcript_chunks + frame_chunks
    all_chunks = _deduplicate(all_chunks)

    # Ordina per timestamp
    all_chunks.sort(key=lambda c: c["start"])

    print(f"[CHUNKER] Chunk trascrizione: {len(transcript_chunks)}")
    print(f"[CHUNKER] Chunk frame: {len(frame_chunks)}")
    print(f"[CHUNKER] Totale (dopo dedup): {len(all_chunks)}")

    return all_chunks
