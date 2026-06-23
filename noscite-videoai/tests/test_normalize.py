"""Test normalizzazione trascrizioni (funzioni pure, nessuna rete)."""
from backend.transcription.normalize import (
    segments_to_paragraphs,
    paragraphs_to_text,
    normalize_transcript,
    to_public_segments,
)


def _segs():
    return [
        {"text": "Ciao a tutti", "start": 0.0, "end": 4.0},
        {"text": "oggi parliamo di AI", "start": 10.0, "end": 14.0},
        {"text": "in particolare di trascrizione", "start": 20.0, "end": 24.0},
        {"text": "Nuovo paragrafo qui", "start": 35.0, "end": 39.0},
    ]


def test_paragraphs_grouped_by_window():
    paras = segments_to_paragraphs(_segs(), window_seconds=30.0)
    # I primi tre segmenti (0,10,20) stanno nella finestra di 30s dal primo;
    # il quarto (35) apre un nuovo paragrafo.
    assert len(paras) == 2
    assert paras[0]["start"] == 0.0
    assert paras[0]["end"] == 24.0
    assert paras[0]["text"] == "Ciao a tutti oggi parliamo di AI in particolare di trascrizione"
    assert paras[1]["start"] == 35.0
    assert paras[1]["text"] == "Nuovo paragrafo qui"


def test_empty_and_whitespace_segments_ignored():
    segs = [
        {"text": "  ", "start": 0.0, "end": 1.0},
        {"text": "", "start": 1.0, "end": 2.0},
        {"text": "contenuto", "start": 2.0, "end": 3.0},
    ]
    paras = segments_to_paragraphs(segs)
    assert len(paras) == 1
    assert paras[0]["text"] == "contenuto"


def test_no_segments_returns_empty():
    assert segments_to_paragraphs([]) == []
    assert normalize_transcript([])["text"] == ""
    assert normalize_transcript([])["paragraphs"] == []


def test_text_has_paragraph_timestamps():
    text = paragraphs_to_text(segments_to_paragraphs(_segs(), window_seconds=30.0))
    assert text.startswith("[00:00] Ciao a tutti")
    assert "[00:35] Nuovo paragrafo qui" in text
    # paragrafi separati da riga vuota
    assert "\n\n" in text


def test_long_timestamp_uses_hours():
    segs = [{"text": "tardi", "start": 3661.0, "end": 3665.0}]
    text = normalize_transcript(segs)["text"]
    assert text == "[01:01:01] tardi"


def test_to_public_segments_shape():
    out = to_public_segments(_segs())
    assert len(out) == 4
    assert out[0] == {"start_seconds": 0.0, "end_seconds": 4.0, "text": "Ciao a tutti"}
    # solo le chiavi pubbliche, nessuna chiave interna
    assert set(out[0].keys()) == {"start_seconds", "end_seconds", "text"}


def test_to_public_segments_skips_empty():
    out = to_public_segments([
        {"text": "  ", "start": 0.0, "end": 1.0},
        {"text": "ok", "start": 1.0, "end": 2.0},
    ])
    assert out == [{"start_seconds": 1.0, "end_seconds": 2.0, "text": "ok"}]
