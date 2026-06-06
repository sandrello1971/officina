import hashlib
import json
import shutil
from pathlib import Path

import aiofiles
import chromadb
import mimetypes

from fastapi import FastAPI, Request, UploadFile, File, BackgroundTasks, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import FileResponse, StreamingResponse
from pydantic import BaseModel

from backend.config import settings
from backend.ingest.extractor import extract_audio, extract_keyframes, extract_thumbnail, get_video_duration
from backend.ingest.transcriber import transcribe_audio
from backend.ingest.vision_analyzer import analyze_frames_batch
from backend.rag.chunker import build_chunks
from backend.rag.embedder import VideoIndex
from backend.chat.engine import chat, global_chat, generate_video_summary, generate_tags, search_across_videos, auto_assign_collection
from backend.api.progress import ProgressTracker
from backend.storage.database import get_db
from backend.storage.correlator import compute_correlations
from backend.ingest.extractor import format_timestamp
from backend.jobs import JobStore
from backend.transcription.audio import run_audio_job, ALLOWED_AUDIO_EXTENSIONS
from backend.transcription.youtube import run_youtube_job


app = FastAPI(title="Video Chatbot API", version="1.0.0")

app.add_middleware(
    CORSMiddleware,
    allow_origins=["http://localhost:5173", "http://localhost:5174", "https://atheneum.noscite.it", "http://127.0.0.1"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)


class ChatRequest(BaseModel):
    question: str
    history: list[dict] = []


class ChatResponse(BaseModel):
    answer: str
    timestamps: list[str]
    sources: list[dict]


class MetadataUpdate(BaseModel):
    title: str | None = None
    tags: list[str] | None = None
    collection: str | None = None
    notes: str | None = None


class CrossSearchRequest(BaseModel):
    question: str
    video_ids: list[str] | None = None


class YouTubeRequest(BaseModel):
    url: str


def _compute_video_id(content: bytes) -> str:
    """Calcola video_id deterministico basato su hash MD5 del file."""
    return hashlib.md5(content).hexdigest()


def _save_metadata(video_id: str, metadata: dict):
    """Salva metadati video su disco."""
    video_dir = settings.DATA_DIR / "videos" / video_id
    video_dir.mkdir(parents=True, exist_ok=True)
    meta_path = video_dir / "metadata.json"
    with open(meta_path, "w") as f:
        json.dump(metadata, f, indent=2, ensure_ascii=False)


def _is_already_indexed(video_id: str) -> bool:
    """Controlla se il video è già indicizzato (metadata + ChromaDB)."""
    meta_path = settings.DATA_DIR / "videos" / video_id / "metadata.json"
    if not meta_path.exists():
        return False
    try:
        index = VideoIndex(video_id)
        return index.exists()
    except Exception:
        return False


def _run_pipeline(video_id: str, video_path: str, original_filename: str = ""):
    """Esegue la pipeline completa di ingestione con progress tracking."""
    tracker = ProgressTracker(video_id)
    try:
        video_dir = settings.DATA_DIR / "videos" / video_id

        # 1. Estrazione audio
        tracker.update(step="extraction", progress=10, can_chat=False)
        duration = get_video_duration(video_path)
        audio_path = extract_audio(video_path, str(video_dir))

        # 2. Trascrizione
        tracker.update(step="transcription", progress=25, can_chat=False)
        segments = transcribe_audio(audio_path)
        language = segments[0].get("language_detected", "unknown") if segments else "unknown"

        # 3. Indicizza chunk trascrizione (sblocca chat)
        transcript_chunks = build_chunks(segments, [])
        index = VideoIndex(video_id)
        index.index_chunks(transcript_chunks)
        tracker.update(step="frames", progress=45, can_chat=True)
        print(f"[API] Chat sbloccata per video {video_id} (solo trascrizione)")

        # 4. Estrai thumbnail e frame
        extract_thumbnail(video_path, str(video_dir))
        frames = extract_keyframes(video_path, str(video_dir), fps=settings.FRAMES_PER_SECOND)
        frame_analyses = analyze_frames_batch(frames, max_frames=settings.MAX_FRAMES_TO_ANALYZE)

        # 5. Indicizza chunk completi (trascrizione + frame)
        tracker.update(step="indexing", progress=85, can_chat=True)
        all_chunks = build_chunks(segments, frame_analyses)
        index.index_chunks(all_chunks)

        # 6. Salva metadati
        metadata = {
            "video_id": video_id,
            "duration": duration,
            "chunks_count": len(all_chunks),
            "transcript_segments": len(segments),
            "frames_analyzed": len(frame_analyses),
            "language_detected": language,
            "status": "ready",
        }
        _save_metadata(video_id, metadata)

        # 7. Genera summary
        summary = generate_video_summary(video_id)

        # 8. Salva in SQLite
        thumbnail_exists = (video_dir / "thumbnail.jpg").exists()
        fname = original_filename or Path(video_path).name
        db = get_db()
        db.upsert(video_id, {
            "filename": fname,
            "title": Path(fname).stem.replace("_", " ").replace("-", " "),
            "summary": summary or "",
            "duration_seconds": duration,
            "duration_str": format_timestamp(duration),
            "language": language,
            "chunks_count": len(all_chunks),
            "has_thumbnail": 1 if thumbnail_exists else 0,
            "collection": "Generale",
            "tags": "[]",
            "notes": "",
        })
        print(f"[API] Record SQLite salvato per {video_id}")

        # 9. Genera tag automatici
        tags = generate_tags(video_id)
        if tags:
            db.update_metadata(video_id, tags=tags)
            print(f"[API] Tag generati per {video_id}: {tags}")

        # 10. Calcolo correlazioni
        print(f"[CORRELATOR] Calcolo correlazioni...")
        tracker.update(step="correlations", progress=96, can_chat=True)
        correlations = compute_correlations(video_id)
        db.save_correlations(video_id, correlations)
        print(f"[CORRELATOR] {len(correlations)} correlazioni trovate")

        tracker.update(step="ready", progress=100, can_chat=True)
        print(f"[API] Pipeline completata per video {video_id}")

    except Exception as e:
        tracker.set_error(str(e))
        print(f"[API] Errore pipeline per video {video_id}: {e}")
        raise


@app.post("/api/videos/ingest")
async def ingest_video(background_tasks: BackgroundTasks, file: UploadFile = File(...)):
    """Ingesta un nuovo video."""
    content = await file.read()
    video_id = _compute_video_id(content)

    # Skip se già indicizzato
    if _is_already_indexed(video_id):
        print(f"[API] Video già indicizzato: {video_id}")
        return {
            "video_id": video_id,
            "status": "ready",
            "message": "Video già indicizzato",
            "skipped": True,
        }

    # Salva file video
    video_dir = settings.DATA_DIR / "videos" / video_id
    video_dir.mkdir(parents=True, exist_ok=True)
    video_path = video_dir / file.filename
    async with aiofiles.open(str(video_path), "wb") as f:
        await f.write(content)

    print(f"[API] Video salvato: {video_path} ({len(content) / (1024*1024):.1f} MB)")

    # Progress iniziale
    tracker = ProgressTracker(video_id)
    tracker.update(step="upload", progress=5, can_chat=False)

    # Avvia pipeline in background
    background_tasks.add_task(_run_pipeline, video_id, str(video_path), file.filename)

    return {
        "video_id": video_id,
        "status": "processing",
        "message": "Analisi avviata in background",
    }


@app.get("/api/videos/{video_id}/status")
async def get_status(video_id: str):
    """Ritorna lo stato del processing da progress.json."""
    tracker = ProgressTracker(video_id)
    progress = tracker.get()

    if progress.get("status") == "not_found":
        # Fallback: controlla metadati su disco
        meta_path = settings.DATA_DIR / "videos" / video_id / "metadata.json"
        if meta_path.exists():
            with open(meta_path) as f:
                metadata = json.load(f)
            return {
                "status": metadata.get("status", "ready"),
                "progress": 100,
                "can_chat": True,
                "has_transcript_chunks": True,
            }
        raise HTTPException(status_code=404, detail="Video non trovato")

    # Aggiungi campo has_transcript_chunks
    try:
        index = VideoIndex(video_id)
        progress["has_transcript_chunks"] = index.has_transcript_chunks()
    except Exception:
        progress["has_transcript_chunks"] = False

    return progress


@app.get("/api/videos/{video_id}/thumbnail")
async def get_thumbnail(video_id: str):
    """Serve la thumbnail del video."""
    thumb_path = settings.DATA_DIR / "videos" / video_id / "thumbnail.jpg"
    if not thumb_path.exists():
        raise HTTPException(status_code=404, detail="Thumbnail non trovata")
    return FileResponse(str(thumb_path), media_type="image/jpeg")


@app.get("/api/videos/{video_id}/stream")
async def stream_video(video_id: str, request: Request):
    """Serve il file video con supporto HTTP Range Requests per seek."""
    video_dir = settings.DATA_DIR / "videos" / video_id
    if not video_dir.exists():
        raise HTTPException(status_code=404, detail="Video non trovato")

    # Trova il file video (escludendo audio e frames)
    video_file = None
    for ext in [".mp4", ".mov", ".avi", ".webm", ".MP4", ".MOV"]:
        for f in video_dir.iterdir():
            if f.suffix.lower() == ext.lower() and "audio" not in f.name and "frames" not in f.name:
                video_file = f
                break
        if video_file:
            break

    if not video_file or not video_file.exists():
        raise HTTPException(status_code=404, detail="File video non trovato")

    file_size = video_file.stat().st_size
    media_type = mimetypes.guess_type(str(video_file))[0] or "video/mp4"

    range_header = request.headers.get("range")

    if not range_header:
        def iter_file():
            with open(video_file, "rb") as f:
                while chunk := f.read(1024 * 1024):
                    yield chunk
        return StreamingResponse(
            iter_file(),
            media_type=media_type,
            headers={"Accept-Ranges": "bytes", "Content-Length": str(file_size)},
        )

    # Parse range: "bytes=start-end"
    range_val = range_header.replace("bytes=", "")
    range_start, range_end = range_val.split("-")
    range_start = int(range_start)
    range_end = int(range_end) if range_end else file_size - 1
    range_end = min(range_end, file_size - 1)
    content_length = range_end - range_start + 1

    def iter_range():
        with open(video_file, "rb") as f:
            f.seek(range_start)
            remaining = content_length
            while remaining > 0:
                chunk_size = min(1024 * 1024, remaining)
                data = f.read(chunk_size)
                if not data:
                    break
                yield data
                remaining -= len(data)

    return StreamingResponse(
        iter_range(),
        status_code=206,
        media_type=media_type,
        headers={
            "Content-Range": f"bytes {range_start}-{range_end}/{file_size}",
            "Accept-Ranges": "bytes",
            "Content-Length": str(content_length),
        },
    )


@app.post("/api/videos/{video_id}/chat", response_model=ChatResponse)
async def chat_endpoint(video_id: str, request: ChatRequest):
    """Chat con il video indicizzato."""
    # Controlla progress
    tracker = ProgressTracker(video_id)
    progress = tracker.get()

    if progress.get("status") == "not_found":
        # Fallback: controlla metadata
        meta_path = settings.DATA_DIR / "videos" / video_id / "metadata.json"
        if not meta_path.exists():
            raise HTTPException(status_code=404, detail="Video non trovato")
        # Metadata esiste, video è pronto
        result = chat(video_id, request.question, request.history)
        return ChatResponse(**result)

    if progress.get("status") == "error":
        raise HTTPException(status_code=500, detail=f"Errore elaborazione: {progress.get('error')}")

    if not progress.get("can_chat", False):
        raise HTTPException(
            status_code=400,
            detail="Video ancora in elaborazione, attendi la trascrizione",
        )

    # Se can_chat=true ma non ancora ready: solo transcript
    transcript_only = progress.get("step") != "ready"
    result = chat(video_id, request.question, request.history, transcript_only=transcript_only)
    return ChatResponse(**result)


@app.get("/api/videos/filters")
async def get_filters():
    """Ritorna opzioni disponibili per filtri."""
    db = get_db()
    stats = db.get_stats()
    return {
        "collections": stats["collections"],
        "languages": stats["languages"],
        "tags": db.list_all_tags(),
        "stats": {
            "total_videos": stats["total_videos"],
            "total_duration_seconds": stats["total_duration_seconds"],
        },
    }


@app.get("/api/videos/")
async def list_videos(
    q: str = "",
    language: str = "",
    collection: str = "",
    tag: str = "",
    sort: str = "created_at",
):
    """Lista video da SQLite con ricerca e filtri."""
    db = get_db()
    tags_filter = [tag] if tag else None
    videos = db.search(
        query=q,
        language=language,
        collection=collection,
        tags=tags_filter,
        sort_by=sort,
        sort_dir="desc",
    )

    # Arricchisci con status da progress.json per video in elaborazione
    for v in videos:
        progress_path = settings.DATA_DIR / "videos" / v["video_id"] / "progress.json"
        v["status"] = "ready"
        v["progress"] = 100
        if progress_path.exists():
            with open(progress_path) as f:
                prog = json.load(f)
            if prog.get("status") == "processing":
                v["status"] = "processing"
                v["progress"] = prog.get("progress", 0)
            elif prog.get("status") == "error":
                v["status"] = "error"

    return videos


@app.patch("/api/videos/{video_id}/metadata")
async def update_video_metadata(video_id: str, body: MetadataUpdate):
    """Aggiorna metadati editabili di un video."""
    db = get_db()
    updated = db.update_metadata(
        video_id,
        title=body.title,
        tags=body.tags,
        collection=body.collection,
        notes=body.notes,
    )
    if not updated:
        raise HTTPException(status_code=404, detail="Video non trovato")
    return updated


@app.get("/api/videos/{video_id}/transcript")
async def get_transcript(video_id: str):
    """Ritorna la trascrizione del video."""
    meta_path = settings.DATA_DIR / "videos" / video_id / "metadata.json"
    if not meta_path.exists():
        raise HTTPException(status_code=404, detail="Video non trovato")

    # Try to get segments from the ingest transcriber output
    # We rebuild from ChromaDB transcript chunks
    try:
        index = VideoIndex(video_id)
        if not index.exists():
            raise HTTPException(status_code=404, detail="Trascrizione non disponibile")

        # Get all transcript chunks sorted by time
        all_chunks = index.collection.get(
            where={"type": "transcript"},
            include=["documents", "metadatas"],
        )

        segments = []
        for i in range(len(all_chunks["ids"])):
            meta = all_chunks["metadatas"][i]
            segments.append({
                "text": all_chunks["documents"][i],
                "start": meta.get("start", 0),
                "end": meta.get("end", 0),
                "timestamp_str": meta.get("timestamp_str", "00:00"),
            })

        segments.sort(key=lambda s: s["start"])
        return {"segments": segments}
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/api/search")
async def cross_video_search(body: CrossSearchRequest):
    """Cerca una domanda attraverso tutti i video."""
    db = get_db()

    if body.video_ids:
        video_ids = body.video_ids
    else:
        videos = db.search()
        video_ids = [v["video_id"] for v in videos]

    if not video_ids:
        return []

    results = search_across_videos(body.question, video_ids)

    # Enrich with video metadata from DB
    for r in results:
        video = db.get(r["video_id"])
        if video:
            r["filename"] = video.get("filename", "")
            r["title"] = video.get("title", "")
            r["summary"] = video.get("summary", "")
            r["has_thumbnail"] = video.get("has_thumbnail", 0)
            r["duration_str"] = video.get("duration_str", "")

    return results


@app.post("/api/videos/auto-organize")
async def auto_organize():
    """Organizza automaticamente i video in collezioni."""
    db = get_db()

    # Prendi tutti i video con collection="Generale" o vuota
    all_videos = db.search()
    to_organize = [
        v for v in all_videos
        if not v.get("collection") or v["collection"] == "Generale"
    ]

    if not to_organize:
        return {"updated": 0, "assignments": []}

    print(f"[AUTO-ORG] {len(to_organize)} video da organizzare")

    existing_collections = db.list_collections()
    assignments = []

    for v in to_organize:
        new_collection = auto_assign_collection(v["video_id"], existing_collections)
        if new_collection and new_collection != "Generale":
            db.update_metadata(v["video_id"], collection=new_collection)
            print(f"[AUTO-ORG] {v.get('filename', v['video_id'][:8])} → {new_collection}")
            assignments.append({
                "video_id": v["video_id"],
                "filename": v.get("filename", ""),
                "collection": new_collection,
            })
            if new_collection not in existing_collections:
                existing_collections.append(new_collection)

    return {"updated": len(assignments), "assignments": assignments}


@app.post("/api/videos/{video_id}/regenerate-tags")
async def regenerate_tags(video_id: str):
    """Rigenera i tag AI per un video."""
    db = get_db()
    if not db.get(video_id):
        raise HTTPException(status_code=404, detail="Video non trovato")
    tags = generate_tags(video_id)
    db.update_metadata(video_id, tags=tags)
    return {"tags": tags}


@app.post("/api/chat/global")
async def global_chat_endpoint(request: ChatRequest):
    """Chat globale su tutti i video."""
    result = global_chat(request.question, request.history)
    return result


@app.get("/api/graph")
async def get_graph(min_score: float = 0.15, video_id: str = "", mode: str = "semantic_rules"):
    """Ritorna il grafo correlazioni in formato Cytoscape."""
    from backend.storage.correlator import compute_correlations as _cc, update_all_correlations as _ua

    db = get_db()

    # If mode changed, recompute correlations on the fly
    # For now just use existing correlations filtered by min_score
    vid = video_id if video_id else None
    correlations = db.get_correlations(video_id=vid, min_score=min_score)

    if not correlations:
        return {"nodes": [], "edges": []}

    # Collect involved video IDs
    node_ids = set()
    for c in correlations:
        node_ids.add(c["video_id_a"])
        node_ids.add(c["video_id_b"])

    if len(node_ids) < 2:
        return {"nodes": [], "edges": []}

    # Count degree per node
    degree = {}
    for c in correlations:
        degree[c["video_id_a"]] = degree.get(c["video_id_a"], 0) + 1
        degree[c["video_id_b"]] = degree.get(c["video_id_b"], 0) + 1

    # Build nodes
    nodes = []
    for nid in node_ids:
        v = db.get(nid)
        if not v:
            continue
        label = v.get("title", v.get("filename", ""))
        if len(label) > 25:
            label = label[:22] + "..."
        tags = v.get("tags", [])
        if isinstance(tags, str):
            try:
                tags = json.loads(tags)
            except (json.JSONDecodeError, TypeError):
                tags = []
        nodes.append({
            "data": {
                "id": nid,
                "label": label,
                "title": v.get("title", ""),
                "summary": v.get("summary", ""),
                "collection": v.get("collection", ""),
                "language": v.get("language", ""),
                "tags": tags,
                "duration_str": v.get("duration_str", ""),
                "has_thumbnail": bool(v.get("has_thumbnail", 0)),
                "chunks_count": v.get("chunks_count", 0),
                "degree": degree.get(nid, 0),
            }
        })

    # Build edges (unique only)
    seen_edges = set()
    edges = []
    for c in correlations:
        a, b = c["video_id_a"], c["video_id_b"]
        key = tuple(sorted([a, b]))
        if key in seen_edges:
            continue
        seen_edges.add(key)

        reasons = c.get("reasons", [])
        reason_labels = [r.split(":")[1] if ":" in r else r for r in reasons[:3]]
        edges.append({
            "data": {
                "id": f"e_{a[:8]}_{b[:8]}",
                "source": a,
                "target": b,
                "score": c["score"],
                "reasons": reasons,
                "label": ", ".join(reason_labels),
            }
        })

    return {"nodes": nodes, "edges": edges}


@app.delete("/api/videos/{video_id}")
async def delete_video(video_id: str):
    """Elimina un video: file, ChromaDB e SQLite."""
    video_dir = settings.DATA_DIR / "videos" / video_id
    if not video_dir.exists():
        raise HTTPException(status_code=404, detail="Video non trovato")

    # Elimina collection ChromaDB
    try:
        db_path = settings.DATA_DIR / "chroma_db"
        client = chromadb.PersistentClient(path=str(db_path))
        collection_name = f"video_{video_id}"
        existing = [c.name for c in client.list_collections()]
        if collection_name in existing:
            client.delete_collection(collection_name)
            print(f"[API] Collection ChromaDB '{collection_name}' eliminata")
    except Exception as e:
        print(f"[API] Errore eliminazione collection: {e}")

    # Elimina da SQLite + correlazioni
    db = get_db()
    db.delete_correlations(video_id)
    db.delete(video_id)

    # Elimina cartella video
    shutil.rmtree(video_dir)
    print(f"[API] Cartella {video_dir} eliminata")

    return {"success": True, "message": "Video eliminato"}


# ---------------------------------------------------------------------------
# Trascrizione audio / YouTube (job asincroni, polling)
# Nota auth: come gli endpoint /api/videos/*, questi NON usano autenticazione
# (il servizio è esposto solo su 127.0.0.1 dietro reverse proxy).
# ---------------------------------------------------------------------------

def _job_status_payload(data: dict) -> dict:
    """Payload comune di polling per i job di trascrizione."""
    return {
        "status": data.get("status"),
        "progress": data.get("progress", 0),
        "transcript": data.get("transcript"),
        "segments": data.get("segments"),
        "language": data.get("language"),
        "duration_seconds": data.get("duration_seconds"),
        "error": data.get("error"),
    }


@app.post("/api/audio/transcribe")
async def audio_transcribe(background_tasks: BackgroundTasks, file: UploadFile = File(...)):
    """Avvia la trascrizione asincrona di un file audio o video. Ritorna {job_id}.

    Accetta file audio (mp3, m4a, wav, ogg) e contenitori video (mp4, mov,
    mpeg, mpg, avi, webm): in questi ultimi viene trascritta la traccia audio
    (Whisper li decodifica via ffmpeg). Vedi ALLOWED_AUDIO_EXTENSIONS.
    """
    ext = Path(file.filename or "").suffix.lower()
    if ext not in ALLOWED_AUDIO_EXTENSIONS:
        raise HTTPException(
            status_code=400,
            detail=f"Formato non supportato: '{ext or 'sconosciuto'}'. "
                   f"Ammessi (audio e video): {sorted(ALLOWED_AUDIO_EXTENSIONS)}",
        )

    content = await file.read()
    job = JobStore.create("audio", filename=file.filename)
    audio_path = job.dir / f"source{ext}"
    async with aiofiles.open(str(audio_path), "wb") as f:
        await f.write(content)

    background_tasks.add_task(run_audio_job, job.job_id, str(audio_path))
    return {"job_id": job.job_id}


@app.get("/api/audio/{job_id}")
async def audio_status(job_id: str):
    """Stato/risultato di un job di trascrizione audio."""
    data = JobStore(job_id).get()
    if data.get("status") == "not_found":
        raise HTTPException(status_code=404, detail="Job non trovato")
    return _job_status_payload(data)


@app.post("/api/youtube/transcribe")
async def youtube_transcribe(body: YouTubeRequest, background_tasks: BackgroundTasks):
    """Avvia la trascrizione asincrona di un video YouTube. Ritorna {job_id}."""
    if not body.url or not body.url.strip():
        raise HTTPException(status_code=400, detail="URL mancante")

    job = JobStore.create("youtube", url=body.url)
    background_tasks.add_task(run_youtube_job, job.job_id, body.url)
    return {"job_id": job.job_id}


@app.get("/api/youtube/{job_id}")
async def youtube_status(job_id: str):
    """Stato/risultato di un job di trascrizione YouTube (con method + metadati)."""
    data = JobStore(job_id).get()
    if data.get("status") == "not_found":
        raise HTTPException(status_code=404, detail="Job non trovato")
    payload = _job_status_payload(data)
    payload["method"] = data.get("method")
    payload["metadata"] = data.get("metadata")
    return payload
