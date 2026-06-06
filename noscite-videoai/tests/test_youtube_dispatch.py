"""Test del dispatcher a cascata YouTube e della mappatura errori.

La rete è interamente mockata: si verifica la LOGICA di cascata, non yt-dlp o
youtube-transcript-api.
"""
import pytest

from backend.transcription import youtube as yt
from backend.transcription.youtube import YouTubeError


@pytest.fixture
def patch_net(monkeypatch):
    """Mocka le funzioni di rete del modulo youtube. Ritorna un registro di
    chiamate così i test possono asserire cosa è stato (non) invocato."""
    calls = {"download": 0, "whisper": 0, "native": 0, "metadata": 0}

    def set_metadata(duration=120, title="Titolo", channel="Canale", video_id="abcdefghijk"):
        def _fake(url):
            calls["metadata"] += 1
            return {"video_id": video_id, "title": title,
                    "channel": channel, "duration_seconds": duration}
        monkeypatch.setattr(yt, "fetch_metadata", _fake)

    def set_native(segments):
        def _fake(video_id, languages=yt.PREFERRED_LANGUAGES):
            calls["native"] += 1
            return segments
        monkeypatch.setattr(yt, "fetch_native_transcript", _fake)

    def set_download(path="/tmp/yt_audio.mp3", error=None):
        def _fake(url, out_dir):
            calls["download"] += 1
            if error:
                raise YouTubeError(error)
            return path
        monkeypatch.setattr(yt, "download_audio", _fake)

    def set_whisper(segments):
        def _fake(audio_path):
            calls["whisper"] += 1
            return segments
        monkeypatch.setattr(yt, "transcribe_audio", _fake)

    # default sicuro: metadati ok, niente nativo, download+whisper disponibili
    set_metadata()
    set_native(None)
    set_download()
    set_whisper([{"text": "da whisper", "start": 0.0, "end": 3.0,
                  "language_detected": "it"}])

    return {"calls": calls, "set_metadata": set_metadata, "set_native": set_native,
            "set_download": set_download, "set_whisper": set_whisper}


def test_uses_native_transcript_when_available(patch_net):
    # due caption ravvicinate: vanno raggruppate in un unico paragrafo
    patch_net["set_native"]([
        {"text": "prima caption", "start": 0.0, "end": 4.0,
         "language_detected": "it"},
        {"text": "seconda caption", "start": 5.0, "end": 9.0},
    ])
    result = yt.transcribe_youtube("https://youtu.be/abcdefghijk", work_dir="/tmp")

    assert result["method"] == "native_transcript"
    assert "prima caption" in result["transcript"]
    assert result["language"] == "it"
    assert result["metadata"]["title"] == "Titolo"
    # native -> segments = paragrafi raggruppati: le 2 caption diventano 1
    assert len(result["segments"]) == 1
    seg = result["segments"][0]
    assert set(seg.keys()) == {"start_seconds", "end_seconds", "text"}
    assert seg["text"] == "prima caption seconda caption"
    # il fallback NON deve essere toccato
    assert patch_net["calls"]["download"] == 0
    assert patch_net["calls"]["whisper"] == 0


def test_falls_back_to_whisper_when_no_native(patch_net):
    patch_net["set_native"](None)
    # due segment Whisper distanti: devono restare distinti (granularità nativa)
    patch_net["set_whisper"]([
        {"text": "parte uno", "start": 0.0, "end": 3.0, "language_detected": "it"},
        {"text": "parte due", "start": 120.0, "end": 124.0},
    ])
    result = yt.transcribe_youtube("https://youtu.be/abcdefghijk", work_dir="/tmp")

    assert result["method"] == "whisper"
    assert "parte uno" in result["transcript"]
    # whisper -> segments = segment nativi (NON raggruppati): restano 2
    assert len(result["segments"]) == 2
    assert result["segments"][0] == {"start_seconds": 0.0, "end_seconds": 3.0, "text": "parte uno"}
    assert patch_net["calls"]["download"] == 1
    assert patch_net["calls"]["whisper"] == 1


def test_duration_over_limit_raises(patch_net):
    patch_net["set_metadata"](duration=99999)
    with pytest.raises(YouTubeError) as exc:
        yt.transcribe_youtube("https://youtu.be/abcdefghijk",
                              max_duration_seconds=3600, work_dir="/tmp")
    assert "limite" in str(exc.value).lower()
    # non deve nemmeno provare native/whisper
    assert patch_net["calls"]["native"] == 0
    assert patch_net["calls"]["download"] == 0


def test_private_video_propagates_as_youtube_error(monkeypatch):
    def _fake(url):
        raise YouTubeError("Video privato")
    monkeypatch.setattr(yt, "fetch_metadata", _fake)
    with pytest.raises(YouTubeError) as exc:
        yt.transcribe_youtube("https://youtu.be/abcdefghijk", work_dir="/tmp")
    assert exc.value.reason == "Video privato"


def test_download_error_propagates(patch_net):
    patch_net["set_native"](None)
    patch_net["set_download"](error="Video rimosso o non disponibile")
    with pytest.raises(YouTubeError) as exc:
        yt.transcribe_youtube("https://youtu.be/abcdefghijk", work_dir="/tmp")
    assert "rimosso" in exc.value.reason


@pytest.mark.parametrize("message,expected", [
    ("ERROR: Private video. Sign in if you've been granted access", "Video privato"),
    ("This video is not available in your country", "region-locked"),
    ("Video unavailable. This video has been removed by the uploader", "rimosso"),
    ("This video is age restricted", "età"),
    ("Sign in to confirm you're not a bot", "anti-bot"),
    ("qualcosa di imprevisto", "Errore download YouTube"),
])
def test_map_download_error(message, expected):
    assert expected in yt.map_download_error(message)


@pytest.mark.parametrize("url,expected", [
    ("https://www.youtube.com/watch?v=dQw4w9WgXcQ", "dQw4w9WgXcQ"),
    ("https://youtu.be/dQw4w9WgXcQ", "dQw4w9WgXcQ"),
    ("https://www.youtube.com/shorts/dQw4w9WgXcQ", "dQw4w9WgXcQ"),
    ("dQw4w9WgXcQ", "dQw4w9WgXcQ"),
    ("https://example.com/nope", None),
])
def test_extract_video_id(url, expected):
    assert yt.extract_video_id(url) == expected
