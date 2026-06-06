# Video Chatbot

Sistema RAG che ingesta video, estrae trascrizioni e analizza frame con Claude Vision, poi risponde alle domande indicando i minuti esatti del video.

## Prerequisites

- **Python 3.11+**
- **ffmpeg** (per estrazione audio e frame)

```bash
brew install ffmpeg
```

## Setup

```bash
# Clona il progetto
cd video-chatbot

# Crea virtual environment
python3 -m venv venv
source venv/bin/activate

# Installa dipendenze
pip install -r requirements.txt

# Configura le API key
cp .env.example .env
# Modifica .env con le tue chiavi:
#   ANTHROPIC_API_KEY=sk-ant-...
#   GROQ_API_KEY=gsk_...
```

## Come eseguire

### Test con un video

```bash
python test_ingest.py path/to/video.mp4
```

Questo esegue l'intera pipeline:
1. Estrae audio WAV mono 16kHz
2. Trascrive con Groq Whisper (rilevamento lingua automatico)
3. Estrae keyframe a intervalli regolari
4. Analizza frame con Claude Vision
5. Crea chunk con finestre scorrevoli
6. Indicizza in ChromaDB
7. Esegue una query di test

### Avviare l'API

```bash
uvicorn backend.api.main:app --reload
```

API disponibile su `http://localhost:8000`. Documentazione interattiva su `/docs`.

### Endpoint API

| Metodo | Endpoint | Descrizione |
|--------|----------|-------------|
| POST | `/api/videos/ingest` | Upload e ingestione video (multipart/form-data) |
| GET | `/api/videos/{video_id}/status` | Stato elaborazione |
| POST | `/api/videos/{video_id}/chat` | Chat con il video |
| GET | `/api/videos/` | Lista video indicizzati |

### Esempio chat

```bash
curl -X POST http://localhost:8000/api/videos/{video_id}/chat \
  -H "Content-Type: application/json" \
  -d '{"question": "Di cosa parla questo video?", "history": []}'
```

Risposta con timestamp:

```json
{
  "answer": "Il video tratta di... [AUDIO 02:15] viene spiegato che... [FRAME 05:30] nella slide si vede...\n\n📍 Punti chiave nel video:\n- [02:15] Introduzione dell'argomento\n- [05:30] Slide con schema architetturale\n- [10:00] Conclusioni",
  "timestamps": ["02:15", "05:30", "10:00"],
  "sources": [...]
}
```

## Configurazione

Variabili in `.env`:

| Variabile | Default | Descrizione |
|-----------|---------|-------------|
| `ANTHROPIC_API_KEY` | - | Chiave API Anthropic (obbligatoria) |
| `GROQ_API_KEY` | - | Chiave API Groq (obbligatoria) |
| `FRAMES_PER_SECOND` | 0.5 | Frame estratti al secondo |
| `CHUNK_WINDOW_SECONDS` | 30 | Finestra chunk trascrizione |
| `CHUNK_OVERLAP_SECONDS` | 8 | Overlap tra chunk |
| `MAX_FRAMES_TO_ANALYZE` | 50 | Max frame analizzati con Vision |
| `DATA_DIR` | ./data | Directory dati |

## Note su Groq Free Tier

- Limite di 25 MB per file audio (il sistema spezza automaticamente file più grandi)
- Rate limit: ~20 richieste/minuto sul free tier
- Il sistema implementa retry con backoff esponenziale per gestire i rate limit
- Per video lunghi (>1h), la trascrizione potrebbe richiedere più tempo a causa dei limiti

## Struttura progetto

```
video-chatbot/
├── backend/
│   ├── ingest/          # Estrazione audio, frame, trascrizione, analisi visiva
│   ├── rag/             # Chunking e indicizzazione ChromaDB
│   ├── chat/            # Engine di chat RAG con Claude
│   ├── api/             # FastAPI endpoints
│   └── config.py        # Configurazione da .env
├── data/
│   ├── videos/          # Video e metadati per video_id
│   ├── frames/          # Frame estratti
│   └── chroma_db/       # Database vettoriale
├── test_ingest.py       # Script test pipeline
├── requirements.txt
└── .env.example
```
