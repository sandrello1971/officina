"""Test del validatore di formato dell'endpoint /api/audio/transcribe.

Verifica che i contenitori video siano accettati (si trascrive la traccia
audio, Whisper li decodifica via ffmpeg) e che le estensioni non-media siano
rifiutate con 400. La trascrizione vera (run_audio_job) è mockata: qui si
testa SOLO la logica di validazione dell'estensione.
"""
from types import SimpleNamespace

import pytest
from fastapi.testclient import TestClient

import backend.api.main as main
from backend.transcription.audio import ALLOWED_AUDIO_EXTENSIONS


@pytest.fixture
def client(monkeypatch, tmp_path):
    """TestClient con run_audio_job no-op e JobStore.create che scrive in tmp."""
    monkeypatch.setattr(main, "run_audio_job", lambda *a, **k: None)

    def fake_create(kind, **kwargs):
        return SimpleNamespace(job_id="test-job", dir=tmp_path)

    monkeypatch.setattr(main.JobStore, "create", staticmethod(fake_create))
    return TestClient(main.app)


def test_allowed_set_contains_audio_and_video():
    expected = {
        ".mp3", ".m4a", ".wav", ".ogg",
        ".mp4", ".mov", ".mpeg", ".mpg", ".avi", ".webm",
    }
    assert ALLOWED_AUDIO_EXTENSIONS == expected


@pytest.mark.parametrize("filename,mime", [
    ("clip.mp4", "video/mp4"),
    ("clip.mov", "video/quicktime"),
    ("clip.webm", "video/webm"),
    ("clip.avi", "video/x-msvideo"),
    ("clip.mpeg", "video/mpeg"),
    ("clip.mpg", "video/mpeg"),
    ("lezione.m4a", "audio/mp4"),
])
def test_video_and_audio_extensions_accepted(client, filename, mime):
    resp = client.post(
        "/api/audio/transcribe",
        files={"file": (filename, b"fake-bytes", mime)},
    )
    assert resp.status_code == 200, resp.text
    assert resp.json()["job_id"] == "test-job"


@pytest.mark.parametrize("filename,mime", [
    ("malware.txt", "text/plain"),
    ("foglio.pdf", "application/pdf"),
    ("immagine.png", "image/png"),
    ("senza_estensione", "application/octet-stream"),
])
def test_non_media_extensions_rejected(client, filename, mime):
    resp = client.post(
        "/api/audio/transcribe",
        files={"file": (filename, b"fake-bytes", mime)},
    )
    assert resp.status_code == 400
    assert "Formato non supportato" in resp.json()["detail"]
