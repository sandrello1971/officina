#!/usr/bin/env python3
"""Migra i video esistenti (da metadata.json) nel database SQLite."""

import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent.parent))

from backend.config import settings
from backend.chat.engine import generate_tags
from backend.storage.database import get_db
from backend.ingest.extractor import format_timestamp


def main():
    db = get_db()
    videos_dir = settings.DATA_DIR / "videos"

    if not videos_dir.exists():
        print("Nessuna cartella data/videos/ trovata.")
        return

    count = 0
    for video_dir in sorted(videos_dir.iterdir()):
        if not video_dir.is_dir():
            continue

        meta_path = video_dir / "metadata.json"
        if not meta_path.exists():
            continue

        with open(meta_path) as f:
            metadata = json.load(f)

        video_id = metadata.get("video_id", video_dir.name)

        # Filename: cerca il file video nella cartella
        filename = metadata.get("filename", "")
        if not filename:
            video_files = [
                p.name for p in video_dir.iterdir()
                if p.is_file() and p.suffix.lower() in (".mp4", ".mov", ".avi", ".webm", ".mkv")
            ]
            filename = video_files[0] if video_files else "video"

        duration = metadata.get("duration", 0)
        has_thumbnail = 1 if (video_dir / "thumbnail.jpg").exists() else 0
        language = metadata.get("language_detected", metadata.get("language", ""))
        summary = metadata.get("summary", "")

        # Use file mtime as created_at
        created_at = meta_path.stat().st_mtime
        from datetime import datetime, timezone
        created_iso = datetime.fromtimestamp(created_at, tz=timezone.utc).isoformat()

        title = Path(filename).stem.replace("_", " ").replace("-", " ")

        db.upsert(video_id, {
            "filename": filename,
            "title": title,
            "summary": summary,
            "duration_seconds": duration,
            "duration_str": format_timestamp(duration),
            "language": language,
            "chunks_count": metadata.get("chunks_count", 0),
            "has_thumbnail": has_thumbnail,
            "collection": "Generale",
            "tags": "[]",
            "notes": "",
            "created_at": created_iso,
        })

        print(f"[DB] Migrato: {filename} ({video_id[:8]}...)")

        # Genera tag se mancanti
        existing = db.get(video_id)
        if existing and (not existing.get("tags") or existing["tags"] == [] or existing["tags"] == "[]"):
            try:
                tags = generate_tags(video_id)
                if tags:
                    db.update_metadata(video_id, tags=tags)
                    print(f"[DB] Tag generati: {tags}")
            except Exception as e:
                print(f"[DB] Errore tag per {video_id[:8]}: {e}")

        count += 1

    print(f"\nMigrazione completata: {count} video")


if __name__ == "__main__":
    main()
