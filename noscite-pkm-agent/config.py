"""
config.py — Configurazione centralizzata PKM Agent (SharePoint edition).
"""

import os
from pathlib import Path
from dataclasses import dataclass

from dotenv import load_dotenv
load_dotenv()


@dataclass
class Config:
    # SharePoint folder names (logical paths dentro il drive del site)
    SP_INBOX:    str = "_inbox"
    SP_METADATA: str = "_metadata"
    SP_ARCHIVE:  str = "_archive"

    # Tempdir volatile per download/processing
    TEMP_DIR: Path = Path("/tmp/pkm-agent")

    # Stato locale persistente (SQLite)
    STATE_DB: Path = Path(__file__).parent / "state.db"

    # Log
    LOG_FILE: Path = Path(__file__).parent / "agent.log"

    # Modelli Claude
    CLAUDE_MODEL:      str = "claude-sonnet-4-6"
    CLAUDE_MODEL_FAST: str = "claude-haiku-4-5-20251001"

    # Parametri di processing
    MAX_TYPE_CHARS:     int = 8_000
    MAX_TEXT_CHARS:     int = 200_000
    CHUNK_SIZE:         int = 4_000
    MAX_LINKS:          int = 10
    MIN_SCORE:          int = 8
    OVERLOAD_RETRIES:   int = 3
    OVERLOAD_BASE_WAIT: int = 15

    # Polling
    POLL_INTERVAL_SECONDS: int = int(os.environ.get("PKM_POLL_INTERVAL", "30"))
    POLL_MODE:             str = os.environ.get("PKM_POLL_MODE", "loop")  # "loop" | "once"

    # Laravel sync
    LARAVEL_APP_PATH: str = os.environ.get("LARAVEL_APP_PATH", "/var/www/noscite-site")

    @classmethod
    def ensure_dirs(cls):
        cls.TEMP_DIR.mkdir(parents=True, exist_ok=True)
