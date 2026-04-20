<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class EntitlementComputationSnapshotMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'at_entitlement_snapshots', EntitlementComputationSnapshot::class);
	}

	public function findLatestForUserAndPeriod(string $userId, string $periodKey): ?EntitlementComputationSnapshot {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('period_key', $qb->createNamedParameter($periodKey)))
			->orderBy('computed_at', 'DESC')
			->setMaxResults(1);
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	public function findByUser(string $userId, ?int $limit = null): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->orderBy('computed_at', 'DESC');
		if ($limit !== null) {
			$qb->setMaxResults(max(1, $limit));
		}
		return $this->findEntities($qb);
	}

	public function upsertSnapshot(EntitlementComputationSnapshot $snapshot): EntitlementComputationSnapshot {
		$existing = $this->findLatestForUserAndPeriod($snapshot->getUserId(), $snapshot->getPeriodKey());
		if ($existing !== null && $existing->getAsOfDate()->format('Y-m-d') === $snapshot->getAsOfDate()->format('Y-m-d')) {
			$existing->setEffectiveEntitlementDays($snapshot->getEffectiveEntitlementDays());
			$existing->setSource($snapshot->getSource());
			$existing->setRuleSetId($snapshot->getRuleSetId());
			$existing->setCalculationTraceJson($snapshot->getCalculationTraceJson());
			$existing->setComputedAt($snapshot->getComputedAt());
			$existing->setComputedBy($snapshot->getComputedBy());
			$existing->setPolicyFingerprint($snapshot->getPolicyFingerprint());
			return $this->update($existing);
		}
		return $this->insert($snapshot);
	}

	public function deleteByUser(string $userId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
		return $qb->executeStatement();
	}
}

