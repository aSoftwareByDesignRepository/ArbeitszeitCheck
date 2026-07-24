<?php

declare(strict_types=1);

/**
 * B-1 consolidation: guarantee the unique index on at_holidays and remove a
 * redundant duplicate index created by pre-release 1.6.0 builds.
 *
 * The duplicate-seeding race (B-1 in the DACH plan) was already closed by
 * {@see Version1008Date20260312000001}, which deduplicated (state, date,
 * scope) groups and added the unique index at_holidays_state_date_scope_u.
 * Early 1.6.0 development builds shipped this migration as a second
 * dedupe + addUniqueIndex('at_hol_st_dt_sc_u') — creating an identical,
 * redundant unique index on instances that ran them.
 *
 * This final version therefore:
 *  1. preSchemaChange — dedupe pass (idempotent, normally a no-op; protects
 *     databases restored from backups that never ran 1008).
 *  2. changeSchema — ensure the canonical unique index
 *     at_holidays_state_date_scope_u exists, and drop the redundant
 *     at_hol_st_dt_sc_u where a pre-release build created it.
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
	/** Canonical unique index name, unchanged since Version1008 (≤ 30 chars for Oracle). */
	public const UNIQUE_INDEX = 'at_holidays_state_date_scope_u';

	/** Redundant duplicate index created only by pre-release 1.6.0 builds. */
	public const REDUNDANT_INDEX = 'at_hol_st_dt_sc_u';

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
				'arbeitszeitcheck: removed %d duplicate holiday row(s) before enforcing the unique index.',
				$deleted
			));
		}
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
	{
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('at_holidays')) {
			return null;
		}

		$table = $schema->getTable('at_holidays');
		$changed = false;

		if (!$table->hasIndex(self::UNIQUE_INDEX)) {
			$table->addUniqueIndex(['state', 'date', 'scope'], self::UNIQUE_INDEX);
			$changed = true;
		}

		if ($table->hasIndex(self::REDUNDANT_INDEX)) {
			$table->dropIndex(self::REDUNDANT_INDEX);
			$changed = true;
		}

		return $changed ? $schema : null;
	}
}
