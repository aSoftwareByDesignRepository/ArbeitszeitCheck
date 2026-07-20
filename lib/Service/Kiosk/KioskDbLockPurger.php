<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Service\Kiosk;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Removes orphaned kiosk lock rows from oc_file_locks.
 *
 * Nextcloud's DB locking provider cannot release a lock after the holder crashes,
 * and pre-1.5.20 over-long keys truncated on insert so releaseLock never matched.
 * Exclusive rows then stick at lock=-1 until TTL (~1h). Admin cancel/start must
 * recover sooner than that.
 *
 * Only call from admin-authenticated recovery paths and repair steps — never from
 * the public kiosk identify/stamp surface.
 */
class KioskDbLockPurger
{
	public function __construct(
		private readonly IDBConnection $db,
	) {
	}

	/**
	 * @param list<string> $keys Exact lock keys to delete
	 * @return int Number of rows deleted
	 */
	public function purgeKeys(array $keys): int
	{
		if (!$this->db->tableExists('file_locks')) {
			return 0;
		}

		$normalized = [];
		foreach ($keys as $key) {
			if (!is_string($key) || $key === '') {
				continue;
			}
			$normalized[$key] = true;
		}
		$keys = array_keys($normalized);
		if ($keys === []) {
			return 0;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->delete('file_locks')
			->where($qb->expr()->in(
				'key',
				$qb->createNamedParameter($keys, IQueryBuilder::PARAM_STR_ARRAY),
			));
		return $qb->executeStatement();
	}

	/**
	 * Purge current + legacy enrollment locks for a terminal (and optional user).
	 */
	public function purgeEnrollmentLocks(?string $userId, string $terminalId): int
	{
		$terminalId = trim($terminalId);
		if ($terminalId === '') {
			return 0;
		}

		$keys = [
			KioskEnrollmentLockKeys::forTerminal($terminalId),
			self::legacyTruncated('arbeitszeitcheck/kiosk_enroll/' . $terminalId),
		];

		$userId = $userId !== null ? trim($userId) : '';
		if ($userId !== '') {
			$keys[] = KioskEnrollmentLockKeys::forUser($userId);
			$keys[] = self::legacyTruncated(
				'arbeitszeitcheck/kiosk_enroll_user/' . hash('sha256', $userId),
			);
		}

		return $this->purgeKeys($keys);
	}

	/**
	 * Purge current + legacy PIN/RFID assign locks for a user.
	 */
	public function purgeCredentialLocks(string $userId): int
	{
		$userId = trim($userId);
		if ($userId === '') {
			return 0;
		}

		$hash = hash('sha256', $userId);
		return $this->purgeKeys([
			KioskCredentialLockKeys::forRfidAssign($userId),
			KioskCredentialLockKeys::forPinGenerate($userId),
			self::legacyTruncated('arbeitszeitcheck/kiosk_rfid_assign/' . $hash),
			self::legacyTruncated('arbeitszeitcheck/kiosk_pin_gen/' . $hash),
		]);
	}

	/**
	 * Match what MySQL stored when a >64-char key was inserted into VARCHAR(64).
	 */
	public static function legacyTruncated(string $fullKey): string
	{
		if (strlen($fullKey) <= 64) {
			return $fullKey;
		}
		return substr($fullKey, 0, 64);
	}
}
