<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Service\Kiosk;

/**
 * Enrollment lock key helpers.
 *
 * Nextcloud's DB locking provider stores keys in oc_file_locks.key as VARCHAR(64).
 * Longer keys are truncated on insert, so releaseLock(fullKey) cannot find the row —
 * exclusive locks stick at -1 until TTL and every later start/cancel/scan fails with
 * KIOSK_BUSY (which used to surface as HTTP 500 on cancel).
 *
 * Keep every key ≤ 64 characters. md5 here is only a stable id, not a secret.
 */
final class KioskEnrollmentLockKeys
{
	public static function forUser(string $userId): string
	{
		return 'azc/eu/' . md5($userId);
	}

	public static function forTerminal(string $terminalId): string
	{
		return 'azc/et/' . md5($terminalId);
	}
}
