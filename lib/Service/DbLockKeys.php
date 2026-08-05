<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Service;

/**
 * Short exclusive-lock keys for Nextcloud's DB locking provider.
 *
 * oc_file_locks.key is VARCHAR(64). Longer keys truncate on insert so
 * releaseLock(fullKey) never matches — exclusive locks stick at -1 until TTL
 * and the next mutation fails with LockedException / “already running”.
 *
 * md5 here is only a stable identifier, not a secret. Keep every key ≤ 64 chars.
 */
final class DbLockKeys
{
	public static function timeTrackingUser(string $userId): string
	{
		return 'azc/tt/' . md5($userId);
	}

	public static function absenceUser(string $userId): string
	{
		return 'azc/ab/' . md5($userId);
	}

	public static function monthClosure(string $userId, int $year, int $month): string
	{
		return 'azc/mc/' . md5(sprintf('%s|%04d|%02d', $userId, $year, $month));
	}

	public static function entitlementSnapshot(string $userId, int $year, string $asOfYmd): string
	{
		return 'azc/es/' . md5($userId . '|' . $year . '|' . $asOfYmd);
	}

	public static function vacationUnitMigration(): string
	{
		return 'azc/vu/migrate';
	}

	/** Exclusive lock while saving / sealing premium policy (NN-06). */
	public static function premiumPolicy(): string
	{
		return 'azc/pp/policy';
	}

	/** Exclusive lock while flipping vacation year mode + refreshing open allocations (AC-101.4). */
	public static function vacationYearMode(): string
	{
		return 'azc/vy/mode';
	}
}
