#!/usr/bin/env bash
# Targeted mutation gauntlet: requestCorrection must reject justification-only
# payloads (the mobile screenshot bug) and stamp error_code proposed_change_required.
#
# Usage (host, from app root):
#   bash tests/Mutation/run-correction-request-mutations.sh
set -euo pipefail

APP_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
TARGET="$APP_ROOT/lib/Controller/TimeEntryController.php"
BACKUP="$TARGET.mutation-bak"
CONTAINER="${NEXTCLOUD_DOCKER_CONTAINER:-nextcloud-app}"
FILTER='TimeEntryControllerTest::testRequestCorrectionRejectsJustificationWithoutProposedChange|TimeEntryControllerTest::testRequestCorrectionAcceptsDateAndClockFields'

restore() {
	if [[ -f "$BACKUP" ]]; then
		mv -f "$BACKUP" "$TARGET"
	fi
}
trap restore EXIT

run_phpunit() {
	if ! docker container inspect "$CONTAINER" &>/dev/null; then
		echo "Docker container '$CONTAINER' is not running." >&2
		exit 1
	fi
	local phpunit="./vendor/bin/phpunit"
	if ! docker exec -w "/var/www/html/custom_apps/arbeitszeitcheck" "$CONTAINER" test -x ./vendor/bin/phpunit; then
		phpunit="/var/www/html/custom_apps/snackcheck/vendor/bin/phpunit"
	fi
	docker exec -u www-data -w "/var/www/html/custom_apps/arbeitszeitcheck" "$CONTAINER" \
		php -d opcache.enable_cli=0 "$phpunit" --filter "$FILTER"
}

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
	run_phpunit
	code=$?
	set -e
	restore
	if [[ $code -eq 0 ]]; then
		echo "MUTATION SURVIVED: $name" >&2
		exit 1
	fi
	echo "killed $name"
}

echo "== baseline =="
run_phpunit

kill_or_die skip_empty_proposal_guard \
	'if ($proposedData === []) {' \
	'if (false && $proposedData === []) {'

kill_or_die drop_proposed_change_error_code \
	"'error_code' => 'proposed_change_required'," \
	"'error_code' => 'justification_required',"

echo "All correction-request mutations killed."
