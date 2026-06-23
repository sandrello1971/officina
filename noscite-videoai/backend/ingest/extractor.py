import subprocess
import json
from pathlib import Path

import ffmpeg
from tqdm import tqdm


def format_timestamp(seconds: float) -> str:
    """Converte secondi in MM:SS o HH:MM:SS (usa HH solo se >= 3600s)."""
    seconds = max(0, seconds)
    hours = int(seconds // 3600)
    minutes = int((seconds % 3600) // 60)
    secs = int(seconds % 60)
    if hours > 0:
        return f"{hours:02d}:{minutes:02d}:{secs:02d}"
    return f"{minutes:02d}:{secs:02d}"


def get_video_duration(video_path: str) -> float:
    """Ritorna durata in secondi usando ffprobe."""
    video_path = str(video_path)
    try:
        result = subprocess.run(
            [
                "ffprobe",
                "-v", "quiet",
                "-print_format", "json",
                "-show_format",
                video_path,
            ],
            capture_output=True,
            text=True,
            check=True,
        )
        info = json.loads(result.stdout)
        duration = float(info["format"]["duration"])
        print(f"[EXTRACTOR] Durata video: {format_timestamp(duration)} ({duration:.1f}s)")
        return duration
    except (subprocess.CalledProcessError, KeyError, json.JSONDecodeError) as e:
        raise RuntimeError(f"Impossibile leggere la durata del video: {e}")


def extract_audio(video_path: str, output_dir: str) -> str:
    """Estrae audio mono 16kHz WAV dal video."""
    video_path = Path(video_path)
    output_dir = Path(output_dir)
    output_dir.mkdir(parents=True, exist_ok=True)

    audio_path = output_dir / f"{video_path.stem}_audio.wav"

    print(f"[EXTRACTOR] Estrazione audio da {video_path.name}...")
    try:
        (
            ffmpeg
            .input(str(video_path))
            .output(
                str(audio_path),
                acodec="pcm_s16le",
                ac=1,
                ar=16000,
            )
            .overwrite_output()
            .run(quiet=True)
        )
    except ffmpeg.Error as e:
        raise RuntimeError(
            f"Errore ffmpeg durante estrazione audio: {e.stderr.decode() if e.stderr else str(e)}"
        )

    size_mb = audio_path.stat().st_size / (1024 * 1024)
    print(f"[EXTRACTOR] Audio estratto: {audio_path.name} ({size_mb:.1f} MB)")
    return str(audio_path)


def extract_keyframes(
    video_path: str, output_dir: str, fps: float = 0.5
) -> list[dict]:
    """Estrae frame a intervalli regolari con fps configurabile."""
    video_path = Path(video_path)
    output_dir = Path(output_dir)
    frames_dir = output_dir / "frames"
    frames_dir.mkdir(parents=True, exist_ok=True)

    duration = get_video_duration(str(video_path))
    total_frames = int(duration * fps)
    interval = 1.0 / fps

    print(f"[EXTRACTOR] Estrazione {total_frames} frame (1 ogni {interval:.1f}s)...")

    # Estrai frame con ffmpeg
    output_pattern = str(frames_dir / "frame_%06d.jpg")
    try:
        (
            ffmpeg
            .input(str(video_path))
            .filter("fps", fps=fps)
            .output(output_pattern, qscale=2)
            .overwrite_output()
            .run(quiet=True)
        )
    except ffmpeg.Error as e:
        raise RuntimeError(
            f"Errore ffmpeg durante estrazione frame: {e.stderr.decode() if e.stderr else str(e)}"
        )

    # Costruisci lista frame con metadati
    frames = []
    frame_files = sorted(frames_dir.glob("frame_*.jpg"))

    for idx, frame_path in enumerate(tqdm(frame_files, desc="[EXTRACTOR] Indicizzazione frame")):
        timestamp_seconds = idx * interval
        frames.append({
            "path": str(frame_path),
            "timestamp_seconds": round(timestamp_seconds, 2),
            "timestamp_str": format_timestamp(timestamp_seconds),
            "frame_index": idx,
        })

    print(f"[EXTRACTOR] Estratti {len(frames)} frame in {frames_dir}")
    return frames


def extract_thumbnail(video_path: str, output_dir: str) -> str:
    """Estrae un singolo frame al 10% della durata come thumbnail 640x360."""
    video_path = Path(video_path)
    output_dir = Path(output_dir)
    output_dir.mkdir(parents=True, exist_ok=True)
    thumbnail_path = output_dir / "thumbnail.jpg"

    try:
        duration = get_video_duration(str(video_path))
        seek_time = max(0.5, duration * 0.1)

        (
            ffmpeg
            .input(str(video_path), ss=seek_time)
            .filter("scale", 640, 360)
            .output(str(thumbnail_path), vframes=1, qscale=2)
            .overwrite_output()
            .run(quiet=True)
        )
        print(f"[EXTRACTOR] Thumbnail estratta: {thumbnail_path}")
        return str(thumbnail_path)
    except Exception as e:
        print(f"[EXTRACTOR] Thumbnail non estratta (non critico): {e}")
        return ""
