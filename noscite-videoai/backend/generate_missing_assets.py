import sys
import os
import json
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent.parent))
os.environ["PYTORCH_MPS_HIGH_WATERMARK_RATIO"] = "0.0"
os.environ["TOKENIZERS_PARALLELISM"] = "false"

from backend.ingest.extractor import extract_thumbnail
from backend.chat.engine import generate_video_summary, generate_tags
from backend.storage.database import get_db
from backend.config import settings

data_dir = Path(settings.DATA_DIR) / "videos"
db = get_db()

thumb_count = 0
summary_count = 0
tags_count = 0

for video_dir in sorted(data_dir.iterdir()):
    if not video_dir.is_dir():
        continue

    video_id = video_dir.name
    metadata_path = video_dir / "metadata.json"

    if not metadata_path.exists():
        continue

    with open(metadata_path) as f:
        metadata = json.load(f)

    # Trova file video
    video_file = None
    for ext in [".mp4", ".mov", ".avi", ".webm"]:
        for f in video_dir.iterdir():
            if f.suffix.lower() == ext and "audio" not in f.name:
                video_file = f
                break
        if video_file:
            break

    # Genera thumbnail se manca
    if video_file and not (video_dir / "thumbnail.jpg").exists():
        try:
            extract_thumbnail(str(video_file), str(video_dir))
            print(f"[THUMB] {video_id[:8]}... → thumbnail.jpg")
            thumb_count += 1
        except Exception as e:
            print(f"[THUMB] Errore {video_id[:8]}: {e}")

    # Genera summary se manca
    if "summary" not in metadata or not metadata["summary"]:
        try:
            summary = generate_video_summary(video_id)
            print(f"[SUMMARY] {video_id[:8]}... → {summary}")
            summary_count += 1
        except Exception as e:
            print(f"[SUMMARY] Errore {video_id[:8]}: {e}")

    # Genera tag se mancanti
    video_record = db.get(video_id)
    has_tags = video_record and video_record.get("tags") and video_record["tags"] != [] and video_record["tags"] != "[]"
    if not has_tags:
        try:
            tags = generate_tags(video_id)
            if tags:
                db.update_metadata(video_id, tags=tags)
                print(f"[TAGS] {video_id[:8]}... → {tags}")
                tags_count += 1
        except Exception as e:
            print(f"[TAGS] Errore {video_id[:8]}: {e}")

print(f"\nCompletato: {thumb_count} thumbnail, {summary_count} summary, {tags_count} tag generati")

# Ricalcola correlazioni
from backend.storage.correlator import update_all_correlations
print("\n[CORRELATOR] Ricalcolo tutte le correlazioni...")
n = update_all_correlations()
print(f"[CORRELATOR] {n} correlazioni salvate")
