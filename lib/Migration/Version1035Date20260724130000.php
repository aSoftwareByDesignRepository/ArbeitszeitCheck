<?php

declare(strict_types=1);

/**
 * Fix B-1: duplicate holiday rows + missing unique index on at_holidays.
 *
 * at_holidays had no unique constraint on (state, date, scope), so two
 * parallel requests lazy-seeding the same state/year could insert statutory
 * rows twice (working-day math stayed correct via max-weight semantics, but
 * the admin list showed duplicates and the duplicate-catch in HolidayService
 * was dead code).
 *
 *  1. preSchemaChange — dedupe: for every (state, date, scope) group keep the
 *     row with MIN(id), delete the rest. Idempotent (second run finds no
 *     groups with more than one row).
 *  2. changeSchema — add unique index at_hol_st_dt_sc_u so the race can no
 *     longer produce duplicates and the insert-catch becomes effective.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1035Date20260724130000 extends SimpleMigrationStep
{
	public function __construct(
		private IDBConnection $db,
	) {
	}

	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void
	{
		if (!$this->db->tableExists('at_holidays')) {
			return;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('state', 'date', 'scope')
			->selectAlias($qb->func()->min('id'), 'keep_id')
			->selectAlias($qb->func()->count('id'), 'row_count')
			->from('at_holidays')
			->groupBy('state', 'date', 'scope');

		$duplicateGroups = [];
		$cursor = $qb->executeQuery();
		while (($row = $cursor->fetch()) !== false) {
			if ((int)$row['row_count'] > 1) {
				$duplicateGroups[] = $row;
			}
		}
		$cursor->closeCursor();

		$deleted = 0;
		foreach ($duplicateGroups as $group) {
			$del = $this->db->getQueryBuilder();
			$del->delete('at_holidays')
				->where($del->expr()->eq('state', $del->createNamedParameter((string)$group['state'])))
				->andWhere($del->expr()->eq('date', $del->createNamedParameter((string)$group['date'])))
				->andWhere($del->expr()->eq('scope', $del->createNamedParameter((string)$group['scope'])))
				->andWhere($del->expr()->neq('id', $del->createNamedParameter((int)$group['keep_id'], \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)));
			$deleted += $del->executeStatement();
		}

		if ($deleted > 0) {
			$output->info(sprintf(
				'arbeitszeitcheck: removed %d duplicate holiday row(s) before adding the unique index.',
				$deleted
			));
		}
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
	{
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('at_holidays')) {
			$t = $schema->getTable('at_holidays');
			if (!$t->hasIndex('at_hol_st_dt_sc_u')) {
				$t->addUniqueIndex(['state', 'date', 'scope'], 'at_hol_st_dt_sc_u');
			}
		}

		return $schema;
	}
}
