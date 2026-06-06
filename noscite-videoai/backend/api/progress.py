import json
from pathlib import Path

from backend.config import settings


STEP_LABELS = {
    "upload": "Upload video...",
    "extraction": "Estrazione audio...",
    "transcription": "Trascrizione in corso...",
    "frames": "Analisi frame...",
    "indexing": "Indicizzazione...",
    "correlations": "Calcolo correlazioni...",
    "ready": "Pronto!",
}

CHAT_LABELS = {
    False: "Chat non ancora disponibile",
    True: "Chat disponibile solo su audio",
    "full": "Chat completa disponibile",
}


class ProgressTracker:
    def __init__(self, video_id: str):
        self.video_id = video_id
        self.video_dir = settings.DATA_DIR / "videos" / video_id
        self.video_dir.mkdir(parents=True, exist_ok=True)
        self.progress_path = self.video_dir / "progress.json"

    def update(self, step: str, progress: int, can_chat: bool = False, error: str = None):
        """Scrive atomicamente progress.json."""
        status = "error" if error else ("ready" if step == "ready" else "processing")

        if can_chat and step == "ready":
            can_chat_label = "Chat completa disponibile"
        elif can_chat:
            can_chat_label = "Chat disponibile solo su audio"
        else:
            can_chat_label = "Chat non ancora disponibile"

        data = {
            "video_id": self.video_id,
            "status": status,
            "step": step,
            "step_label": STEP_LABELS.get(step, step),
            "progress": progress,
            "can_chat": can_chat,
            "can_chat_label": can_chat_label,
            "error": error,
        }

        # Scrittura atomica: scrivi su .tmp poi rinomina
        tmp_path = self.progress_path.with_suffix(".tmp")
        with open(tmp_path, "w", encoding="utf-8") as f:
            json.dump(data, f, indent=2, ensure_ascii=False)
        tmp_path.rename(self.progress_path)

    def get(self) -> dict:
        """Legge e ritorna progress.json."""
        if not self.progress_path.exists():
            return {"status": "not_found"}
        with open(self.progress_path, encoding="utf-8") as f:
            return json.load(f)

    def set_error(self, error: str):
        """Imposta status=error con messaggio."""
        current = self.get()
        step = current.get("step", "unknown") if current.get("status") != "not_found" else "unknown"
        progress = current.get("progress", 0) if current.get("status") != "not_found" else 0
        self.update(step=step, progress=progress, can_chat=current.get("can_chat", False), error=str(error))
