<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class KioskEnrollmentMapper extends QBMapper
{
	public function __construct(IDBConnection $db)
	{
		parent::__construct($db, 'at_kiosk_enrollment', KioskEnrollment::class);
	}

	public function findActiveByTerminalId(string $terminalId, \DateTimeInterface $now): ?KioskEnrollment
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('terminal_id', $qb->createNamedParameter($terminalId)))
			->andWhere($qb->expr()->isNull('completed_at'))
			->andWhere($qb->expr()->gt('expires_at', $qb->createNamedParameter($now->format('Y-m-d H:i:s'))))
			->orderBy('id', 'DESC')
			->setMaxResults(1);
		$entities = $this->findEntities($qb);
		return $entities[0] ?? null;
	}

	/**
	 * Incomplete enrollment for a terminal, including already-expired rows.
	 * Used by force-abort so we can audit the target user even after TTL.
	 */
	public function findIncompleteByTerminalId(string $terminalId): ?KioskEnrollment
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('terminal_id', $qb->createNamedParameter($terminalId)))
			->andWhere($qb->expr()->isNull('completed_at'))
			->orderBy('id', 'DESC')
			->setMaxResults(1);
		$entities = $this->findEntities($qb);
		return $entities[0] ?? null;
	}

	/**
	 * @return int Number of incomplete enrollment rows deleted
	 */
	public function cancelForTerminal(string $terminalId): int
	{
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('terminal_id', $qb->createNamedParameter($terminalId)))
			->andWhere($qb->expr()->isNull('completed_at'));
		return $qb->executeStatement();
	}

	/**
	 * GC for background maintenance — remove expired incomplete enrollments.
	 *
	 * @return int Rows deleted
	 */
	public function deleteExpiredIncomplete(\DateTimeInterface $now): int
	{
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->isNull('completed_at'))
			->andWhere($qb->expr()->lte(
				'expires_at',
				$qb->createNamedParameter($now->format('Y-m-d H:i:s')),
			));
		return $qb->executeStatement();
	}

	public function findLatestCompletedByTerminalId(string $terminalId): ?KioskEnrollment
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('terminal_id', $qb->createNamedParameter($terminalId)))
			->andWhere($qb->expr()->isNotNull('completed_at'))
			->orderBy('completed_at', 'DESC')
			->setMaxResults(1);
		$entities = $this->findEntities($qb);
		return $entities[0] ?? null;
	}

	/**
	 * Atomically mark an enrollment completed. Returns true only once under
	 * concurrent badge scans for the same enrollment.
	 */
	public function claimComplete(int $enrollmentId, \DateTimeInterface $completedAt, \DateTimeInterface $now): bool
	{
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('completed_at', $qb->createNamedParameter($completedAt->format('Y-m-d H:i:s')))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($enrollmentId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNull('completed_at'))
			->andWhere($qb->expr()->gt(
				'expires_at',
				$qb->createNamedParameter($now->format('Y-m-d H:i:s')),
			));
		return $qb->executeStatement() === 1;
	}
}
