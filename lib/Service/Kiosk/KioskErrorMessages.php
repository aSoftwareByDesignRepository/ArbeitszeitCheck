<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Service\Kiosk;

use OCP\IL10N;

/**
 * Human-readable, actionable kiosk error copy for admin UI and terminal API.
 *
 * Machine codes stay stable for clients; messages explain what to do next.
 */
final class KioskErrorMessages
{
	public function __construct(
		private readonly IL10N $l10n,
	) {
	}

	public function message(string $code): string
	{
		return match ($code) {
			'TERMINAL_LICENSE_REQUIRED' => $this->l10n->t(
				'A Terminal license is required. Open License administration to apply a key.',
			),
			'TERMINAL_DEVICE_LIMIT_REACHED' => $this->l10n->t(
				'All terminal license slots are in use. Revoke an unused terminal or upgrade the license.',
			),
			'PAIRING_CODE_INVALID' => $this->l10n->t(
				'Pairing code is invalid, expired, or already used. Create a new terminal pairing code.',
			),
			'KIOSK_USER_NOT_ALLOWED' => $this->l10n->t(
				'This employee is not allowed to use the kiosk. In Badges & PIN, turn on “Allow kiosk access” first.',
			),
			'KIOSK_CLOCK_STAMPING_DISABLED' => $this->l10n->t(
				'Clock in/out is not enabled for this employee. An administrator must enable stamping under time recording methods.',
			),
			'KIOSK_INTERNAL_ERROR' => $this->l10n->t(
				'Something went wrong on the server. Try again or contact your administrator.',
			),
			'KIOSK_RATE_LIMITED' => $this->l10n->t(
				'Too many attempts. Wait a few minutes, then try again.',
			),
			'KIOSK_RFID_ALREADY_ASSIGNED' => $this->l10n->t(
				'This badge is already assigned to another employee. Remove it from the other person first, or use a different badge.',
			),
			'KIOSK_RFID_INVALID' => $this->l10n->t(
				'The badge could not be read. Hold it flat on the NFC area or USB reader for 1–2 seconds, then try again.',
			),
			'KIOSK_CREDENTIAL_NOT_FOUND' => $this->l10n->t('Credential not found. It may have been deleted.'),
			'KIOSK_CREDENTIAL_UNKNOWN' => $this->l10n->t(
				'Badge not recognized. Assign it under Badges & PIN first, or check that kiosk access is allowed.',
			),
			'KIOSK_TERMINAL_NOT_FOUND' => $this->l10n->t(
				'Terminal not found. Refresh the page and select an active tablet.',
			),
			'KIOSK_TERMINAL_NOT_ACTIVE' => $this->l10n->t(
				'Only a paired (active) tablet can enroll badges. Finish pairing the terminal first.',
			),
			'KIOSK_TERMINAL_UNAUTHORIZED' => $this->l10n->t(
				'This tablet is no longer authorized. Pair it again with a new code.',
			),
			'ENROLLMENT_NOT_ACTIVE' => $this->l10n->t(
				'No badge scan is waiting on this tablet. In admin, click “Scan badge at tablet” again, then hold the badge.',
			),
			'ENROLLMENT_ACTIVE' => $this->l10n->t(
				'Badge assignment is in progress on this tablet. Hold the new badge on the reader to assign it.',
			),
			'KIOSK_BUSY' => $this->l10n->t(
				'Another PIN or badge change is still finishing for this employee or tablet. Wait a few seconds, then try again. If a scan is open, click “Cancel scan” first.',
			),
			'KIOSK_SCAN_FAILED' => $this->l10n->t(
				'Badge could not be saved. Check that the tablet is online, the employee still has kiosk access, then start the scan again.',
			),
			'KIOSK_IMPORT_TOO_LARGE' => $this->l10n->t('Import file is too large (max 1 MB).'),
			'KIOSK_SESSION_USED' => $this->l10n->t('This kiosk session was already used. Identify again.'),
			'KIOSK_SESSION_INVALID' => $this->l10n->t('Session expired. Identify again.'),
			'KIOSK_ACTION_INVALID' => $this->l10n->t('This action is not allowed in the current state.'),
			'KIOSK_DISABLED' => $this->l10n->t('Kiosk mode is disabled. Enable it in Kiosk administration.'),
			'PIN_INVALID' => $this->l10n->t('PIN is incorrect.'),
			'PIN_LOCKED' => $this->l10n->t('PIN is temporarily locked. Try again later.'),
			'MONTH_FINALIZED' => $this->l10n->t('This month is finalized. Contact your administrator.'),
			'KIOSK_USER_NOT_FOUND' => $this->l10n->t('Employee not found.'),
			default => $this->l10n->t(
				'The request failed ({code}). Check kiosk access, that the tablet is paired and online, then try again.',
				['code' => $code !== '' ? $code : 'unknown'],
			),
		};
	}
}
