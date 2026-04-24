<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Service;

use OCA\ArbeitszeitCheck\Db\EntitlementComputationSnapshot;
use OCA\ArbeitszeitCheck\Db\EntitlementComputationSnapshotMapper;
use OCP\Lock\ILockingProvider;

class EntitlementSnapshotService {
	public function __construct(
		private EntitlementComputationSnapshotMapper $snapshotMapper,
		private ILockingProvider $lockingProvider,
	) {
	}

	public function store(
		string $userId,
		int $year,
		\DateTimeInterface $asOfDate,
		float $effectiveDays,
		string $source,
		?int $ruleSetId,
		array $trace,
		string $computedBy,
		?string $policyFingerprint = null
	): EntitlementComputationSnapshot {
		$asOfDateOnly = new \DateTime($asOfDate->format('Y-m-d'));
		$lockKey = 'arbeitszeitcheck/entitlement-snapshot/' . $userId . '/' . $year . '/' . $asOfDateOnly->format('Y-m-d');
		$this->lockingProvider->acquireLock($lockKey, ILockingProvider::LOCK_EXCLUSIVE, 'Entitlement snapshot lock ' . $userId . ' ' . $year);
		try {
			$snapshot = new EntitlementComputationSnapshot();
			$snapshot->setUserId($userId);
			$snapshot->setPeriodKey((string)$year);
			$snapshot->setAsOfDate($asOfDateOnly);
			$snapshot->setEffectiveEntitlementDays(round($effectiveDays, 2));
			$snapshot->setSource($source);
			$snapshot->setRuleSetId($ruleSetId);
			$snapshot->setCalculationTrace($trace);
			$snapshot->setComputedAt(new \DateTimeImmutable('now'));
			$snapshot->setComputedBy($computedBy);
			$snapshot->setPolicyFingerprint($policyFingerprint);
			return $this->snapshotMapper->upsertSnapshot($snapshot);
		} finally {
			$this->lockingProvider->releaseLock($lockKey, ILockingProvider::LOCK_EXCLUSIVE);
		}
	}
}

