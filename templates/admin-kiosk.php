<?php

declare(strict_types=1);

/** @var array $_ */
/** @var \OCP\IL10N $l */
$l = $_['l'] ?? \OCP\Util::getL10N('arbeitszeitcheck');

$kioskEnabled = !empty($_['kioskEnabled']);
$terminals = is_array($_['terminals'] ?? null) ? $_['terminals'] : [];
$terminalUsed = (int)($_['terminalDevicesUsed'] ?? 0);
$terminalLimit = (int)($_['terminalDevicesLimit'] ?? 0);
$licenseAdminUrl = (string)($_['licenseAdminUrl'] ?? '');
$requesttoken = (string)($_['requesttoken'] ?? '');
$i18n = is_array($_['i18n'] ?? null) ? $_['i18n'] : [];
$i18nJson = json_encode($i18n, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
if (!is_string($i18nJson)) {
	$i18nJson = '{}';
}

$statusLabel = static function (string $status) use ($l, $i18n): string {
	return match ($status) {
		'active' => (string)($i18n['statusActive'] ?? $l->t('Active')),
		'pending' => (string)($i18n['statusPending'] ?? $l->t('Pending pairing')),
		'revoked' => (string)($i18n['statusRevoked'] ?? $l->t('Revoked')),
		default => $status,
	};
};

$statusBadgeClass = static function (string $status): string {
	return match ($status) {
		'active' => 'azc-badge azc-badge--success',
		'pending' => 'azc-badge azc-badge--warning',
		default => 'azc-badge azc-badge--neutral',
	};
};

$meterPct = $terminalLimit > 0 ? min(100, (int)round(($terminalUsed / $terminalLimit) * 100)) : 0;
?>

<?php include __DIR__ . '/common/page-start.php'; ?>

<div class="azc-page-stack azc-kiosk-page" id="azc-kiosk-page"
	data-api-enabled="<?php p((string)($_['apiKioskEnabled'] ?? '')); ?>"
	data-api-terminals="<?php p((string)($_['apiTerminals'] ?? '')); ?>"
	data-api-credentials="<?php p((string)($_['apiCredentials'] ?? '')); ?>"
	data-api-rfid="<?php p((string)($_['apiRfid'] ?? '')); ?>"
	data-api-pin="<?php p((string)($_['apiPinGenerate'] ?? '')); ?>"
	data-api-enrollment-start="<?php p((string)($_['apiEnrollmentStart'] ?? '')); ?>"
	data-api-enrollment-status="<?php p((string)($_['apiEnrollmentStatus'] ?? '')); ?>"
	data-api-enrollment-cancel="<?php p((string)($_['apiEnrollmentCancel'] ?? '')); ?>"
	data-api-search-users="<?php p((string)($_['apiSearchUsers'] ?? '')); ?>"
	data-api-terminal-revoke="<?php p((string)($_['apiTerminalRevoke'] ?? '')); ?>"
	data-api-user-allowed="<?php p((string)($_['apiUserAllowed'] ?? '')); ?>"
	data-i18n="<?php p($i18nJson); ?>"
	data-requesttoken="<?php p($requesttoken); ?>">

	<div id="azc-kiosk-live" class="azc-sr-only" role="status" aria-live="polite" aria-atomic="true"></div>
	<div id="azc-kiosk-alert" class="azc-sr-only" role="alert" aria-live="assertive" aria-atomic="true"></div>
	<div id="azc-kiosk-feedback" class="azc-kiosk-feedback" role="status" hidden></div>

	<section class="azc-card azc-kiosk-section" aria-labelledby="azc-kiosk-enable-heading">
		<header class="azc-kiosk-section__header">
			<h2 id="azc-kiosk-enable-heading" class="azc-kiosk-section__title"><?php p($l->t('Kiosk mode')); ?></h2>
			<p class="azc-kiosk-section__lead"><?php p($l->t('Turn foyer tablet clocking on or off. Needs a Terminal license and at least one paired tablet.')); ?></p>
		</header>
		<label class="azc-kiosk-toggle" for="azc-kiosk-enabled">
			<input type="checkbox" id="azc-kiosk-enabled" <?php echo $kioskEnabled ? 'checked' : ''; ?>
				aria-describedby="azc-kiosk-enable-hint">
			<span><?php p($l->t('Kiosk enabled')); ?></span>
		</label>
		<div class="azc-kiosk-capacity" id="azc-kiosk-enable-hint">
			<p class="azc-kiosk-capacity__label">
				<?php p($l->t('Terminal devices')); ?>:
				<strong><span id="azc-kiosk-terminal-used"><?php p((string)$terminalUsed); ?></span></strong>
				<span class="azc-kiosk-capacity__sep" aria-hidden="true">/</span>
				<strong><span id="azc-kiosk-terminal-limit"><?php p((string)$terminalLimit); ?></span></strong>
			</p>
			<div class="azc-kiosk-meter" role="meter"
				aria-valuemin="0"
				aria-valuenow="<?php p((string)$terminalUsed); ?>"
				aria-valuemax="<?php p((string)max(1, $terminalLimit)); ?>"
				aria-label="<?php p($l->t('Terminal devices used')); ?>">
				<div class="azc-kiosk-meter__fill<?php echo $terminalUsed >= $terminalLimit && $terminalLimit > 0 ? ' azc-kiosk-meter__fill--full' : ''; ?>"
					style="width: <?php p((string)$meterPct); ?>%" id="azc-kiosk-terminal-meter"></div>
			</div>
			<?php if ($licenseAdminUrl !== ''): ?>
			<p class="azc-kiosk-capacity__link">
				<a href="<?php p($licenseAdminUrl); ?>"><?php p($l->t('Manage license')); ?></a>
			</p>
			<?php endif; ?>
		</div>
	</section>

	<section class="azc-card azc-kiosk-section" aria-labelledby="azc-kiosk-terminals-heading">
		<header class="azc-kiosk-section__header">
			<h2 id="azc-kiosk-terminals-heading" class="azc-kiosk-section__title"><?php p($l->t('Terminals')); ?></h2>
			<p class="azc-kiosk-section__lead"><?php p($l->t('Create a terminal, then enter the pairing code on the tablet within 10 minutes.')); ?></p>
		</header>
		<div class="azc-kiosk-form">
			<div class="azc-kiosk-form__field">
				<label for="azc-kiosk-terminal-label" class="azc-field__label"><?php p($l->t('New terminal label')); ?></label>
				<input type="text" id="azc-kiosk-terminal-label" class="azc-input" maxlength="128"
					autocomplete="off"
					placeholder="<?php p($l->t('e.g. Main entrance')); ?>">
			</div>
			<button type="button" id="azc-kiosk-create-terminal" class="azc-btn azc-btn--primary">
				<?php p($l->t('Create terminal & pairing code')); ?>
			</button>
		</div>
		<div id="azc-kiosk-pairing-backdrop" class="azc-kiosk-modal-backdrop" hidden></div>
		<div id="azc-kiosk-pairing-modal" class="azc-kiosk-modal" hidden role="dialog" aria-modal="true" aria-labelledby="azc-kiosk-pairing-title">
			<h3 id="azc-kiosk-pairing-title"><?php p($l->t('Pairing code')); ?></h3>
			<p><?php p($l->t('Enter this code on the tablet within 10 minutes. It is shown only once.')); ?></p>
			<p class="azc-kiosk-pairing__code" id="azc-kiosk-pairing-code" aria-live="polite"></p>
			<p class="azc-kiosk-modal__hint" id="azc-kiosk-pairing-expires" hidden></p>
			<button type="button" id="azc-kiosk-pairing-close" class="azc-btn azc-btn--primary"><?php p($l->t('Close')); ?></button>
		</div>
		<div class="azc-table-wrap">
			<table class="azc-table azc-kiosk-table" id="azc-kiosk-terminals-table">
				<caption class="azc-sr-only"><?php p($l->t('Registered kiosk terminals')); ?></caption>
				<thead>
					<tr>
						<th scope="col"><?php p($l->t('Label')); ?></th>
						<th scope="col"><?php p($l->t('Status')); ?></th>
						<th scope="col"><?php p($l->t('Last seen')); ?></th>
						<th scope="col"><?php p($l->t('Actions')); ?></th>
					</tr>
				</thead>
				<tbody id="azc-kiosk-terminals-body">
					<?php if ($terminals === []): ?>
					<tr class="azc-kiosk-empty-row">
						<td colspan="4"><?php p($l->t('No terminals yet. Create one above to get a pairing code.')); ?></td>
					</tr>
					<?php endif; ?>
					<?php foreach ($terminals as $t): ?>
					<?php
					$tid = (string)($t['terminalId'] ?? '');
					$status = (string)($t['status'] ?? '');
					$canRevoke = $status === 'active' || $status === 'pending';
					$lastSeen = (string)($t['lastSeenAt'] ?? '');
					?>
					<tr data-terminal-id="<?php p($tid); ?>">
						<td data-label="<?php p($l->t('Label')); ?>"><?php p((string)($t['label'] ?? '')); ?></td>
						<td data-label="<?php p($l->t('Status')); ?>">
							<span class="<?php p($statusBadgeClass($status)); ?>"><?php p($statusLabel($status)); ?></span>
						</td>
						<td data-label="<?php p($l->t('Last seen')); ?>" class="azc-kiosk-last-seen" data-iso="<?php p($lastSeen); ?>">
							<?php p($lastSeen !== '' ? $lastSeen : (string)($i18n['neverSeen'] ?? $l->t('Never'))); ?>
						</td>
						<td data-label="<?php p($l->t('Actions')); ?>">
							<?php if ($canRevoke): ?>
							<button type="button" class="azc-btn azc-btn--small azc-btn--danger azc-kiosk-revoke-terminal"
								data-terminal-id="<?php p($tid); ?>">
								<?php p($l->t('Revoke')); ?>
							</button>
							<?php else: ?>
							<span class="azc-kiosk-no-action" aria-hidden="true">—</span>
							<?php endif; ?>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</section>

	<section class="azc-card azc-kiosk-section" aria-labelledby="azc-kiosk-creds-heading">
		<header class="azc-kiosk-section__header">
			<h2 id="azc-kiosk-creds-heading" class="azc-kiosk-section__title"><?php p($l->t('Badges & PIN')); ?></h2>
			<p class="azc-kiosk-section__lead"><?php p($l->t('Three simple steps: find the employee, allow kiosk access, then assign a badge or PIN.')); ?></p>
		</header>

		<ol class="azc-kiosk-steps" aria-label="<?php p($l->t('How to assign credentials')); ?>">
			<li><?php p((string)($i18n['stepSelect'] ?? $l->t('1. Find the employee'))); ?></li>
			<li><?php p((string)($i18n['stepAllow'] ?? $l->t('2. Allow kiosk access'))); ?></li>
			<li><?php p((string)($i18n['stepCredential'] ?? $l->t('3. Assign a badge or PIN'))); ?></li>
		</ol>

		<div class="azc-kiosk-form azc-kiosk-form--grid">
			<div class="azc-kiosk-search-wrap">
				<label for="azc-kiosk-user-search" class="azc-field__label"><?php p($l->t('Employee')); ?></label>
				<input type="search" id="azc-kiosk-user-search" class="azc-input" autocomplete="off"
					role="combobox"
					aria-autocomplete="list"
					aria-expanded="false"
					aria-controls="azc-kiosk-user-results"
					aria-haspopup="listbox"
					placeholder="<?php p($l->t('Search by name…')); ?>">
				<input type="hidden" id="azc-kiosk-selected-user" value="">
				<ul id="azc-kiosk-user-results" class="azc-kiosk-user-results" role="listbox" hidden></ul>
			</div>
			<div>
				<label for="azc-kiosk-enroll-terminal" class="azc-field__label"><?php p($l->t('Terminal for scan')); ?></label>
				<select id="azc-kiosk-enroll-terminal" class="azc-input">
					<option value=""><?php p($l->t('Select terminal…')); ?></option>
					<?php foreach ($terminals as $t): ?>
						<?php if (($t['status'] ?? '') === 'active'): ?>
						<option value="<?php p((string)($t['terminalId'] ?? '')); ?>"><?php p((string)($t['label'] ?? '')); ?></option>
						<?php endif; ?>
					<?php endforeach; ?>
				</select>
			</div>
		</div>

		<div id="azc-kiosk-selected-panel" class="azc-kiosk-selected-panel" hidden>
			<p class="azc-kiosk-selected-panel__name" id="azc-kiosk-selected-name"></p>
			<label class="azc-kiosk-toggle" for="azc-kiosk-selected-allowed">
				<input type="checkbox" id="azc-kiosk-selected-allowed">
				<span><?php p($l->t('Allow kiosk access')); ?></span>
			</label>
		</div>

		<div class="azc-kiosk-form__actions">
			<button type="button" id="azc-kiosk-start-enrollment" class="azc-btn azc-btn--primary">
				<?php p($l->t('Scan badge at tablet')); ?>
			</button>
			<button type="button" id="azc-kiosk-cancel-enrollment" class="azc-btn" hidden>
				<?php p($l->t('Cancel scan')); ?>
			</button>
			<button type="button" id="azc-kiosk-generate-pin" class="azc-btn">
				<?php p($l->t('Generate PIN')); ?>
			</button>
		</div>
		<p id="azc-kiosk-enrollment-status" class="azc-kiosk-enrollment-status" aria-live="polite"></p>
		<div id="azc-kiosk-pin-backdrop" class="azc-kiosk-modal-backdrop" hidden></div>
		<div id="azc-kiosk-pin-modal" class="azc-kiosk-modal" hidden role="dialog" aria-modal="true" aria-labelledby="azc-kiosk-pin-title">
			<h3 id="azc-kiosk-pin-title"><?php p($l->t('PIN generated')); ?></h3>
			<p class="azc-kiosk-modal__hint"><?php p($l->t('PIN is shown only once. Share it securely with the employee.')); ?></p>
			<p class="azc-kiosk-pairing__code" id="azc-kiosk-pin-code" aria-live="polite"></p>
			<button type="button" id="azc-kiosk-pin-close" class="azc-btn azc-btn--primary"><?php p($l->t('Close')); ?></button>
		</div>
		<div class="azc-table-wrap">
			<table class="azc-table azc-kiosk-table" id="azc-kiosk-creds-table">
				<caption class="azc-sr-only"><?php p($l->t('Kiosk credentials')); ?></caption>
				<thead>
					<tr>
						<th scope="col"><?php p($l->t('Employee')); ?></th>
						<th scope="col"><?php p($l->t('Credentials')); ?></th>
						<th scope="col"><?php p($l->t('Kiosk allowed')); ?></th>
						<th scope="col"><?php p($l->t('Actions')); ?></th>
					</tr>
				</thead>
				<tbody id="azc-kiosk-creds-body"></tbody>
			</table>
		</div>
	</section>
</div>

<?php include __DIR__ . '/common/page-end.php'; ?>
