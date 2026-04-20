<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class MonthClosureRevisionMapper extends QBMapper
{
	public function __construct(IDBConnection $db)
	{
		parent::__construct($db, 'at_month_closure_revision', MonthClosureRevision::class);
	}

	/**
	 * @return MonthClosureRevision[]
	 */
	public function findByClosureId(int $closureId): array
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('closure_id', $qb->createNamedParameter($closureId, IQueryBuilder::PARAM_INT)))
			->orderBy('version', 'ASC');
		return $this->findEntities($qb);
	}
}
