#!/usr/bin/env bash
# Targeted mutation gauntlet: desklet clock-in payload / daily-max / empty project omit.
# Proves Vitest catches regressions in AzcDeskletActions.
#
# Usage (host, from app root):
#   bash tests/Mutation/run-desklet-actions-mutations.sh
set -euo pipefail

APP_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
TARGET="$APP_ROOT/js/common/desklet-actions.js"
BACKUP="$TARGET.mutation-bak"
NPM_TEST=(npm test -- --run js/common/desklet-actions.test.js)

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
	# portable in-place replace (no GNU sed -i assumptions beyond Linux)
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

# Baseline must be green
(cd "$APP_ROOT" && "${NPM_TEST[@]}")

kill_or_die omit_project_trim \
	'const trimmed = String(projectSelectValue).trim();' \
	'const trimmed = String(projectSelectValue);'

kill_or_die allow_clock_in_at_daily_max \
	"if (atDailyMaximum && (status === 'clocked_out' || status === 'paused' || status === 'completed')) {
			states['dz-clock-in'] = false;
		}" \
	"if (false && atDailyMaximum && (status === 'clocked_out' || status === 'paused' || status === 'completed')) {
			states['dz-clock-in'] = false;
		}"

kill_or_die drop_error_message_fallback \
	'|| fromBodyMessage' \
	'|| /* mutated */ false && fromBodyMessage'

echo "All desklet-actions mutations killed."

