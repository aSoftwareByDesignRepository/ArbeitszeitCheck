<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Service;

use OCA\ArbeitszeitCheck\Db\EntitlementComputationSnapshot;
use OCA\ArbeitszeitCheck\Db\EntitlementComputationSnapshotMapper;

class EntitlementSnapshotService {
	public function __construct(
		private EntitlementComputationSnapshotMapper $snapshotMapper,
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
		$snapshot = new EntitlementComputationSnapshot();
		$snapshot->setUserId($userId);
		$snapshot->setPeriodKey((string)$year);
		$snapshot->setAsOfDate(new \DateTime($asOfDate->format('Y-m-d')));
		$snapshot->setEffectiveEntitlementDays(round($effectiveDays, 2));
		$snapshot->setSource($source);
		$snapshot->setRuleSetId($ruleSetId);
		$snapshot->setCalculationTrace($trace);
		$snapshot->setComputedAt(new \DateTimeImmutable('now'));
		$snapshot->setComputedBy($computedBy);
		$snapshot->setPolicyFingerprint($policyFingerprint);
		return $this->snapshotMapper->upsertSnapshot($snapshot);
	}
}

