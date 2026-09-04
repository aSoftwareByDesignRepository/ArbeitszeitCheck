#!/usr/bin/env python3
"""Insert missing catalog keys into all locale JSON files preserving en key order."""
from __future__ import annotations

import json
import subprocess
from collections import OrderedDict
from pathlib import Path

L10N = Path(__file__).parent
ROOT = L10N.parents[2]
INSERT = json.loads((L10N / "_insert_missing_runtime.json").read_text(encoding="utf-8"))
LANGS = ["en", "de", "fr", "es", "da", "nl", "it", "pl", "sv", "nb", "pt_BR"]


def load_ordered(path: Path) -> OrderedDict:
    return json.loads(path.read_text(encoding="utf-8"), object_pairs_hook=OrderedDict)


def insert_keys(base: OrderedDict, new_keys: dict[str, str]) -> OrderedDict:
    items = list(base["translations"].items())
    existing = set(base["translations"].keys())
    for key, value in new_keys.items():
        if key in existing:
            base["translations"][key] = value
            continue
        inserted = False
        new_items: list[tuple[str, str]] = []
        for k, v in items:
            if not inserted and k > key:
                new_items.append((key, value))
                inserted = True
            new_items.append((k, v))
        if not inserted:
            new_items.append((key, value))
        items = new_items
        existing.add(key)
    base["translations"] = OrderedDict(items)
    return base


def main() -> None:
    en = load_ordered(L10N / "en.json")
    en_new = {k: v["en"] for k, v in INSERT.items()}
    en = insert_keys(en, en_new)
    en_order = list(en["translations"].keys())
    en.write_text(json.dumps(en, ensure_ascii=False, indent=4) + "\n", encoding="utf-8") if False else None
    (L10N / "en.json").write_text(json.dumps(en, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")

    for lang in LANGS:
        if lang == "en":
            continue
        data = load_ordered(L10N / f"{lang}.json")
        lang_new = {k: v[lang] for k, v in INSERT.items()}
        data = insert_keys(data, lang_new)
        # Reorder to match en
        reordered = OrderedDict((k, data["translations"][k]) for k in en_order if k in data["translations"])
        if len(reordered) != len(en_order):
            missing = set(en_order) - set(reordered.keys())
            raise SystemExit(f"{lang} missing after insert: {missing}")
        data["translations"] = reordered
        (L10N / f"{lang}.json").write_text(json.dumps(data, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")
        print(f"{lang}: {len(INSERT)} keys ensured, total {len(reordered)}")

    subprocess.run(
        ["php", str(ROOT / "scripts/l10n/regenerate-l10n-js.php"), "--app=arbeitszeitcheck"],
        check=True,
        cwd=ROOT,
    )


if __name__ == "__main__":
    main()
