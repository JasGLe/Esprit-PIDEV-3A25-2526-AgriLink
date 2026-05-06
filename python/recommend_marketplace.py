#!/usr/bin/env python3
import json
import sys
from collections import defaultdict
from typing import Dict, List, Any, Tuple

import numpy as np
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity


def _to_float(v: Any) -> float:
    try:
        return float(v)
    except Exception:
        return 0.0


def _safe_text(v: Any) -> str:
    if v is None:
        return ""
    return str(v).strip()


def _normalize_scores(raw: Dict[str, float]) -> Dict[str, float]:
    if not raw:
        return {}
    max_v = max([float(v) for v in raw.values()] + [1.0])
    return {k: (float(v) / max_v) for k, v in raw.items()}


def _build_docs(products: List[Dict[str, Any]]) -> List[str]:
    docs = []
    for p in products:
        name = _safe_text(p.get("name"))
        desc = _safe_text(p.get("description"))
        cat = _safe_text(p.get("category"))
        docs.append(f"{name} {cat} {desc}".strip())
    return docs


def _top_ids(scored: List[Tuple[float, int]], limit: int) -> List[int]:
    scored.sort(key=lambda x: (-x[0], -x[1]))
    out = []
    seen = set()
    for s, pid in scored:
        if pid <= 0 or pid in seen:
            continue
        seen.add(pid)
        out.append(pid)
        if len(out) >= limit:
            break
    return out


def rank_sections(payload: Dict[str, Any]) -> Dict[str, List[int]]:
    products = payload.get("products", []) or []
    limit = max(1, int(payload.get("limit", 6) or 6))

    if not products:
        return {"recommended": [], "similar": [], "trending": [], "offers": []}

    global_sales_raw = payload.get("global_sales", {}) or {}
    interaction_scores_raw = payload.get("user_interaction_scores", {}) or {}
    search_terms = payload.get("search_terms", []) or []
    last_viewed_product_id = int(payload.get("last_viewed_product_id", 0) or 0)

    # id mapping
    ids = [int(p.get("id", 0) or 0) for p in products]
    id_to_idx = {pid: i for i, pid in enumerate(ids) if pid > 0}

    docs = _build_docs(products)
    vectorizer = TfidfVectorizer(stop_words=None, ngram_range=(1, 2), min_df=1)
    tfidf = vectorizer.fit_transform(docs)

    global_sales = _normalize_scores({str(k): _to_float(v) for k, v in global_sales_raw.items()})
    interaction_scores = {str(k): _to_float(v) for k, v in interaction_scores_raw.items()}
    interaction_norm = _normalize_scores(interaction_scores)

    # user profile vector (weighted by interacted products + search terms)
    profile = np.zeros((1, tfidf.shape[1]), dtype=np.float64)
    total_w = 0.0
    for pid_str, w in interaction_norm.items():
        pid = int(pid_str)
        idx = id_to_idx.get(pid)
        if idx is None:
            continue
        profile += tfidf[idx].toarray() * float(max(0.0, w))
        total_w += float(max(0.0, w))

    if search_terms:
        search_doc = " ".join([_safe_text(s) for s in search_terms]).strip()
        if search_doc:
            qvec = vectorizer.transform([search_doc]).toarray()
            profile += qvec * 0.6
            total_w += 0.6

    if total_w > 0:
        profile = profile / total_w

    # Content score from profile
    if total_w > 0:
        content_scores = cosine_similarity(profile, tfidf).flatten()
    else:
        content_scores = np.zeros(len(products), dtype=np.float64)

    # category affinity for offers
    category_affinity = defaultdict(float)
    for pid_str, w in interaction_norm.items():
        pid = int(pid_str)
        idx = id_to_idx.get(pid)
        if idx is None:
            continue
        cat = _safe_text(products[idx].get("category")).upper()
        if cat:
            category_affinity[cat] += float(w)
    if category_affinity:
        max_cat = max(category_affinity.values()) or 1.0
        for k in list(category_affinity.keys()):
            category_affinity[k] = category_affinity[k] / max_cat

    rec_scored: List[Tuple[float, int]] = []
    trend_scored: List[Tuple[float, int]] = []
    offers_scored: List[Tuple[float, int]] = []

    for i, p in enumerate(products):
        pid = int(p.get("id", 0) or 0)
        if pid <= 0:
            continue
        if int(p.get("stock", 0) or 0) <= 0:
            continue

        pid_key = str(pid)
        trend = float(global_sales.get(pid_key, 0.0))
        behavior = float(interaction_norm.get(pid_key, 0.0))
        content = float(content_scores[i]) if i < len(content_scores) else 0.0
        hybrid = (0.45 * content) + (0.35 * behavior) + (0.20 * trend)

        rec_scored.append((hybrid, pid))
        trend_scored.append((trend, pid))

        if bool(p.get("promo_active", False)):
            cat = _safe_text(p.get("category")).upper()
            cat_score = float(category_affinity.get(cat, 0.0))
            offer_score = (0.55 * cat_score) + (0.45 * trend)
            offers_scored.append((offer_score, pid))

    recommended = _top_ids(rec_scored, limit)
    trending = _top_ids(trend_scored, limit)
    offers = _top_ids(offers_scored, limit)

    similar: List[int] = []
    if last_viewed_product_id > 0 and last_viewed_product_id in id_to_idx:
        src_idx = id_to_idx[last_viewed_product_id]
        sims = cosine_similarity(tfidf[src_idx], tfidf).flatten()
        sim_scored = []
        for i, score in enumerate(sims):
            pid = ids[i]
            if pid <= 0 or pid == last_viewed_product_id:
                continue
            if int(products[i].get("stock", 0) or 0) <= 0:
                continue
            sim_scored.append((float(score), pid))
        similar = _top_ids(sim_scored, limit)

    return {
        "recommended": recommended,
        "similar": similar,
        "trending": trending,
        "offers": offers,
    }


def main() -> int:
    try:
        payload = json.loads(sys.stdin.read() or "{}")
        sections = rank_sections(payload)
        # Backward compatibility for older Symfony callers.
        ids = sections.get("recommended", [])
        sys.stdout.write(json.dumps({"ids": ids, "sections": sections}, ensure_ascii=False))
        return 0
    except Exception as exc:
        sys.stdout.write(json.dumps({"ids": [], "sections": {}, "error": str(exc)}, ensure_ascii=False))
        return 1


if __name__ == "__main__":
    raise SystemExit(main())

