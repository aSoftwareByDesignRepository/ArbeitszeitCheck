<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Service\Kiosk;

/**
 * Credential mutation lock keys (PIN generate / RFID assign / PIN lockout).
 *
 * Nextcloud's DB locking provider stores keys in oc_file_locks.key as VARCHAR(64).
 * Longer keys are truncated on insert, so releaseLock(fullKey) never matches the
 * row — exclusive locks stick at -1 until TTL. The next PIN/badge assign for that
 * user then fails with LockedException (surfaced as KIOSK_BUSY / “already running”).
 *
 * Keep every key ≤ 64 characters. md5 here is only a stable id, not a secret.
 *
 * @see KioskEnrollmentLockKeys Same constraint for enrollment locks.
 */
final class KioskCredentialLockKeys
{
	public static function forRfidAssign(string $userId): string
	{
		return 'azc/ra/' . md5($userId);
	}

	public static function forPinGenerate(string $userId): string
	{
		return 'azc/pg/' . md5($userId);
	}

	public static function forCredLockout(int $credId): string
	{
		return 'azc/cl/' . $credId;
	}
}
