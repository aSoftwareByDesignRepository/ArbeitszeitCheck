#!/usr/bin/env python3
"""Apply manually curated l10n quality fixes (key→value only, no regex on strings)."""
from __future__ import annotations

import json
import subprocess
from pathlib import Path

L10N = Path(__file__).parent
ROOT = L10N.parents[2]


def load_json(path: Path) -> dict:
    with path.open(encoding="utf-8") as f:
        return json.load(f)


def save_json(lang: str, data: dict) -> None:
    with (L10N / f"{lang}.json").open("w", encoding="utf-8") as f:
        json.dump(data, f, ensure_ascii=False, indent=4)
        f.write("\n")


def apply_fixes(lang: str, fixes: dict[str, str]) -> int:
    data = load_json(L10N / f"{lang}.json")
    trans = data["translations"]
    applied = 0
    for key, value in fixes.items():
        if key in trans and value:
            trans[key] = value
            applied += 1
    data["translations"] = trans
    save_json(lang, data)
    return applied


def main() -> None:
    total = 0
    for path in sorted(L10N.glob("_quality_fixes_*.json")):
        lang = path.stem.replace("_quality_fixes_", "")
        fixes = load_json(path)
        n = apply_fixes(lang, fixes)
        print(f"{lang}: {n} fixes")
        total += n
    subprocess.run(
        ["php", str(ROOT / "scripts/l10n/regenerate-l10n-js.php"), "--app=arbeitszeitcheck"],
        check=True,
        cwd=ROOT,
    )
    print(f"Total: {total}")


if __name__ == "__main__":
    main()
