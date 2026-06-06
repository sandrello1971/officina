"""Store su file per job di trascrizione asincroni (audio + YouTube).

Separato dal ProgressTracker dei video (backend/api/progress.py): i job di
trascrizione non sono video indicizzati, quindi vivono in DATA_DIR/jobs/{job_id}
con un job.json autonomo. Stessa filosofia di scrittura atomica del tracker.
"""
import json
import uuid
from pathlib import Path

from backend.config import settings


def _jobs_root() -> Path:
    root = settings.DATA_DIR / "jobs"
    root.mkdir(parents=True, exist_ok=True)
    return root


class JobStore:
    """Gestisce lo stato di un singolo job su disco (job.json)."""

    def __init__(self, job_id: str):
        self.job_id = job_id
        self.dir = _jobs_root() / job_id
        self.path = self.dir / "job.json"

    @classmethod
    def create(cls, kind: str, **extra) -> "JobStore":
        """Crea un nuovo job (kind: 'audio' | 'youtube') e ritorna lo store."""
        store = cls(uuid.uuid4().hex)
        store.dir.mkdir(parents=True, exist_ok=True)
        data = {
            "job_id": store.job_id,
            "kind": kind,
            "status": "queued",      # queued | processing | completed | failed
            "progress": 0,
            "transcript": None,
            "segments": None,
            "language": None,
            "duration_seconds": None,
            "error": None,
        }
        data.update(extra)
        store._write(data)
        return store

    def _write(self, data: dict):
        tmp = self.path.with_suffix(".tmp")
        with open(tmp, "w", encoding="utf-8") as f:
            json.dump(data, f, indent=2, ensure_ascii=False)
        tmp.rename(self.path)

    def get(self) -> dict:
        if not self.path.exists():
            return {"status": "not_found"}
        with open(self.path, encoding="utf-8") as f:
            return json.load(f)

    def exists(self) -> bool:
        return self.path.exists()

    def update(self, **fields) -> dict:
        """Aggiorna i campi indicati e riscrive job.json."""
        data = self.get()
        if data.get("status") == "not_found":
            data = {"job_id": self.job_id}
        data.update(fields)
        self._write(data)
        return data

    def set_error(self, reason: str) -> dict:
        return self.update(status="failed", error=reason)
