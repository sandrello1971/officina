#!/usr/bin/env python3
"""
Test standalone della pipeline di ingestione video.
Uso:
  python test_ingest.py path/to/video.mp4          # ingest completo
  python test_ingest.py path/to/video.mp4 --reuse  # salta ingest, usa ultimo video_id
"""

import hashlib
import json
import sys
import time
from datetime import datetime
from pathlib import Path

# Aggiungi root al path per import
sys.path.insert(0, str(Path(__file__).parent))

from backend.config import settings
from backend.ingest.extractor import extract_audio, extract_keyframes, get_video_duration
from backend.ingest.transcriber import transcribe_audio
from backend.ingest.vision_analyzer import analyze_frames_batch
from backend.rag.chunker import build_chunks
from backend.rag.embedder import VideoIndex
from backend.chat.engine import chat


LAST_VIDEO_ID_PATH = settings.DATA_DIR / "last_video_id.txt"


def timed(label: str):
    """Context manager per misurare il tempo di ogni step."""
    class Timer:
        def __enter__(self):
            self.start = time.time()
            print(f"\n{'='*60}")
            print(f"[STEP] {label}")
            print(f"{'='*60}")
            return self

        def __exit__(self, *args):
            self.elapsed = time.time() - self.start
            print(f"[TIMING] {label}: {self.elapsed:.1f}s")

    return Timer()


def compute_video_id(video_path: Path) -> str:
    """Calcola video_id deterministico basato su hash MD5 del file."""
    h = hashlib.md5()
    with open(video_path, "rb") as f:
        for chunk in iter(lambda: f.read(8192), b""):
            h.update(chunk)
    return h.hexdigest()


def save_last_video_id(video_id: str):
    """Salva l'ultimo video_id in data/last_video_id.txt."""
    LAST_VIDEO_ID_PATH.parent.mkdir(parents=True, exist_ok=True)
    LAST_VIDEO_ID_PATH.write_text(video_id)
    print(f"[INFO] Video ID salvato in {LAST_VIDEO_ID_PATH}")


def load_last_video_id() -> str | None:
    """Carica l'ultimo video_id salvato."""
    if LAST_VIDEO_ID_PATH.exists():
        return LAST_VIDEO_ID_PATH.read_text().strip()
    return None


def is_already_indexed(video_id: str) -> bool:
    """Controlla se il video è già indicizzato."""
    meta_path = settings.DATA_DIR / "videos" / video_id / "metadata.json"
    if not meta_path.exists():
        return False
    try:
        index = VideoIndex(video_id)
        return index.exists()
    except Exception:
        return False


def run_reuse_mode():
    """Modalità --reuse: salta ingest, va diretto alla chat."""
    video_id = load_last_video_id()
    if not video_id:
        video_id = input("Nessun video_id salvato. Inserisci manualmente: ").strip()
        if not video_id:
            print("Errore: video_id vuoto")
            sys.exit(1)

    print(f"\n🎬 Video Chatbot - Modalità REUSE")
    print(f"Video ID: {video_id}")

    if not is_already_indexed(video_id):
        print(f"Errore: video {video_id} non trovato o non indicizzato")
        sys.exit(1)

    # Carica metadati
    meta_path = settings.DATA_DIR / "videos" / video_id / "metadata.json"
    if meta_path.exists():
        with open(meta_path) as f:
            metadata = json.load(f)
        print(f"  Durata: {metadata.get('duration', '?')}s")
        print(f"  Chunk: {metadata.get('chunks_count', '?')}")
        print(f"  Lingua: {metadata.get('language_detected', '?')}")

    print(f"\nVideo già indicizzato, salto ingest.")

    # Query di test
    with timed("Query di test completa"):
        test_question = "Di cosa parla questo video?"
        print(f"\n❓ Domanda: {test_question}\n")
        response = chat(video_id, test_question)
        print(f"💬 Risposta:\n{response['answer']}")
        print(f"\n🕐 Timestamp citati: {response['timestamps']}")

    print(f"\n✅ Test completato!")


def run_full_ingest(video_path: Path):
    """Ingest completo con chat progressiva."""
    # Valida configurazione
    settings.validate()

    video_id = compute_video_id(video_path)
    video_dir = settings.DATA_DIR / "videos" / video_id
    video_dir.mkdir(parents=True, exist_ok=True)

    print(f"\n🎬 Video Chatbot - Test Pipeline")
    print(f"Video: {video_path}")
    print(f"Video ID: {video_id}")
    print(f"Output: {video_dir}")

    # Controlla se già indicizzato
    if is_already_indexed(video_id):
        print(f"\nVideo già indicizzato, salto ingest.")
        save_last_video_id(video_id)

        # Carica metadati per riepilogo
        meta_path = video_dir / "metadata.json"
        if meta_path.exists():
            with open(meta_path) as f:
                metadata = json.load(f)
            print(f"  Durata: {metadata.get('duration', '?')}s")
            print(f"  Chunk: {metadata.get('chunks_count', '?')}")
            print(f"  Lingua: {metadata.get('language_detected', '?')}")

        # Query di test completa
        with timed("Query di test completa"):
            test_question = "Di cosa parla questo video?"
            print(f"\n❓ Domanda: {test_question}\n")
            response = chat(video_id, test_question)
            print(f"💬 Risposta:\n{response['answer']}")
            print(f"\n🕐 Timestamp citati: {response['timestamps']}")

        print(f"\n✅ Test completato!")
        return

    print(f"\nNuovo video, avvio ingest completo.")

    total_start = time.time()
    results = {"video_id": video_id, "video_path": str(video_path)}

    # 1. Durata video
    with timed("Lettura durata video"):
        duration = get_video_duration(str(video_path))
        results["duration"] = duration

    # 2. Estrazione audio
    with timed("Estrazione audio"):
        audio_path = extract_audio(str(video_path), str(video_dir))
        results["audio_path"] = audio_path

    # 3. Trascrizione
    with timed("Trascrizione audio (Groq Whisper)"):
        segments = transcribe_audio(audio_path)
        results["transcript_segments"] = len(segments)
        results["language_detected"] = segments[0].get("language_detected", "unknown") if segments else "unknown"

    # === SBLOCCO CHAT INTERMEDIO ===
    # Indicizza solo chunk trascrizione per abilitare chat parziale
    with timed("Indicizzazione chunk trascrizione (chat parziale)"):
        transcript_chunks = build_chunks(segments, [])
        index = VideoIndex(video_id)
        index.index_chunks(transcript_chunks)

    print(f"\n{'='*60}")
    print(f"[CHAT DISPONIBILE] Puoi già fare domande sull'audio")
    print(f"{'='*60}")

    # Query di test intermedia (solo trascrizione)
    with timed("Query di test intermedia (solo audio)"):
        test_question_1 = "Di cosa parla questo video?"
        print(f"\n❓ Domanda: {test_question_1}\n")
        response_1 = chat(video_id, test_question_1, transcript_only=True)
        print(f"💬 Risposta (solo audio):\n{response_1['answer']}")
        print(f"\n🕐 Timestamp citati: {response_1['timestamps']}")
        results["test_query_partial"] = {
            "question": test_question_1,
            "answer": response_1["answer"],
            "timestamps": response_1["timestamps"],
        }

    # 4. Estrazione frame
    with timed("Estrazione keyframe"):
        frames = extract_keyframes(str(video_path), str(video_dir), fps=settings.FRAMES_PER_SECOND)
        results["frames_extracted"] = len(frames)

    # 5. Analisi visiva
    with timed("Analisi frame (Claude Vision)"):
        frame_analyses = analyze_frames_batch(frames, max_frames=settings.MAX_FRAMES_TO_ANALYZE)
        results["frames_analyzed"] = len(frame_analyses)

    # 6. Chunking completo (trascrizione + frame)
    with timed("Creazione chunk completi"):
        all_chunks = build_chunks(segments, frame_analyses)
        results["total_chunks"] = len(all_chunks)

    # 7. Re-indicizzazione completa
    with timed("Indicizzazione completa ChromaDB"):
        indexed = index.index_chunks(all_chunks)
        results["chunks_indexed"] = indexed

    print(f"\n{'='*60}")
    print(f"[CHAT COMPLETA] Analisi immagini completata")
    print(f"{'='*60}")

    total_elapsed = time.time() - total_start

    # Riepilogo
    print(f"\n{'='*60}")
    print(f"📊 RIEPILOGO")
    print(f"{'='*60}")
    print(f"  Durata video:        {duration:.1f}s")
    print(f"  Segmenti trascritti: {len(segments)}")
    print(f"  Lingua rilevata:     {results['language_detected']}")
    print(f"  Frame estratti:      {len(frames)}")
    print(f"  Frame analizzati:    {len(frame_analyses)}")
    print(f"  Chunk totali:        {len(all_chunks)}")
    print(f"  Chunk indicizzati:   {indexed}")
    print(f"  Tempo totale:        {total_elapsed:.1f}s")

    # 8. Query di test completa (con frame)
    with timed("Query di test completa (audio + frame)"):
        test_question_2 = "Quali elementi visivi importanti ci sono nel video?"
        print(f"\n❓ Domanda: {test_question_2}\n")
        response_2 = chat(video_id, test_question_2)
        print(f"💬 Risposta (completa):\n{response_2['answer']}")
        print(f"\n🕐 Timestamp citati: {response_2['timestamps']}")
        results["test_query_full"] = {
            "question": test_question_2,
            "answer": response_2["answer"],
            "timestamps": response_2["timestamps"],
        }

    # Salva output completo
    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    output_path = settings.DATA_DIR / f"test_output_{timestamp}.json"

    results["transcript"] = segments
    results["frame_analyses"] = frame_analyses
    results["chunks"] = all_chunks
    results["total_time_seconds"] = total_elapsed

    with open(output_path, "w", encoding="utf-8") as f:
        json.dump(results, f, indent=2, ensure_ascii=False)

    print(f"\n📁 Output salvato: {output_path}")

    # Salva metadati video
    metadata = {
        "video_id": video_id,
        "duration": duration,
        "chunks_count": indexed,
        "transcript_segments": len(segments),
        "frames_analyzed": len(frame_analyses),
        "language_detected": results["language_detected"],
        "status": "ready",
    }
    meta_path = video_dir / "metadata.json"
    with open(meta_path, "w") as f:
        json.dump(metadata, f, indent=2, ensure_ascii=False)

    # Salva ultimo video_id
    save_last_video_id(video_id)

    print(f"✅ Pipeline completata con successo!")


def main():
    if len(sys.argv) < 2:
        print("Uso: python test_ingest.py <path/to/video.mp4> [--reuse]")
        sys.exit(1)

    reuse = "--reuse" in sys.argv
    video_args = [a for a in sys.argv[1:] if a != "--reuse"]

    if reuse:
        run_reuse_mode()
    else:
        if not video_args:
            print("Errore: specifica il path del video")
            sys.exit(1)
        video_path = Path(video_args[0])
        if not video_path.exists():
            print(f"Errore: file non trovato: {video_path}")
            sys.exit(1)
        run_full_ingest(video_path)


if __name__ == "__main__":
    main()
