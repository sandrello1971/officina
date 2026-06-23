"""Test dell'endpoint /api/embeddings.

Il calcolo vero (embed_texts) è mockato: qui si verifica la LOGICA
dell'endpoint (validazione, shape della risposta, errori espliciti), non il
modello sentence-transformers.
"""
import pytest
from fastapi.testclient import TestClient

import backend.api.main as main
from backend.rag.embedder import EMBEDDING_MODEL_NAME, EMBEDDING_DIMENSIONS, MAX_EMBED_TEXTS


@pytest.fixture
def client(monkeypatch):
    # Mock deterministico: un vettore fittizio per ogni testo, dimensione reale.
    def fake_embed(texts, batch_size=32):
        return [[0.1] * EMBEDDING_DIMENSIONS for _ in texts]

    monkeypatch.setattr(main, "embed_texts", fake_embed)
    return TestClient(main.app)


def test_returns_embeddings_with_model_and_dimensions(client):
    resp = client.post("/api/embeddings", json={"texts": ["ciao", "mondo", "prova"]})
    assert resp.status_code == 200, resp.text
    data = resp.json()
    assert len(data["embeddings"]) == 3
    assert all(len(v) == EMBEDDING_DIMENSIONS for v in data["embeddings"])
    assert data["model"] == EMBEDDING_MODEL_NAME
    assert data["dimensions"] == EMBEDDING_DIMENSIONS


def test_empty_list_rejected(client):
    resp = client.post("/api/embeddings", json={"texts": []})
    assert resp.status_code == 400
    assert "vuoto" in resp.json()["detail"].lower()


def test_all_blank_texts_rejected(client):
    resp = client.post("/api/embeddings", json={"texts": ["", "   "]})
    assert resp.status_code == 400


def test_too_many_texts_rejected(client):
    resp = client.post("/api/embeddings", json={"texts": ["x"] * (MAX_EMBED_TEXTS + 1)})
    assert resp.status_code == 400
    assert "Troppi testi" in resp.json()["detail"]


def test_missing_field_is_422(client):
    # 'texts' mancante → validazione Pydantic (422), non 400
    resp = client.post("/api/embeddings", json={})
    assert resp.status_code == 422


def test_non_string_elements_is_422(client):
    resp = client.post("/api/embeddings", json={"texts": [1, 2, 3]})
    assert resp.status_code == 422
