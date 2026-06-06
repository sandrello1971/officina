"""Trascrizione di file audio caricati, riusando la pipeline Whisper esistente.

Flusso: normalizza l'audio in WAV mono 16kHz (extract_audio funziona anche con
input solo-audio), trascrive con Groq Whisper (transcribe_audio, che gestisce
già lo split dei file > 25MB), normalizza in testo continuo.
"""
import shutil
from pathlib import Path

from backend.config import settings
from backend.ingest.extractor import extract_audio, get_video_duration
from backend.ingest.transcriber import transcribe_audio
from backend.jobs import JobStore
from backend.transcription.normalize import normalize_transcript, to_public_segments

# Estensioni audio accettate dall'endpoint
ALLOWED_AUDIO_EXTENSIONS = {".mp3", ".m4a", ".wav", ".ogg"}


def transcribe_audio_file(audio_path: str, window_seconds: float = 30.0) -> dict:
    """Trascrive un file audio già su disco. Ritorna {transcript, language,
    duration_seconds}. Solleva ValueError se la durata supera il limite."""
    duration = get_video_duration(audio_path)
    if duration > settings.MAX_TRANSCRIBE_DURATION_SECONDS:
        raise ValueError(
            f"Durata audio {int(duration)}s oltre il limite di "
            f"{settings.MAX_TRANSCRIBE_DURATION_SECONDS}s"
        )

    # Normalizza in WAV mono 16kHz nella cartella del file
    wav_path = extract_audio(audio_path, str(Path(audio_path).parent))
    segments = transcribe_audio(wav_path)
    language = segments[0].get("language_detected", "unknown") if segments else "unknown"

    normalized = normalize_transcript(segments, window_seconds)
    return {
        "transcript": normalized["text"],
        "language": language,
        "duration_seconds": round(duration, 2),
        # Whisper: segment nativi (granularità fine)
        "segments": to_public_segments(segments),
    }


def run_audio_job(job_id: str, audio_path: str):
    """Esegue un job di trascrizione audio in background e aggiorna job.json.

    Pulisce sempre i file temporanei (upload + WAV + chunk) a fine elaborazione,
    mantenendo solo job.json.
    """
    store = JobStore(job_id)
    job_dir = Path(audio_path).parent
    try:
        store.update(status="processing", progress=20)
        result = transcribe_audio_file(audio_path)
        store.update(
            status="completed",
            progress=100,
            transcript=result["transcript"],
            language=result["language"],
            duration_seconds=result["duration_seconds"],
            segments=result["segments"],
        )
    except Exception as e:
        store.set_error(str(e))
        print(f"[AUDIO-JOB] Errore job {job_id}: {e}")
    finally:
        _cleanup_temp(job_dir, keep={"job.json"})


def _cleanup_temp(job_dir: Path, keep: set[str]):
    """Rimuove dalla cartella del job tutto tranne i file in `keep`."""
    if not job_dir.exists():
        return
    for entry in job_dir.iterdir():
        if entry.name in keep:
            continue
        try:
            if entry.is_dir():
                shutil.rmtree(entry)
            else:
                entry.unlink()
        except OSError as e:
            print(f"[AUDIO-JOB] Pulizia fallita per {entry}: {e}")
