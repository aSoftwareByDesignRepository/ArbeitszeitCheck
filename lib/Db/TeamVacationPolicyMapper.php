<?php

declare(strict_types=1);

/**
 * Mapper for L2 team-attached vacation policies
 * ({@see TeamVacationPolicy}).
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Db;

use OCA\ArbeitszeitCheck\Support\QueryInChunker;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception as DBException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class TeamVacationPolicyMapper extends QBMapper
{
	public function __construct(IDBConnection $db)
	{
		parent::__construct($db, 'at_team_vacation_policies', TeamVacationPolicy::class);
	}

	/**
	 * Active policies for *any* of the given team IDs, valid on `$asOfDate`.
	 * Returned ordered by `priority DESC, id ASC` for downstream tie-breaking;
	 * depth-by-team is applied by the engine, which has the tree shape in
	 * scope.
	 *
	 * @param list<int> $teamIds
	 * @return TeamVacationPolicy[]
	 */
	public function findActiveByTeamIds(array $teamIds, \DateTimeInterface $asOfDate): array
	{
		if ($teamIds === []) {
			return [];
		}
		try {
			$date = $asOfDate->format('Y-m-d');
			$qb = $this->db->getQueryBuilder();
			$qb->select('*')
				->from($this->getTableName())
				->where(QueryInChunker::in($qb, 'team_id', $teamIds, IQueryBuilder::PARAM_INT_ARRAY))
				->andWhere($qb->expr()->lte('effective_from', $qb->createNamedParameter($date, IQueryBuilder::PARAM_STR)))
				->andWhere($qb->expr()->orX(
					$qb->expr()->isNull('effective_to'),
					$qb->expr()->gte('effective_to', $qb->createNamedParameter($date, IQueryBuilder::PARAM_STR))
				))
				->orderBy('priority', 'DESC')
				->addOrderBy('id', 'ASC');
			return $this->findEntities($qb);
		} catch (DBException $e) {
			if ($e->getReason() === DBException::REASON_DATABASE_OBJECT_NOT_FOUND) {
				return [];
			}
			throw $e;
		}
	}

	/**
	 * @return TeamVacationPolicy[]
	 */
	public function findByTeamId(int $teamId): array
	{
		try {
			$qb = $this->db->getQueryBuilder();
			$qb->select('*')
				->from($this->getTableName())
				->where($qb->expr()->eq('team_id', $qb->createNamedParameter($teamId, IQueryBuilder::PARAM_INT)))
				->orderBy('priority', 'DESC')
				->addOrderBy('effective_from', 'DESC')
				->addOrderBy('id', 'ASC');
			return $this->findEntities($qb);
		} catch (DBException $e) {
			if ($e->getReason() === DBException::REASON_DATABASE_OBJECT_NOT_FOUND) {
				return [];
			}
			throw $e;
		}
	}

	/**
	 * @return TeamVacationPolicy[]
	 */
	public function findAll(): array
	{
		try {
			$qb = $this->db->getQueryBuilder();
			$qb->select('*')
				->from($this->getTableName())
				->orderBy('team_id', 'ASC')
				->addOrderBy('priority', 'DESC')
				->addOrderBy('effective_from', 'DESC');
			return $this->findEntities($qb);
		} catch (DBException $e) {
			if ($e->getReason() === DBException::REASON_DATABASE_OBJECT_NOT_FOUND) {
				return [];
			}
			throw $e;
		}
	}

	public function find(int $id): TeamVacationPolicy
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	public function deleteByTeamId(int $teamId): int
	{
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('team_id', $qb->createNamedParameter($teamId, IQueryBuilder::PARAM_INT)));
		return $qb->executeStatement();
	}
}
