import json
import os

os.environ.setdefault("PYTORCH_MPS_HIGH_WATERMARK_RATIO", "0.0")

from sentence_transformers import SentenceTransformer, util

from backend.storage.database import get_db

_model = None


def _get_model():
    global _model
    if _model is None:
        _model = SentenceTransformer(
            "paraphrase-multilingual-mpnet-base-v2",
            device="cpu",
        )
        print("[CORRELATOR] Modello embedding caricato su CPU")
    return _model


def _parse_tags(tags) -> list[str]:
    if isinstance(tags, list):
        return tags
    if isinstance(tags, str):
        try:
            parsed = json.loads(tags)
            return parsed if isinstance(parsed, list) else []
        except (json.JSONDecodeError, TypeError):
            return []
    return []


def compute_correlations(new_video_id: str, mode: str = "semantic_rules") -> list[dict]:
    """Calcola correlazioni tra new_video_id e tutti gli altri video.

    mode: "semantic_rules" (default), "semantic", "rules"
    """
    db = get_db()
    new_video = db.get(new_video_id)
    if not new_video or not new_video.get("summary"):
        print(f"[CORRELATOR] {new_video_id[:8]}... skip (no summary)")
        return []

    all_videos = db.search()
    others = [v for v in all_videos if v["video_id"] != new_video_id]
    if not others:
        return []

    use_semantic = mode in ("semantic_rules", "semantic")
    use_rules = mode in ("semantic_rules", "rules")

    # Semantic similarity via embeddings
    cos_scores = None
    if use_semantic:
        model = _get_model()
        new_summary = new_video.get("summary", "") or new_video.get("title", "")
        new_embedding = model.encode(new_summary, convert_to_tensor=True)
        other_summaries = [
            (v.get("summary", "") or v.get("title", "")) for v in others
        ]
        other_embeddings = model.encode(other_summaries, convert_to_tensor=True)
        cos_scores = util.cos_sim(new_embedding, other_embeddings)[0]

    new_tags = set(_parse_tags(new_video.get("tags", "[]")))
    new_collection = new_video.get("collection", "")
    new_language = new_video.get("language", "")

    correlations = []
    for i, other in enumerate(others):
        reasons = []

        # Semantic score
        score_semantico = 0.0
        if use_semantic and cos_scores is not None:
            score_semantico = max(0, float(cos_scores[i]))
            reasons.append(f"semantica:{round(score_semantico, 2)}")

        # Rule-based scores
        score_regole = 0.0
        if use_rules:
            other_tags = set(_parse_tags(other.get("tags", "[]")))
            comuni = new_tags & other_tags
            score_tag = min(len(comuni) / max(len(new_tags), 1), 1.0) * 0.4
            reasons.extend(f"tag:{t}" for t in sorted(comuni))

            score_collection = 0.0
            other_collection = other.get("collection", "")
            if new_collection and new_collection == other_collection and new_collection != "Generale":
                score_collection = 0.25
                reasons.append(f"collezione:{new_collection}")

            score_lingua = 0.0
            other_language = other.get("language", "")
            if new_language and new_language == other_language:
                score_lingua = 0.1
                reasons.append(f"lingua:{new_language}")

            score_regole = score_tag + score_collection + score_lingua

        # Combined score
        if mode == "semantic_rules":
            score_finale = round((score_semantico * 0.5) + (score_regole * 0.5), 3)
        elif mode == "semantic":
            score_finale = round(score_semantico, 3)
        else:  # rules
            score_finale = round(score_regole, 3)

        if score_finale >= 0.1:
            correlations.append({
                "video_id_b": other["video_id"],
                "score": score_finale,
                "reasons": reasons,
            })

    correlations.sort(key=lambda c: c["score"], reverse=True)
    return correlations


def update_all_correlations() -> int:
    """Ricalcola tutte le correlazioni."""
    db = get_db()
    all_videos = db.search()
    total = 0

    for v in all_videos:
        vid = v["video_id"]
        db.delete_correlations(vid)
        corrs = compute_correlations(vid)
        if corrs:
            db.save_correlations(vid, corrs)
            total += len(corrs)
            print(f"[CORRELATOR] {v.get('filename', vid[:8])} → {len(corrs)} correlazioni")

    return total
