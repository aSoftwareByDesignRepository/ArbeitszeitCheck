#!/usr/bin/env bash
# Targeted mutation gauntlet: normalizeMutatingFetchInit / Utils.ajax empty POST body.
#
# Usage (host, from app root):
#   bash tests/Mutation/run-mutating-json-body-mutations.sh
set -euo pipefail

APP_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
TARGET="$APP_ROOT/js/common/utils.js"
BACKUP="$TARGET.mutation-bak"
NPM_TEST=(npm test -- --run js/common/utils.test.js)

restore() {
	if [[ -f "$BACKUP" ]]; then
		mv -f "$BACKUP" "$TARGET"
	fi
}
trap restore EXIT

kill_or_die() {
	local name="$1"
	local from="$2"
	local to="$3"
	cp "$TARGET" "$BACKUP"
	if ! grep -Fq "$from" "$TARGET"; then
		echo "Mutation anchor not found for $name" >&2
		exit 1
	fi
	python3 - "$TARGET" "$from" "$to" <<'PY'
import pathlib, sys
path, fr, to = sys.argv[1], sys.argv[2], sys.argv[3]
text = pathlib.Path(path).read_text()
if fr not in text:
    raise SystemExit('anchor missing at apply time')
pathlib.Path(path).write_text(text.replace(fr, to, 1))
PY
	echo "== mutation: $name =="
	set +e
	(cd "$APP_ROOT" && "${NPM_TEST[@]}")
	code=$?
	set -e
	restore
	if [[ $code -eq 0 ]]; then
		echo "MUTATION SURVIVED: $name" >&2
		exit 1
	fi
	echo "killed $name"
}

(cd "$APP_ROOT" && "${NPM_TEST[@]}")

kill_or_die drop_normalize_empty_json_body \
	"if (String(resolvedType).toLowerCase().includes('application/json')) {
      next.body = JSON.stringify({});
    }" \
	"if (false && String(resolvedType).toLowerCase().includes('application/json')) {
      next.body = JSON.stringify({});
    }"

kill_or_die drop_ajax_empty_object_body \
	'const payload = (data && typeof data === '"'"'object'"'"') ? data : {};
        config.body = JSON.stringify(payload);' \
	'const payload = (data && typeof data === '"'"'object'"'"') ? data : null;
        config.body = payload == null ? undefined : JSON.stringify(payload);'

echo "All mutating-json-body mutations killed."
