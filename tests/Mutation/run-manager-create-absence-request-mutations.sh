#!/usr/bin/env bash
# Targeted mutation gauntlet: ManagerController::createEmployeeAbsence must use
# public IRequest::getParams() (not protected Request::getContent()).
#
# Usage (host, from app root):
#   bash tests/Mutation/run-manager-create-absence-request-mutations.sh
#
# PHPUnit runs inside the Nextcloud Docker container (same as CI / run-app-phpunit.sh).
set -euo pipefail

APP_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
TARGET="$APP_ROOT/lib/Controller/ManagerController.php"
BACKUP="$TARGET.mutation-bak"
CONTAINER="${NEXTCLOUD_DOCKER_CONTAINER:-nextcloud-app}"
FILTER='ManagerControllerTest::testCreateEmployeeAbsence|ManagerCreateEmployeeAbsenceRequestContractTest'

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
	docker exec -w "/var/www/html/custom_apps/arbeitszeitcheck" "$CONTAINER" \
		./vendor/bin/phpunit --filter "$FILTER"
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

kill_or_die restore_protected_getContent \
	'$payload = $this->request->getParams();' \
	'$raw = $this->request->getContent();
			$payload = is_string($raw) && $raw !== '\'''\'' ? json_decode($raw, true) : null;
			if (!is_array($payload)) {
				$payload = [];
			}'

kill_or_die drop_getParams_call \
	'$payload = $this->request->getParams();' \
	'$payload = [];'

kill_or_die skip_canManageEmployee_check \
	'if (!$this->permissionService->canManageEmployee($actorUserId, $targetUserId)) {
				$this->permissionService->logPermissionDenied($actorUserId, '\''create_employee_absence'\'', '\''user'\'', $targetUserId);
				return new JSONResponse([
					'\''success'\'' => false,
					'\''error'\'' => $this->l10n->t('\''Access denied. You can only record absences for employees you manage.'\''),
				], Http::STATUS_FORBIDDEN);
			}' \
	'if (false && !$this->permissionService->canManageEmployee($actorUserId, $targetUserId)) {
				$this->permissionService->logPermissionDenied($actorUserId, '\''create_employee_absence'\'', '\''user'\'', $targetUserId);
				return new JSONResponse([
					'\''success'\'' => false,
					'\''error'\'' => $this->l10n->t('\''Access denied. You can only record absences for employees you manage.'\''),
				], Http::STATUS_FORBIDDEN);
			}'

echo "All manager-create-absence request mutations killed."
