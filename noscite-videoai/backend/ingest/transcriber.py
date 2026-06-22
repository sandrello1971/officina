import time
from pathlib import Path

from groq import Groq

from backend.config import settings
from backend.ingest.extractor import format_timestamp


def _split_audio_chunks(audio_path: str, chunk_duration: int = 600, overlap: int = 30) -> list[dict]:
    """Spezza audio in chunk da chunk_duration secondi con overlap.

    Ritorna lista di { path, offset } dove offset è il tempo di inizio in secondi.
    """
    import ffmpeg as ff

    audio_path = Path(audio_path)
    file_size = audio_path.stat().st_size
    max_size = 25 * 1024 * 1024  # 25 MB

    if file_size <= max_size:
        return [{"path": str(audio_path), "offset": 0.0}]

    print(f"[TRANSCRIBER] Audio > 25MB ({file_size / (1024*1024):.1f}MB), splitting in chunk...")
    chunks_dir = audio_path.parent / "audio_chunks"
    chunks_dir.mkdir(exist_ok=True)

    # Ottieni durata audio
    import subprocess, json
    result = subprocess.run(
        ["ffprobe", "-v", "quiet", "-print_format", "json", "-show_format", str(audio_path)],
        capture_output=True, text=True, check=True,
    )
    duration = float(json.loads(result.stdout)["format"]["duration"])

    chunks = []
    start = 0.0
    idx = 0
    while start < duration:
        chunk_path = chunks_dir / f"chunk_{idx:03d}.wav"
        try:
            (
                ff.input(str(audio_path), ss=start, t=chunk_duration)
                .output(str(chunk_path), acodec="pcm_s16le", ac=1, ar=16000)
                .overwrite_output()
                .run(quiet=True)
            )
            chunks.append({"path": str(chunk_path), "offset": start})
        except ff.Error:
            break
        start += chunk_duration - overlap
        idx += 1

    print(f"[TRANSCRIBER] Audio diviso in {len(chunks)} chunk")
    return chunks


def _transcribe_chunk(client: Groq, audio_path: str, max_retries: int = 3) -> dict:
    """Trascrive un singolo file audio con retry e backoff esponenziale."""
    for attempt in range(max_retries):
        try:
            with open(audio_path, "rb") as f:
                response = client.audio.transcriptions.create(
                    file=(Path(audio_path).name, f.read()),
                    model="whisper-large-v3",
                    language=None,
                    response_format="verbose_json",
                )
            return response
        except Exception as e:
            if attempt < max_retries - 1:
                wait = 2 ** (attempt + 1)
                print(f"[TRANSCRIBER] Errore API Groq (tentativo {attempt + 1}/{max_retries}): {e}")
                print(f"[TRANSCRIBER] Retry tra {wait}s...")
                time.sleep(wait)
            else:
                raise RuntimeError(f"Trascrizione fallita dopo {max_retries} tentativi: {e}")


def transcribe_audio(audio_path: str) -> list[dict]:
    """Trascrive audio usando Groq Whisper. Ritorna lista di segmenti con timestamp."""
    client = Groq(api_key=settings.GROQ_API_KEY)

    print(f"[TRANSCRIBER] Trascrizione in corso: {Path(audio_path).name}")

    audio_chunks = _split_audio_chunks(audio_path)
    all_segments = []
    language_detected = None

    for chunk_info in audio_chunks:
        response = _transcribe_chunk(client, chunk_info["path"])
        offset = chunk_info["offset"]

        # Rileva lingua dal primo chunk
        detected_lang = getattr(response, "language", None)
        if language_detected is None and detected_lang:
            language_detected = detected_lang
            print(f"[TRANSCRIBER] Lingua rilevata: {language_detected}")

        # Estrai segmenti
        segments = getattr(response, "segments", None) or []
        for seg in segments:
            start = seg.get("start", seg.get("start", 0)) + offset
            end = seg.get("end", seg.get("end", 0)) + offset
            text = seg.get("text", "").strip()
            if text:
                segment = {
                    "text": text,
                    "start": round(start, 2),
                    "end": round(end, 2),
                    "timestamp_str": format_timestamp(start),
                }
                if not all_segments:
                    segment["language_detected"] = language_detected or "unknown"
                all_segments.append(segment)

    print(f"[TRANSCRIBER] Trascrizione completata: {len(all_segments)} segmenti")
    return all_segments
