import base64
import json
from pathlib import Path

import anthropic
from tqdm import tqdm

from backend.config import settings


VISION_PROMPT = """Analizza questo frame video al minuto {timestamp_str}.
Rispondi in JSON con questi campi:
- has_content (bool): il frame ha contenuto visivo significativo?
- content_type (str): 'slide', 'whiteboard', 'diagram', 'code', 'person', 'screen', 'other', 'empty'
- text_content (str): tutto il testo visibile nel frame, esatto
- visual_description (str): descrizione dettagliata di disegni, schemi, grafici
- key_concepts (list[str]): concetti chiave visibili
- summary (str): riassunto informativo del frame in max 100 parole

Rispondi SOLO con il JSON valido, senza markdown o commenti."""


def _encode_image(frame_path: str) -> str:
    """Legge un'immagine e la converte in base64."""
    with open(frame_path, "rb") as f:
        return base64.standard_b64encode(f.read()).decode("utf-8")


def _get_media_type(frame_path: str) -> str:
    """Ritorna il media type dell'immagine."""
    suffix = Path(frame_path).suffix.lower()
    types = {".jpg": "image/jpeg", ".jpeg": "image/jpeg", ".png": "image/png", ".webp": "image/webp"}
    return types.get(suffix, "image/jpeg")


def analyze_frame(frame_path: str, timestamp_str: str) -> dict:
    """Analizza un singolo frame con Claude Vision."""
    client = anthropic.Anthropic(api_key=settings.ANTHROPIC_API_KEY)

    image_data = _encode_image(frame_path)
    media_type = _get_media_type(frame_path)

    try:
        response = client.messages.create(
            model="claude-sonnet-4-5",
            max_tokens=1024,
            messages=[
                {
                    "role": "user",
                    "content": [
                        {
                            "type": "image",
                            "source": {
                                "type": "base64",
                                "media_type": media_type,
                                "data": image_data,
                            },
                        },
                        {
                            "type": "text",
                            "text": VISION_PROMPT.format(timestamp_str=timestamp_str),
                        },
                    ],
                }
            ],
        )

        raw_text = response.content[0].text.strip()
        # Rimuovi eventuale markdown wrapping
        if raw_text.startswith("```"):
            raw_text = raw_text.split("\n", 1)[1] if "\n" in raw_text else raw_text[3:]
            if raw_text.endswith("```"):
                raw_text = raw_text[:-3].strip()

        analysis = json.loads(raw_text)

    except json.JSONDecodeError:
        analysis = {
            "has_content": True,
            "content_type": "other",
            "text_content": "",
            "visual_description": raw_text if 'raw_text' in dir() else "Errore parsing JSON",
            "key_concepts": [],
            "summary": "Analisi non strutturata - parsing JSON fallito",
        }
    except Exception as e:
        analysis = {
            "has_content": False,
            "content_type": "empty",
            "text_content": "",
            "visual_description": "",
            "key_concepts": [],
            "summary": f"Errore analisi: {str(e)}",
        }

    analysis["timestamp_str"] = timestamp_str
    analysis["frame_path"] = frame_path
    return analysis


def analyze_frames_batch(frames: list[dict], max_frames: int = 50) -> list[dict]:
    """Analizza un batch di frame, campionando uniformemente se necessario."""
    if not frames:
        return []

    # Campiona uniformemente se troppi frame
    if len(frames) > max_frames:
        step = len(frames) / max_frames
        selected = [frames[int(i * step)] for i in range(max_frames)]
        print(f"[VISION] Campionati {len(selected)} frame su {len(frames)} totali")
    else:
        selected = frames

    print(f"[VISION] Analisi di {len(selected)} frame con Claude Vision...")
    results = []

    for frame in tqdm(selected, desc="[VISION] Analisi frame"):
        analysis = analyze_frame(frame["path"], frame["timestamp_str"])

        if analysis.get("has_content", False):
            # Aggiungi metadati temporali dal frame originale
            analysis["timestamp_seconds"] = frame["timestamp_seconds"]
            results.append(analysis)

    print(f"[VISION] Frame con contenuto significativo: {len(results)}/{len(selected)}")
    return results
