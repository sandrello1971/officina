"""Trascrizione di video YouTube con strategia a cascata.

1. youtube-transcript-api: se esistono sottotitoli (preferendo it/en) li usa
   direttamente -> method = 'native_transcript'.
2. fallback: yt-dlp scarica l'audio alla qualità minima sufficiente e lo passa
   alla pipeline Whisper esistente -> method = 'whisper'.

Le funzioni che toccano la rete (fetch_metadata, fetch_native_transcript,
download_audio) sono a livello di modulo così da poter essere mockate nei test;
il dispatcher transcribe_youtube contiene solo la logica di cascata.
"""
import re
import shutil
from pathlib import Path

import yt_dlp
from yt_dlp.utils import DownloadError
from youtube_transcript_api import YouTubeTranscriptApi
from youtube_transcript_api import YouTubeTranscriptApiException

from backend.config import settings
from backend.ingest.transcriber import transcribe_audio
from backend.jobs import JobStore
from backend.transcription.normalize import normalize_transcript, to_public_segments

PREFERRED_LANGUAGES = ("it", "en")

_VIDEO_ID_PATTERNS = [
    re.compile(r"(?:v=|/videos/|/embed/|/shorts/|youtu\.be/|/v/)([0-9A-Za-z_-]{11})"),
]


class YouTubeError(Exception):
    """Errore esplicito su un video YouTube (privato/rimosso/region-locked/...)."""

    def __init__(self, reason: str):
        self.reason = reason
        super().__init__(reason)


def extract_video_id(url: str) -> str | None:
    """Estrae l'ID a 11 caratteri dalle varie forme di URL YouTube."""
    for pattern in _VIDEO_ID_PATTERNS:
        m = pattern.search(url)
        if m:
            return m.group(1)
    # URL già ridotto al solo ID
    if re.fullmatch(r"[0-9A-Za-z_-]{11}", url.strip()):
        return url.strip()
    return None


def map_download_error(message: str) -> str:
    """Mappa il messaggio di errore yt-dlp in una reason leggibile."""
    low = message.lower()
    if "private" in low:
        return "Video privato"
    if "age" in low and "restrict" in low:
        return "Video con restrizione d'età"
    if any(s in low for s in ("not available in your country", "geo", "region")):
        return "Video non disponibile nella regione del server (region-locked)"
    if any(s in low for s in ("removed", "no longer available", "unavailable",
                              "terminated", "deleted", "does not exist")):
        return "Video rimosso o non disponibile"
    if any(s in low for s in ("sign in to confirm", "not a bot", "cookies", "bot")):
        return "YouTube ha bloccato la richiesta dal server (verifica anti-bot/login)"
    return f"Errore download YouTube: {message}"


def fetch_metadata(url: str) -> dict:
    """Metadati video senza scaricare. Solleva YouTubeError su video non
    accessibili. Ritorna {video_id, title, channel, duration_seconds}."""
    opts = {"quiet": True, "no_warnings": True, "skip_download": True}
    try:
        with yt_dlp.YoutubeDL(opts) as ydl:
            info = ydl.extract_info(url, download=False)
    except DownloadError as e:
        raise YouTubeError(map_download_error(str(e)))
    return {
        "video_id": info.get("id"),
        "title": info.get("title"),
        "channel": info.get("channel") or info.get("uploader"),
        "duration_seconds": info.get("duration"),
    }


def fetch_native_transcript(video_id: str, languages=PREFERRED_LANGUAGES) -> list[dict] | None:
    """Sottotitoli nativi via youtube-transcript-api.

    Ritorna segmenti [{text, start, end, (language_detected sul primo)}] oppure
    None se non esistono sottotitoli (innesca il fallback Whisper).
    """
    try:
        fetched = YouTubeTranscriptApi().fetch(video_id, languages=list(languages))
    except YouTubeTranscriptApiException:
        return None

    language_code = getattr(fetched, "language_code", None)
    segments: list[dict] = []
    for item in fetched.to_raw_data():
        text = (item.get("text") or "").strip()
        if not text:
            continue
        start = float(item.get("start", 0) or 0)
        duration = float(item.get("duration", 0) or 0)
        seg = {"text": text, "start": start, "end": start + duration}
        if not segments:
            seg["language_detected"] = language_code or "unknown"
        segments.append(seg)

    return segments or None


def download_audio(url: str, out_dir: str) -> str:
    """Scarica l'audio (qualità minima sufficiente) e ritorna il path del file.

    Solleva YouTubeError su errori di download.
    """
    out_tmpl = str(Path(out_dir) / "yt_audio.%(ext)s")
    opts = {
        "quiet": True,
        "no_warnings": True,
        "format": "worstaudio/worst",
        "outtmpl": out_tmpl,
        "postprocessors": [{
            "key": "FFmpegExtractAudio",
            "preferredcodec": "mp3",
            "preferredquality": "64",
        }],
    }
    try:
        with yt_dlp.YoutubeDL(opts) as ydl:
            ydl.download([url])
    except DownloadError as e:
        raise YouTubeError(map_download_error(str(e)))

    produced = sorted(Path(out_dir).glob("yt_audio.*"))
    if not produced:
        raise YouTubeError("Download audio fallito: nessun file prodotto")
    return str(produced[0])


def transcribe_youtube(
    url: str,
    max_duration_seconds: int | None = None,
    window_seconds: float = 30.0,
    work_dir: str | None = None,
) -> dict:
    """Dispatcher a cascata. Ritorna {method, transcript, language,
    duration_seconds, metadata}. Solleva YouTubeError su errori espliciti."""
    if max_duration_seconds is None:
        max_duration_seconds = settings.MAX_TRANSCRIBE_DURATION_SECONDS

    meta = fetch_metadata(url)
    duration = meta.get("duration_seconds")
    if duration and duration > max_duration_seconds:
        raise YouTubeError(
            f"Durata {int(duration)}s oltre il limite di {max_duration_seconds}s"
        )

    video_id = meta.get("video_id") or extract_video_id(url)
    if not video_id:
        raise YouTubeError("Impossibile determinare l'ID del video YouTube")

    # 1) sottotitoli nativi
    segments = fetch_native_transcript(video_id)
    if segments:
        method = "native_transcript"
    else:
        # 2) fallback: download audio + Whisper
        if not work_dir:
            raise YouTubeError("work_dir richiesto per il fallback Whisper")
        audio_path = download_audio(url, work_dir)
        segments = transcribe_audio(audio_path)
        method = "whisper"

    if not segments:
        raise YouTubeError("Nessuna trascrizione ottenuta dal video")

    language = segments[0].get("language_detected", "unknown")
    normalized = normalize_transcript(segments, window_seconds)
    # Whisper -> segment nativi; caption native -> paragrafi raggruppati
    public_segments = to_public_segments(
        segments if method == "whisper" else normalized["paragraphs"]
    )
    return {
        "method": method,
        "transcript": normalized["text"],
        "segments": public_segments,
        "language": language,
        "duration_seconds": round(duration, 2) if duration else None,
        "metadata": {
            "title": meta.get("title"),
            "channel": meta.get("channel"),
            "duration_seconds": duration,
        },
    }


def run_youtube_job(job_id: str, url: str):
    """Esegue un job di trascrizione YouTube in background e aggiorna job.json.
    Pulisce sempre i file temporanei a fine elaborazione."""
    store = JobStore(job_id)
    work_dir = store.dir
    try:
        store.update(status="processing", progress=15)
        result = transcribe_youtube(url, work_dir=str(work_dir))
        store.update(
            status="completed",
            progress=100,
            transcript=result["transcript"],
            segments=result["segments"],
            language=result["language"],
            duration_seconds=result["duration_seconds"],
            method=result["method"],
            metadata=result["metadata"],
        )
    except YouTubeError as e:
        store.set_error(e.reason)
        print(f"[YT-JOB] Errore esplicito job {job_id}: {e.reason}")
    except Exception as e:
        store.set_error(str(e))
        print(f"[YT-JOB] Errore job {job_id}: {e}")
    finally:
        _cleanup_temp(work_dir, keep={"job.json"})


def _cleanup_temp(job_dir: Path, keep: set[str]):
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
            print(f"[YT-JOB] Pulizia fallita per {entry}: {e}")
