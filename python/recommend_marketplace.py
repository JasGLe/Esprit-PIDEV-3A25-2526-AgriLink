#!/usr/bin/env python3
import json
import sys
from typing import Dict, List, Any


def _to_float(v: Any) -> float:
    try:
        return float(v)
    except Exception:
        return 0.0


def rank_products(payload: Dict[str, Any]) -> List[int]:
    products = payload.get("products", []) or []
    global_sales = payload.get("global_sales", {}) or {}
    user_product_sales = payload.get("user_product_sales", {}) or {}
    user_category_sales = payload.get("user_category_sales", {}) or {}
    mode = str(payload.get("mode", "hybrid") or "hybrid").strip().lower()
    limit = int(payload.get("limit", 6) or 6)

    max_global = max([_to_float(v) for v in global_sales.values()] + [1.0])
    max_user_product = max([_to_float(v) for v in user_product_sales.values()] + [1.0])
    max_user_category = max([_to_float(v) for v in user_category_sales.values()] + [1.0])

    scored = []
    for p in products:
        pid = int(p.get("id", 0) or 0)
        if pid <= 0:
            continue

        category = str(p.get("category", "") or "").strip().upper()
        pid_key = str(pid)

        global_score = _to_float(global_sales.get(pid_key, 0.0)) / max_global
        user_product_score = _to_float(user_product_sales.get(pid_key, 0.0)) / max_user_product
        user_category_score = _to_float(user_category_sales.get(category, 0.0)) / max_user_category

        if mode == "top_selling":
            score = global_score
        elif mode == "history":
            score = user_product_score
        elif mode == "category":
            score = user_category_score
        else:
            # Hybrid (default): stable weighted score.
            score = (0.60 * global_score) + (0.25 * user_product_score) + (0.15 * user_category_score)
        if score <= 0:
            continue
        scored.append((score, pid))

    scored.sort(key=lambda x: (-x[0], -x[1]))
    return [pid for _, pid in scored[: max(1, limit)]]


def main() -> int:
    try:
        payload = json.loads(sys.stdin.read() or "{}")
        ids = rank_products(payload)
        sys.stdout.write(json.dumps({"ids": ids}, ensure_ascii=False))
        return 0
    except Exception as exc:
        sys.stdout.write(json.dumps({"ids": [], "error": str(exc)}, ensure_ascii=False))
        return 1


if __name__ == "__main__":
    raise SystemExit(main())

