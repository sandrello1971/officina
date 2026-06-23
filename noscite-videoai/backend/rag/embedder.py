import os
os.environ["PYTORCH_MPS_HIGH_WATERMARK_RATIO"] = "0.0"

from pathlib import Path

import chromadb
from sentence_transformers import SentenceTransformer
import torch
from tqdm import tqdm

from backend.config import settings

# Modello di embedding condiviso (ChromaDB video + endpoint /api/embeddings per
# il RAG Schola di atheneum). Multilingue, adatto all'italiano. 768 dimensioni.
EMBEDDING_MODEL_NAME = "paraphrase-multilingual-mpnet-base-v2"
EMBEDDING_DIMENSIONS = 768
# Limite di testi per singola richiesta all'endpoint (protezione memoria/CPU).
MAX_EMBED_TEXTS = 256


def load_model():
    try:
        model = SentenceTransformer(
            EMBEDDING_MODEL_NAME,
            device="cpu",
        )
        print("[EMBEDDER] Modello caricato su CPU")
        return model
    except Exception as e:
        print(f"[EMBEDDER] Errore caricamento modello: {e}")
        raise


_model = None


def get_model():
    global _model
    if _model is None:
        _model = load_model()
    return _model


def embed_texts(texts: list[str], batch_size: int = 32) -> list[list[float]]:
    """Calcola gli embedding di una lista di testi. Vettori normalizzati (L2=1):
    la similarità coseno con pgvector (operatore <=>) è così diretta e stabile.
    Ritorna una lista di liste di float (serializzabile in JSON)."""
    model = get_model()
    embeddings = model.encode(
        texts,
        batch_size=batch_size,
        normalize_embeddings=True,
        show_progress_bar=False,
    )
    return embeddings.tolist()


class VideoIndex:
    def __init__(self, video_id: str):
        self.video_id = video_id
        self.collection_name = f"video_{video_id}"

        db_path = settings.DATA_DIR / "chroma_db"
        db_path.mkdir(parents=True, exist_ok=True)

        self.client = chromadb.PersistentClient(path=str(db_path))
        self.collection = self.client.get_or_create_collection(
            name=self.collection_name,
            metadata={"hnsw:space": "cosine"},
        )

    def index_chunks(self, chunks: list[dict]) -> int:
        """Indicizza chunk in ChromaDB."""
        if not chunks:
            return 0

        model = get_model()
        print(f"[EMBEDDER] Calcolo embeddings per {len(chunks)} chunk...")

        # Calcola embeddings in batch
        texts = [c["text"] for c in chunks]
        embeddings = model.encode(texts, show_progress_bar=True).tolist()

        # Prepara dati per ChromaDB
        ids = []
        documents = []
        metadatas = []
        embedding_list = []

        for i, chunk in enumerate(tqdm(chunks, desc="[EMBEDDER] Indicizzazione")):
            ids.append(chunk["id"])
            documents.append(chunk["text"])
            embedding_list.append(embeddings[i])
            metadatas.append({
                "type": chunk.get("type", "unknown"),
                "start": chunk.get("start", 0),
                "end": chunk.get("end", 0),
                "timestamp_str": chunk.get("timestamp_str", "00:00"),
                "content_type": chunk.get("content_type", ""),
            })

        # Aggiungi a ChromaDB in batch (max 5000 per batch)
        batch_size = 5000
        for start in range(0, len(ids), batch_size):
            end = start + batch_size
            self.collection.upsert(
                ids=ids[start:end],
                documents=documents[start:end],
                embeddings=embedding_list[start:end],
                metadatas=metadatas[start:end],
            )

        count = self.collection.count()
        print(f"[EMBEDDER] Indicizzati {count} chunk in collection '{self.collection_name}'")
        return count

    def retrieve(self, query: str, n_results: int = 6) -> list[dict]:
        """Cerca i chunk più rilevanti per la query."""
        if self.collection.count() == 0:
            return []

        model = get_model()
        query_embedding = model.encode([query]).tolist()

        results = self.collection.query(
            query_embeddings=query_embedding,
            n_results=min(n_results, self.collection.count()),
            include=["documents", "metadatas", "distances"],
        )

        chunks = []
        for i in range(len(results["ids"][0])):
            chunks.append({
                "text": results["documents"][0][i],
                "metadata": results["metadatas"][0][i],
                "distance": results["distances"][0][i],
            })

        # Ordina per timestamp (cronologico)
        chunks.sort(key=lambda c: c["metadata"].get("start", 0))
        return chunks

    def retrieve_by_type(self, query: str, chunk_type: str, n_results: int = 6) -> list[dict]:
        """Cerca chunk rilevanti filtrati per tipo (transcript/frame)."""
        if self.collection.count() == 0:
            return []

        model = get_model()
        query_embedding = model.encode([query]).tolist()

        results = self.collection.query(
            query_embeddings=query_embedding,
            n_results=min(n_results, self.collection.count()),
            where={"type": chunk_type},
            include=["documents", "metadatas", "distances"],
        )

        chunks = []
        for i in range(len(results["ids"][0])):
            chunks.append({
                "text": results["documents"][0][i],
                "metadata": results["metadatas"][0][i],
                "distance": results["distances"][0][i],
            })

        chunks.sort(key=lambda c: c["metadata"].get("start", 0))
        return chunks

    def has_transcript_chunks(self) -> bool:
        """True se ci sono chunk di tipo transcript nella collection."""
        try:
            if self.collection.count() == 0:
                return False
            results = self.collection.get(
                where={"type": "transcript"},
                limit=1,
                include=[],
            )
            return len(results["ids"]) > 0
        except Exception:
            return False

    def exists(self) -> bool:
        """True se la collection esiste e ha documenti."""
        try:
            collections = [c.name for c in self.client.list_collections()]
            return self.collection_name in collections and self.collection.count() > 0
        except Exception:
            return False
