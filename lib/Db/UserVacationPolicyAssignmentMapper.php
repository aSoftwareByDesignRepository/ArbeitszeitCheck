<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class UserVacationPolicyAssignmentMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'at_user_vacation_policies', UserVacationPolicyAssignment::class);
	}

	public function findCurrentByUser(string $userId, ?\DateTimeInterface $asOfDate = null): ?UserVacationPolicyAssignment {
		$asOfDate = $asOfDate ?? new \DateTimeImmutable('today');
		$date = $asOfDate->format('Y-m-d');
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->lte('effective_from', $qb->createNamedParameter($date, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->orX(
				$qb->expr()->isNull('effective_to'),
				$qb->expr()->gte('effective_to', $qb->createNamedParameter($date, IQueryBuilder::PARAM_STR))
			))
			->orderBy('effective_from', 'DESC')
			->setMaxResults(1);
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	public function findByUser(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->orderBy('effective_from', 'DESC')
			->addOrderBy('id', 'DESC');
		return $this->findEntities($qb);
	}

	public function deleteByUser(string $userId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
		return $qb->executeStatement();
	}
}

