"""Auth dell'endpoint interno POST /api/videos/{id}/index_chunks.

Verifica il gate X-Internal-Token: senza token → 401, token errato → 401, token
valido → 200. Il lavoro pesante (VideoIndex + embedding + db) è mockato: il test
controlla SOLO l'autenticazione, non crea collection reali.
"""
import pytest
from fastapi.testclient import TestClient

import backend.api.main as main

TOKEN = "secret-test-token"


class _FakeIndex:
    def __init__(self, video_id):
        self.video_id = video_id

    def reset(self):
        pass

    def index_chunks(self, chunks):
        return len(chunks)


class _FakeDB:
    def upsert(self, video_id, data):
        pass


@pytest.fixture
def client(monkeypatch):
    monkeypatch.setenv("INTERNAL_API_TOKEN", TOKEN)
    monkeypatch.setattr(main, "VideoIndex", _FakeIndex)
    monkeypatch.setattr(main, "get_db", lambda: _FakeDB())
    return TestClient(main.app)


BODY = {"chunks": [{"text": "ciao", "start": 0, "end": 1, "type": "transcript"}]}


def test_senza_token_401(client):
    resp = client.post("/api/videos/test_auth/index_chunks", json=BODY)
    assert resp.status_code == 401, resp.text


def test_token_errato_401(client):
    resp = client.post("/api/videos/test_auth/index_chunks", json=BODY,
                        headers={"X-Internal-Token": "wrong"})
    assert resp.status_code == 401, resp.text


def test_token_valido_200(client):
    resp = client.post("/api/videos/test_auth/index_chunks", json=BODY,
                        headers={"X-Internal-Token": TOKEN})
    assert resp.status_code == 200, resp.text
    assert resp.json()["indexed_chunks"] == 1


def test_token_non_configurato_fail_closed(client, monkeypatch):
    # Se il server non ha il token configurato → fail-closed (401), mai aperto.
    monkeypatch.setenv("INTERNAL_API_TOKEN", "")
    resp = client.post("/api/videos/test_auth/index_chunks", json=BODY,
                        headers={"X-Internal-Token": TOKEN})
    assert resp.status_code == 401, resp.text
