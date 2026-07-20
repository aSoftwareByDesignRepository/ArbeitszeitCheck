<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Repair;

use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

/**
 * Clears exclusive locks left behind by over-long lock keys.
 *
 * Nextcloud stores lock keys in oc_file_locks.key as VARCHAR(64). Older builds
 * used keys longer than 64 characters (kiosk PIN/RFID/enrollment, time tracking,
 * absence, month closure, entitlement snapshots). Inserts truncated the key, so
 * releaseLock(fullKey) never matched and exclusive locks stuck at -1 until TTL —
 * later mutations failed with LockedException / KIOSK_BUSY / “already running”.
 *
 * Idempotent: safe to run repeatedly.
 */
class ClearStuckKioskEnrollmentLocks implements IRepairStep
{
	/** @var list<string> LIKE prefixes for legacy over-long keys */
	private const LEGACY_KEY_PREFIXES = [
		'arbeitszeitcheck/kiosk_enroll%',
		'arbeitszeitcheck/kiosk_rfid_assign%',
		'arbeitszeitcheck/kiosk_pin_gen%',
		'arbeitszeitcheck/time-tracking-user/%',
		'arbeitszeitcheck/absence-user/%',
		'arbeitszeitcheck/month-closure/%',
		'arbeitszeitcheck/entitlement-snapshot/%',
	];

	public function __construct(
		private readonly IDBConnection $db,
	) {
	}

	public function getName(): string
	{
		return 'Clear stuck locks from truncated lock keys';
	}

	public function run(IOutput $output): void
	{
		if (!$this->db->tableExists('file_locks')) {
			return;
		}

		$deletedTotal = 0;
		foreach (self::LEGACY_KEY_PREFIXES as $prefix) {
			$qb = $this->db->getQueryBuilder();
			$qb->delete('file_locks')
				->where($qb->expr()->like(
					'key',
					$qb->createNamedParameter($prefix),
				));
			$deletedTotal += $qb->executeStatement();
		}
		if ($deletedTotal > 0) {
			$output->info(sprintf('Removed %d legacy truncated lock row(s).', $deletedTotal));
		}
	}
}
