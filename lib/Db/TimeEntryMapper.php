<?php

declare(strict_types=1);

/**
 * TimeEntryMapper for the arbeitszeitcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * TimeEntryMapper
 */
class TimeEntryMapper extends QBMapper
{
	/**
	 * TimeEntryMapper constructor
	 *
	 * @param IDBConnection $db
	 */
	public function __construct(IDBConnection $db)
	{
		parent::__construct($db, 'at_entries', TimeEntry::class);
	}

	/**
	 * Find time entry by ID
	 *
	 * @param int $id
	 * @return TimeEntry
	 * @throws DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 */
	public function find(int $id): TimeEntry
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * Find all time entries for a user
	 *
	 * @param string $userId
	 * @param int|null $limit
	 * @param int|null $offset
	 * @return TimeEntry[]
	 */
	public function findByUser(string $userId, ?int $limit = null, ?int $offset = null): array
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->orderBy('start_time', 'DESC');

		if ($limit !== null) {
			$qb->setMaxResults($limit);
		}
		if ($offset !== null) {
			$qb->setFirstResult($offset);
		}

		return $this->findEntities($qb);
	}

	/**
	 * Find time entries by user and date range
	 *
	 * @param string $userId
	 * @param \DateTime $startDate
	 * @param \DateTime $endDate
	 * @return TimeEntry[]
	 */
	public function findByUserAndDateRange(string $userId, \DateTime $startDate, \DateTime $endDate): array
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->gte('start_time', $qb->createNamedParameter($startDate, IQueryBuilder::PARAM_DATE)))
			->andWhere($qb->expr()->lt('start_time', $qb->createNamedParameter($endDate, IQueryBuilder::PARAM_DATE)))
			->orderBy('start_time', 'ASC');

		return $this->findEntities($qb);
	}

	/**
	 * Find active time entry for a user (currently clocked in)
	 *
	 * @param string $userId
	 * @return TimeEntry|null
	 */
	public function findActiveByUser(string $userId): ?TimeEntry
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('status', $qb->createNamedParameter(TimeEntry::STATUS_ACTIVE)))
			->orderBy('start_time', 'DESC')
			->setMaxResults(1);

		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException $e) {
			return null;
		}
	}

	/**
	 * Find time entries by user and status
	 *
	 * @param string $userId
	 * @param string $status
	 * @return TimeEntry[]
	 */
	public function findByUserAndStatus(string $userId, string $status): array
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('status', $qb->createNamedParameter($status)))
			->orderBy('start_time', 'DESC');

		return $this->findEntities($qb);
	}

	/**
	 * Find time entries on break for a user
	 *
	 * @param string $userId
	 * @return TimeEntry|null
	 */
	public function findOnBreakByUser(string $userId): ?TimeEntry
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('status', $qb->createNamedParameter(TimeEntry::STATUS_BREAK)))
			->orderBy('start_time', 'DESC')
			->setMaxResults(1);

		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException $e) {
			return null;
		}
	}

	/**
	 * Get total hours for a user in a date range
	 *
	 * @param string $userId
	 * @param \DateTime $startDate
	 * @param \DateTime $endDate
	 * @return float
	 */
	public function getTotalHoursByUserAndDateRange(string $userId, \DateTime $startDate, \DateTime $endDate): float
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->createFunction('SUM(' .
			'CASE ' .
			'WHEN end_time IS NOT NULL THEN ' .
				'((UNIX_TIMESTAMP(end_time) - UNIX_TIMESTAMP(start_time)) / 3600) - ' .
				'COALESCE(((UNIX_TIMESTAMP(break_end_time) - UNIX_TIMESTAMP(break_start_time)) / 3600), 0) ' .
			'ELSE 0 ' .
			'END) as total_hours'))
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->gte('start_time', $qb->createNamedParameter($startDate, IQueryBuilder::PARAM_DATE)))
			->andWhere($qb->expr()->lt('start_time', $qb->createNamedParameter($endDate, IQueryBuilder::PARAM_DATE)))
			->andWhere($qb->expr()->in('status', $qb->createNamedParameter([
				TimeEntry::STATUS_COMPLETED,
				TimeEntry::STATUS_PENDING_APPROVAL
			], IQueryBuilder::PARAM_STR_ARRAY)));

		$result = $qb->executeQuery()->fetchOne();
		return (float)($result ?: 0);
	}

	/**
	 * Get total break hours for a user in a date range
	 *
	 * @param string $userId
	 * @param \DateTime $startDate
	 * @param \DateTime $endDate
	 * @return float
	 */
	public function getTotalBreakHoursByUserAndDateRange(string $userId, \DateTime $startDate, \DateTime $endDate): float
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->createFunction('SUM(' .
			'COALESCE(((UNIX_TIMESTAMP(break_end_time) - UNIX_TIMESTAMP(break_start_time)) / 3600), 0)' .
			') as total_break_hours'))
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->gte('start_time', $qb->createNamedParameter($startDate, IQueryBuilder::PARAM_DATE)))
			->andWhere($qb->expr()->lt('start_time', $qb->createNamedParameter($endDate, IQueryBuilder::PARAM_DATE)))
			->andWhere($qb->expr()->in('status', $qb->createNamedParameter([
				TimeEntry::STATUS_COMPLETED,
				TimeEntry::STATUS_PENDING_APPROVAL
			], IQueryBuilder::PARAM_STR_ARRAY)));

		$result = $qb->executeQuery()->fetchOne();
		return (float)($result ?: 0);
	}

	/**
	 * Count time entries for a user
	 *
	 * @param string $userId
	 * @return int
	 */
	public function countByUser(string $userId): int
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->createFunction('COUNT(*)'))
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));

		return (int)$qb->executeQuery()->fetchOne();
	}

	/**
	 * Get time entries with project information (for integration with ProjectCheck)
	 *
	 * @param array $filters
	 * @return array
	 */
	public function getTimeEntriesWithProjectInfo(array $filters = []): array
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('te.*')
			->from($this->getTableName(), 'te')
			->leftJoin('te', 'projectcheck_projects', 'pcp', $qb->expr()->eq('te.project_check_project_id', 'pcp.id'))
			->addSelect('pcp.name as project_name')
			->addSelect('pcp.customer_id as customer_id');

		// Apply filters
		if (isset($filters['user_id'])) {
			$qb->andWhere($qb->expr()->eq('te.user_id', $qb->createNamedParameter($filters['user_id'])));
		}

		if (isset($filters['project_id'])) {
			$qb->andWhere($qb->expr()->eq('te.project_check_project_id', $qb->createNamedParameter($filters['project_id'])));
		}

		if (isset($filters['start_date'])) {
			$qb->andWhere($qb->expr()->gte('te.start_time', $qb->createNamedParameter($filters['start_date'], IQueryBuilder::PARAM_DATE)));
		}

		if (isset($filters['end_date'])) {
			$qb->andWhere($qb->expr()->lt('te.start_time', $qb->createNamedParameter($filters['end_date'], IQueryBuilder::PARAM_DATE)));
		}

		if (isset($filters['status'])) {
			$qb->andWhere($qb->expr()->eq('te.status', $qb->createNamedParameter($filters['status'])));
		}

		$qb->orderBy('te.start_time', 'DESC');

		if (isset($filters['limit'])) {
			$qb->setMaxResults((int)$filters['limit']);
		}

		if (isset($filters['offset'])) {
			$qb->setFirstResult((int)$filters['offset']);
		}

		return $qb->executeQuery()->fetchAll();
	}

	/**
	 * Count time entries with filters
	 *
	 * @param array $filters
	 * @return int
	 */
	public function count(array $filters = []): int
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->createFunction('COUNT(*)'))
			->from($this->getTableName(), 'te');

		// Apply same filters as getTimeEntriesWithProjectInfo
		if (isset($filters['user_id'])) {
			$qb->andWhere($qb->expr()->eq('te.user_id', $qb->createNamedParameter($filters['user_id'])));
		}

		if (isset($filters['project_id'])) {
			$qb->andWhere($qb->expr()->eq('te.project_check_project_id', $qb->createNamedParameter($filters['project_id'])));
		}

		if (isset($filters['start_date'])) {
			$qb->andWhere($qb->expr()->gte('te.start_time', $qb->createNamedParameter($filters['start_date'], IQueryBuilder::PARAM_DATE)));
		}

		if (isset($filters['end_date'])) {
			$qb->andWhere($qb->expr()->lt('te.start_time', $qb->createNamedParameter($filters['end_date'], IQueryBuilder::PARAM_DATE)));
		}

		if (isset($filters['status'])) {
			$qb->andWhere($qb->expr()->eq('te.status', $qb->createNamedParameter($filters['status'])));
		}

		return (int)$qb->executeQuery()->fetchOne();
	}

	/**
	 * Get time entries pending approval
	 *
	 * @param int|null $limit
	 * @param int|null $offset
	 * @return TimeEntry[]
	 */
	public function findPendingApproval(?int $limit = null, ?int $offset = null): array
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('status', $qb->createNamedParameter(TimeEntry::STATUS_PENDING_APPROVAL)))
			->orderBy('start_time', 'DESC');

		if ($limit !== null) {
			$qb->setMaxResults($limit);
		}
		if ($offset !== null) {
			$qb->setFirstResult($offset);
		}

		return $this->findEntities($qb);
	}

	/**
	 * Count distinct users with time entries on a specific date
	 *
	 * @param \DateTime $date
	 * @return int
	 */
	public function countDistinctUsersByDate(\DateTime $date): int
	{
		$startOfDay = clone $date;
		$startOfDay->setTime(0, 0, 0);
		$endOfDay = clone $date;
		$endOfDay->setTime(23, 59, 59);
		$endOfDay->modify('+1 day'); // Make exclusive

		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->createFunction('COUNT(DISTINCT user_id)'))
			->from($this->getTableName())
			->where($qb->expr()->gte('start_time', $qb->createNamedParameter($startOfDay, IQueryBuilder::PARAM_DATE)))
			->andWhere($qb->expr()->lt('start_time', $qb->createNamedParameter($endOfDay, IQueryBuilder::PARAM_DATE)));

		return (int)$qb->executeQuery()->fetchOne();
	}

	/**
	 * Get time entries for manager approval (team members)
	 *
	 * @param array $userIds Team member user IDs
	 * @param int|null $limit
	 * @param int|null $offset
	 * @return TimeEntry[]
	 */
	public function findPendingApprovalForUsers(array $userIds, ?int $limit = null, ?int $offset = null): array
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('status', $qb->createNamedParameter(TimeEntry::STATUS_PENDING_APPROVAL)))
			->andWhere($qb->expr()->in('user_id', $qb->createNamedParameter($userIds, IQueryBuilder::PARAM_STR_ARRAY)))
			->orderBy('start_time', 'DESC');

		if ($limit !== null) {
			$qb->setMaxResults($limit);
		}
		if ($offset !== null) {
			$qb->setFirstResult($offset);
		}

		return $this->findEntities($qb);
	}
}