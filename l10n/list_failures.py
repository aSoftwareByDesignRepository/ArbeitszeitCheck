#!/usr/bin/env python3
"""List formal-quality failures (identical + informal) per locale."""
from __future__ import annotations

import json
import re
import subprocess
import sys
from pathlib import Path

L10N = Path(__file__).parent
ROOT = L10N.parents[2]


def main() -> None:
    proc = subprocess.run(
        ["php", str(ROOT / "scripts/l10n/extract-formal-gaps.php"), "--app=arbeitszeitcheck", "--json"],
        capture_output=True,
        text=True,
        check=True,
        cwd=ROOT,
    )
    gaps = json.loads(proc.stdout)
    for lang, data in gaps.items():
        identical = list(data.get("identical", {}))
        informal = list(data.get("informal", {}))
        all_fail = sorted(set(identical + informal))
        out = {
            "identical": identical,
            "informal": informal,
            "all": all_fail,
        }
        path = L10N / f"_fail_{lang}.json"
        path.write_text(json.dumps(out, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
        print(f"{lang}: identical={len(identical)} informal={len(informal)} total={len(all_fail)}")


if __name__ == "__main__":
    main()
