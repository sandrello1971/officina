from pathlib import Path
from dotenv import load_dotenv
import os


load_dotenv()


class Settings:
    def __init__(self):
        self.ANTHROPIC_API_KEY: str = os.getenv("ANTHROPIC_API_KEY", "")
        self.GROQ_API_KEY: str = os.getenv("GROQ_API_KEY", "")
        self.FRAMES_PER_SECOND: float = float(os.getenv("FRAMES_PER_SECOND", "0.5"))
        self.CHUNK_WINDOW_SECONDS: int = int(os.getenv("CHUNK_WINDOW_SECONDS", "30"))
        self.CHUNK_OVERLAP_SECONDS: int = int(os.getenv("CHUNK_OVERLAP_SECONDS", "8"))
        self.MAX_FRAMES_TO_ANALYZE: int = int(os.getenv("MAX_FRAMES_TO_ANALYZE", "50"))
        self.DATA_DIR: Path = Path(os.getenv("DATA_DIR", "./data"))
        # Limite durata sorgente per trascrizione audio/YouTube (default 3h)
        self.MAX_TRANSCRIBE_DURATION_SECONDS: int = int(
            os.getenv("MAX_TRANSCRIBE_DURATION_SECONDS", "10800")
        )

        # Crea directory dati automaticamente
        self.DATA_DIR.mkdir(parents=True, exist_ok=True)
        (self.DATA_DIR / "videos").mkdir(exist_ok=True)
        (self.DATA_DIR / "frames").mkdir(exist_ok=True)
        (self.DATA_DIR / "chroma_db").mkdir(exist_ok=True)

    def validate(self):
        """Controlla che le API key siano presenti."""
        errors = []
        if not self.ANTHROPIC_API_KEY or self.ANTHROPIC_API_KEY == "sk-ant-...":
            errors.append(
                "ANTHROPIC_API_KEY mancante. Imposta la variabile in .env"
            )
        if not self.GROQ_API_KEY or self.GROQ_API_KEY == "gsk_...":
            errors.append(
                "GROQ_API_KEY mancante. Imposta la variabile in .env"
            )
        if errors:
            raise ValueError(
                "Configurazione non valida:\n" + "\n".join(f"  - {e}" for e in errors)
            )
        print("[CONFIG] Configurazione validata con successo")


settings = Settings()
