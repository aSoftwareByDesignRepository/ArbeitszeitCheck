<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Repair;

use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

/**
 * Clears exclusive locks left behind by over-long lock keys and orphan azc/* rows.
 *
 * Nextcloud stores lock keys in oc_file_locks.key as VARCHAR(64). Older builds
 * used keys longer than 64 characters (kiosk PIN/RFID/enrollment, time tracking,
 * absence, month closure, entitlement snapshots). Inserts truncated the key, so
 * releaseLock(fullKey) never matched and exclusive locks stuck at -1 until TTL —
 * later mutations failed with LockedException / KIOSK_BUSY / “already running”.
 *
 * Also clears exclusive short-key orphans (azc/eu|et|ra|pg) left by crashed
 * requests — Admin cancel force-aborts these at runtime; repair cleans leftovers
 * on upgrade.
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

	/**
	 * Short-key exclusive orphans from crashed holders (lock = -1).
	 *
	 * @var list<string>
	 */
	private const SHORT_EXCLUSIVE_PREFIXES = [
		'azc/eu/%',
		'azc/et/%',
		'azc/ra/%',
		'azc/pg/%',
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

		$exclusiveOrphans = 0;
		foreach (self::SHORT_EXCLUSIVE_PREFIXES as $prefix) {
			$qb = $this->db->getQueryBuilder();
			$qb->delete('file_locks')
				->where($qb->expr()->like(
					'key',
					$qb->createNamedParameter($prefix),
				))
				->andWhere($qb->expr()->eq(
					'lock',
					$qb->createNamedParameter(-1, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
				));
			$exclusiveOrphans += $qb->executeStatement();
		}
		$deletedTotal += $exclusiveOrphans;

		if ($deletedTotal > 0) {
			$output->info(sprintf(
				'Removed %d stuck kiosk/legacy lock row(s) (%d exclusive short-key orphans).',
				$deletedTotal,
				$exclusiveOrphans,
			));
		}
	}
}
