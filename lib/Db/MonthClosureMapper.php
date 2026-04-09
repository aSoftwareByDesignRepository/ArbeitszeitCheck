<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class MonthClosureMapper extends QBMapper
{
	public function __construct(IDBConnection $db)
	{
		parent::__construct($db, 'at_month_closure', MonthClosure::class);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function findByUserAndMonth(string $userId, int $year, int $month): MonthClosure
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('year', $qb->createNamedParameter($year, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('month', $qb->createNamedParameter($month, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	public function findByUserAndMonthOptional(string $userId, int $year, int $month): ?MonthClosure
	{
		try {
			return $this->findByUserAndMonth($userId, $year, $month);
		} catch (DoesNotExistException $e) {
			return null;
		}
	}

	public function findLatestFinalizedBefore(string $userId, int $year, int $month): ?MonthClosure
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('status', $qb->createNamedParameter(MonthClosure::STATUS_FINALIZED)))
			->andWhere($qb->expr()->orX(
				$qb->expr()->lt('year', $qb->createNamedParameter($year, IQueryBuilder::PARAM_INT)),
				$qb->expr()->andX(
					$qb->expr()->eq('year', $qb->createNamedParameter($year, IQueryBuilder::PARAM_INT)),
					$qb->expr()->lt('month', $qb->createNamedParameter($month, IQueryBuilder::PARAM_INT))
				)
			))
			->orderBy('year', 'DESC')
			->addOrderBy('month', 'DESC')
			->setMaxResults(1);
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException $e) {
			return null;
		}
	}
}

