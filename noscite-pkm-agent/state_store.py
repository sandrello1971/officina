"""
state_store.py — Store SQLite locale per tracciare i file processati dal PKM Agent.

Evita duplicati, gestisce retry, persiste tra riavvii.
"""

import sqlite3
from datetime import datetime
from pathlib import Path


SCHEMA = """
CREATE TABLE IF NOT EXISTS processed (
    item_id TEXT PRIMARY KEY,
    filename TEXT NOT NULL,
    status TEXT NOT NULL,
    attempts INTEGER DEFAULT 0,
    last_error TEXT,
    processed_at TEXT NOT NULL,
    created_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_status ON processed(status);
"""


class StateStore:
    def __init__(self, db_path: Path):
        self.db_path = db_path
        self._init_db()

    def _conn(self) -> sqlite3.Connection:
        conn = sqlite3.connect(str(self.db_path))
        conn.row_factory = sqlite3.Row
        return conn

    def _init_db(self) -> None:
        with self._conn() as c:
            c.executescript(SCHEMA)

    def _get(self, item_id: str) -> sqlite3.Row | None:
        with self._conn() as c:
            row = c.execute(
                "SELECT * FROM processed WHERE item_id = ?", (item_id,)
            ).fetchone()
        return row

    def has_been_processed(self, item_id: str) -> bool:
        row = self._get(item_id)
        return bool(row) and row["status"] == "ok"

    def should_skip(self, item_id: str, max_attempts: int = 3) -> bool:
        row = self._get(item_id)
        if not row:
            return False
        if row["status"] == "ok":
            return True
        if row["attempts"] >= max_attempts:
            return True
        return False

    def mark_ok(self, item_id: str, filename: str) -> None:
        now = datetime.utcnow().isoformat()
        with self._conn() as c:
            existing = c.execute(
                "SELECT created_at FROM processed WHERE item_id = ?", (item_id,)
            ).fetchone()
            created_at = existing["created_at"] if existing else now
            c.execute(
                """
                INSERT INTO processed (item_id, filename, status, attempts, last_error, processed_at, created_at)
                VALUES (?, ?, 'ok', COALESCE((SELECT attempts FROM processed WHERE item_id = ?), 0) + 1, NULL, ?, ?)
                ON CONFLICT(item_id) DO UPDATE SET
                    status = 'ok',
                    attempts = processed.attempts + 1,
                    last_error = NULL,
                    processed_at = excluded.processed_at,
                    filename = excluded.filename
                """,
                (item_id, filename, item_id, now, created_at),
            )

    def mark_error(self, item_id: str, filename: str, error: str) -> None:
        now = datetime.utcnow().isoformat()
        with self._conn() as c:
            existing = c.execute(
                "SELECT created_at FROM processed WHERE item_id = ?", (item_id,)
            ).fetchone()
            created_at = existing["created_at"] if existing else now
            c.execute(
                """
                INSERT INTO processed (item_id, filename, status, attempts, last_error, processed_at, created_at)
                VALUES (?, ?, 'error', 1, ?, ?, ?)
                ON CONFLICT(item_id) DO UPDATE SET
                    status = 'error',
                    attempts = processed.attempts + 1,
                    last_error = excluded.last_error,
                    processed_at = excluded.processed_at,
                    filename = excluded.filename
                """,
                (item_id, filename, error[:500], now, created_at),
            )

    def mark_skipped(self, item_id: str, filename: str, reason: str) -> None:
        now = datetime.utcnow().isoformat()
        with self._conn() as c:
            c.execute(
                """
                INSERT INTO processed (item_id, filename, status, attempts, last_error, processed_at, created_at)
                VALUES (?, ?, 'skipped', 0, ?, ?, ?)
                ON CONFLICT(item_id) DO UPDATE SET
                    status = 'skipped',
                    last_error = excluded.last_error,
                    processed_at = excluded.processed_at
                """,
                (item_id, filename, reason[:500], now, now),
            )

    def reset(self, item_id: str) -> None:
        with self._conn() as c:
            c.execute("DELETE FROM processed WHERE item_id = ?", (item_id,))
