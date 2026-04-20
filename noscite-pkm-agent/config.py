"""
config.py — Configurazione centralizzata PKM Agent
"""

import os
from dotenv import load_dotenv
load_dotenv()
from pathlib import Path
from dataclasses import dataclass

VAULT_ROOT = Path(os.environ.get("OBSIDIAN_VAULT", "~/ObsidianVault")).expanduser()


@dataclass
class Config:
    VAULT_ROOT:   Path = VAULT_ROOT
    INBOX_DIR:    Path = VAULT_ROOT / "_inbox"
    METADATA_DIR: Path = VAULT_ROOT / "_metadata"
    ARCHIVE_DIR:  Path = VAULT_ROOT / "_archive"
    REMOVE_DIR:   Path = VAULT_ROOT / "_remove"

    LOG_FILE:   Path = Path(__file__).parent / "agent.log"
    INDEX_FILE: Path = VAULT_ROOT / "_metadata" / "_INDEX.md"

    CLAUDE_MODEL:      str = "claude-sonnet-4-6"
    CLAUDE_MODEL_FAST: str = "claude-haiku-4-5-20251001"

    MAX_TYPE_CHARS:     int = 8_000
    MAX_TEXT_CHARS:     int = 200_000
    CHUNK_SIZE:         int = 4_000
    MAX_LINKS:          int = 10
    MIN_SCORE:          int = 8
    OVERLOAD_RETRIES:   int = 3
    OVERLOAD_BASE_WAIT: int = 15

    @classmethod
    def ensure_dirs(cls):
        for d in (cls.INBOX_DIR, cls.METADATA_DIR, cls.ARCHIVE_DIR, cls.REMOVE_DIR):
            d.mkdir(parents=True, exist_ok=True)
