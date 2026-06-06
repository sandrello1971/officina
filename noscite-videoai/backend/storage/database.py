import json
import sqlite3
from datetime import datetime, timezone
from pathlib import Path

from backend.config import settings

SCHEMA = """
CREATE TABLE IF NOT EXISTS videos (
    video_id        TEXT PRIMARY KEY,
    filename        TEXT NOT NULL,
    title           TEXT NOT NULL DEFAULT '',
    summary         TEXT NOT NULL DEFAULT '',
    duration_seconds REAL DEFAULT 0,
    duration_str    TEXT DEFAULT '00:00',
    language        TEXT DEFAULT '',
    chunks_count    INTEGER DEFAULT 0,
    has_thumbnail   INTEGER DEFAULT 0,
    collection      TEXT DEFAULT 'Generale',
    tags            TEXT DEFAULT '[]',
    notes           TEXT DEFAULT '',
    created_at      TEXT NOT NULL,
    updated_at      TEXT NOT NULL
);

CREATE VIRTUAL TABLE IF NOT EXISTS videos_fts USING fts5(
    video_id UNINDEXED,
    title,
    summary,
    tags,
    notes,
    collection,
    content='videos',
    content_rowid='rowid'
);

CREATE TRIGGER IF NOT EXISTS videos_ai AFTER INSERT ON videos BEGIN
    INSERT INTO videos_fts(video_id, title, summary, tags, notes, collection)
    VALUES (new.video_id, new.title, new.summary, new.tags, new.notes, new.collection);
END;

CREATE TRIGGER IF NOT EXISTS videos_au AFTER UPDATE ON videos BEGIN
    INSERT INTO videos_fts(videos_fts, video_id, title, summary, tags, notes, collection)
    VALUES ('delete', old.video_id, old.title, old.summary, old.tags, old.notes, old.collection);
    INSERT INTO videos_fts(video_id, title, summary, tags, notes, collection)
    VALUES (new.video_id, new.title, new.summary, new.tags, new.notes, new.collection);
END;

CREATE TRIGGER IF NOT EXISTS videos_ad AFTER DELETE ON videos BEGIN
    INSERT INTO videos_fts(videos_fts, video_id, title, summary, tags, notes, collection)
    VALUES ('delete', old.video_id, old.title, old.summary, old.tags, old.notes, old.collection);
END;

CREATE TABLE IF NOT EXISTS video_correlations (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    video_id_a      TEXT NOT NULL,
    video_id_b      TEXT NOT NULL,
    score           REAL NOT NULL,
    reasons         TEXT NOT NULL DEFAULT '[]',
    created_at      TEXT NOT NULL,
    UNIQUE(video_id_a, video_id_b),
    FOREIGN KEY(video_id_a) REFERENCES videos(video_id) ON DELETE CASCADE,
    FOREIGN KEY(video_id_b) REFERENCES videos(video_id) ON DELETE CASCADE
);
"""


def _now() -> str:
    return datetime.now(timezone.utc).isoformat()


def _row_to_dict(row: sqlite3.Row) -> dict:
    d = dict(row)
    # Parse tags JSON string to list for API consumers
    if "tags" in d and isinstance(d["tags"], str):
        try:
            d["tags"] = json.loads(d["tags"])
        except (json.JSONDecodeError, TypeError):
            d["tags"] = []
    return d


class VideoDatabase:
    def __init__(self, db_path: str | None = None):
        if db_path is None:
            db_path = str(settings.DATA_DIR / "library.db")
        Path(db_path).parent.mkdir(parents=True, exist_ok=True)

        self.conn = sqlite3.connect(db_path, check_same_thread=False)
        self.conn.row_factory = sqlite3.Row
        self.conn.executescript(SCHEMA)
        self.conn.commit()

    def upsert(self, video_id: str, data: dict) -> None:
        now = _now()
        # Check if exists to preserve created_at
        existing = self.get(video_id)
        created_at = data.get("created_at") or (existing["created_at"] if existing else now)
        tags = data.get("tags", "[]")
        if isinstance(tags, list):
            tags = json.dumps(tags, ensure_ascii=False)

        self.conn.execute(
            """INSERT OR REPLACE INTO videos
               (video_id, filename, title, summary, duration_seconds, duration_str,
                language, chunks_count, has_thumbnail, collection, tags, notes,
                created_at, updated_at)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)""",
            (
                video_id,
                data.get("filename", ""),
                data.get("title", ""),
                data.get("summary", ""),
                data.get("duration_seconds", 0),
                data.get("duration_str", "00:00"),
                data.get("language", ""),
                data.get("chunks_count", 0),
                data.get("has_thumbnail", 0),
                data.get("collection", existing["collection"] if existing else "Generale"),
                tags,
                data.get("notes", existing["notes"] if existing else ""),
                created_at,
                now,
            ),
        )
        self.conn.commit()

    def get(self, video_id: str) -> dict | None:
        row = self.conn.execute(
            "SELECT * FROM videos WHERE video_id = ?", (video_id,)
        ).fetchone()
        return _row_to_dict(row) if row else None

    def update_metadata(
        self,
        video_id: str,
        title: str | None = None,
        tags: list | None = None,
        collection: str | None = None,
        notes: str | None = None,
    ) -> dict | None:
        existing = self.get(video_id)
        if not existing:
            return None

        updates = []
        params = []
        if title is not None:
            updates.append("title = ?")
            params.append(title)
        if tags is not None:
            updates.append("tags = ?")
            params.append(json.dumps(tags, ensure_ascii=False))
        if collection is not None:
            updates.append("collection = ?")
            params.append(collection)
        if notes is not None:
            updates.append("notes = ?")
            params.append(notes)

        if not updates:
            return existing

        updates.append("updated_at = ?")
        params.append(_now())
        params.append(video_id)

        self.conn.execute(
            f"UPDATE videos SET {', '.join(updates)} WHERE video_id = ?",
            params,
        )
        self.conn.commit()
        return self.get(video_id)

    def delete(self, video_id: str) -> bool:
        cursor = self.conn.execute(
            "DELETE FROM videos WHERE video_id = ?", (video_id,)
        )
        self.conn.commit()
        return cursor.rowcount > 0

    def search(
        self,
        query: str = "",
        language: str = "",
        collection: str = "",
        tags: list | None = None,
        sort_by: str = "created_at",
        sort_dir: str = "desc",
    ) -> list[dict]:
        allowed_sort = {"created_at", "duration_seconds", "title", "updated_at"}
        if sort_by not in allowed_sort:
            sort_by = "created_at"
        if sort_dir not in ("asc", "desc"):
            sort_dir = "desc"

        if query.strip():
            # FTS search
            fts_query = " ".join(f'"{w}"*' for w in query.strip().split() if w)
            sql = """SELECT v.* FROM videos v
                     JOIN videos_fts f ON v.video_id = f.video_id
                     WHERE videos_fts MATCH ?"""
            params = [fts_query]
        else:
            sql = "SELECT * FROM videos WHERE 1=1"
            params = []

        if language:
            sql += " AND language = ?"
            params.append(language)
        if collection:
            sql += " AND collection = ?"
            params.append(collection)
        if tags:
            for tag in tags:
                sql += " AND tags LIKE ?"
                params.append(f"%{tag}%")

        sql += f" ORDER BY {sort_by} {sort_dir}"

        rows = self.conn.execute(sql, params).fetchall()
        return [_row_to_dict(r) for r in rows]

    def list_collections(self) -> list[str]:
        rows = self.conn.execute(
            "SELECT DISTINCT collection FROM videos ORDER BY collection"
        ).fetchall()
        return [r["collection"] for r in rows if r["collection"]]

    def list_languages(self) -> list[str]:
        rows = self.conn.execute(
            "SELECT DISTINCT language FROM videos WHERE language != '' ORDER BY language"
        ).fetchall()
        return [r["language"] for r in rows]

    def list_all_tags(self) -> list[str]:
        rows = self.conn.execute("SELECT tags FROM videos").fetchall()
        all_tags = set()
        for r in rows:
            try:
                parsed = json.loads(r["tags"]) if isinstance(r["tags"], str) else r["tags"]
                if isinstance(parsed, list):
                    all_tags.update(t for t in parsed if t)
            except (json.JSONDecodeError, TypeError):
                pass
        return sorted(all_tags)

    def get_stats(self) -> dict:
        row = self.conn.execute(
            "SELECT COUNT(*) as total, COALESCE(SUM(duration_seconds), 0) as dur FROM videos"
        ).fetchone()
        return {
            "total_videos": row["total"],
            "total_duration_seconds": row["dur"],
            "languages": self.list_languages(),
            "collections": self.list_collections(),
        }


    # ── Correlations ──────────────────────────────────

    def save_correlations(self, video_id: str, correlations: list[dict]) -> None:
        now = _now()
        for c in correlations:
            vid_b = c["video_id_b"]
            score = c["score"]
            reasons = json.dumps(c.get("reasons", []), ensure_ascii=False)
            # Insert both directions
            self.conn.execute(
                """INSERT OR REPLACE INTO video_correlations
                   (video_id_a, video_id_b, score, reasons, created_at)
                   VALUES (?, ?, ?, ?, ?)""",
                (video_id, vid_b, score, reasons, now),
            )
            self.conn.execute(
                """INSERT OR REPLACE INTO video_correlations
                   (video_id_a, video_id_b, score, reasons, created_at)
                   VALUES (?, ?, ?, ?, ?)""",
                (vid_b, video_id, score, reasons, now),
            )
        self.conn.commit()

    def get_correlations(self, video_id: str | None = None, min_score: float = 0.3) -> list[dict]:
        if video_id:
            sql = """SELECT c.*,
                        va.title as title_a, va.collection as collection_a,
                        va.language as language_a, va.tags as tags_a,
                        vb.title as title_b, vb.collection as collection_b,
                        vb.language as language_b, vb.tags as tags_b
                     FROM video_correlations c
                     JOIN videos va ON c.video_id_a = va.video_id
                     JOIN videos vb ON c.video_id_b = vb.video_id
                     WHERE c.video_id_a = ? AND c.score >= ?
                     ORDER BY c.score DESC"""
            rows = self.conn.execute(sql, (video_id, min_score)).fetchall()
        else:
            # All unique correlations (only a < b to avoid duplicates)
            sql = """SELECT c.*,
                        va.title as title_a, va.collection as collection_a,
                        va.language as language_a, va.tags as tags_a,
                        vb.title as title_b, vb.collection as collection_b,
                        vb.language as language_b, vb.tags as tags_b
                     FROM video_correlations c
                     JOIN videos va ON c.video_id_a = va.video_id
                     JOIN videos vb ON c.video_id_b = vb.video_id
                     WHERE c.video_id_a < c.video_id_b AND c.score >= ?
                     ORDER BY c.score DESC"""
            rows = self.conn.execute(sql, (min_score,)).fetchall()

        results = []
        for r in rows:
            d = dict(r)
            if "reasons" in d and isinstance(d["reasons"], str):
                try:
                    d["reasons"] = json.loads(d["reasons"])
                except (json.JSONDecodeError, TypeError):
                    d["reasons"] = []
            for k in ("tags_a", "tags_b"):
                if k in d and isinstance(d[k], str):
                    try:
                        d[k] = json.loads(d[k])
                    except (json.JSONDecodeError, TypeError):
                        d[k] = []
            results.append(d)
        return results

    def delete_correlations(self, video_id: str) -> None:
        self.conn.execute(
            "DELETE FROM video_correlations WHERE video_id_a = ? OR video_id_b = ?",
            (video_id, video_id),
        )
        self.conn.commit()


_db = None


def get_db() -> VideoDatabase:
    global _db
    if _db is None:
        _db = VideoDatabase()
    return _db
