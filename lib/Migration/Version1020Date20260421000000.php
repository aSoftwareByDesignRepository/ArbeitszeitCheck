<?php

declare(strict_types=1);

/**
 * Repair all remaining orphaned paused time entries.
 *
 * Migration 1015 already closed paused entries with end_time IS NULL via a one-time
 * flag-guarded pass. This migration handles every remaining case unconditionally:
 *
 *  1. paused + end_time NOT NULL   → status = completed   (should never occur in normal use)
 *  2. paused + end_time IS NULL    → end_time = updated_at (or start_time as fallback), status = completed
 *
 * This migration is idempotent: it has no effect when no paused rows remain.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1020Date20260421000000 extends SimpleMigrationStep
{
	public function __construct(
		private IDBConnection $db,
	) {
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void
	{
		$output->info('Repairing orphaned paused time entries…');

		try {
			// 1. paused entries that already have an end_time: just fix the status.
			$qb1 = $this->db->getQueryBuilder();
			$fixed1 = (int)$qb1
				->update('at_entries')
				->set('status', $qb1->createNamedParameter('completed', IQueryBuilder::PARAM_STR))
				->where($qb1->expr()->eq('status', $qb1->createNamedParameter('paused', IQueryBuilder::PARAM_STR)))
				->andWhere($qb1->expr()->isNotNull('end_time'))
				->executeStatement();

			// 2. paused entries without end_time but with updated_at → end_time = updated_at.
			//    createFunction() is used to reference the column value directly because
			//    QueryBuilder does not support SET col = other_col via named parameters.
			$qb2 = $this->db->getQueryBuilder();
			$fixed2 = (int)$qb2
				->update('at_entries')
				->set('end_time', $qb2->createFunction('updated_at'))
				->set('status', $qb2->createNamedParameter('completed', IQueryBuilder::PARAM_STR))
				->where($qb2->expr()->eq('status', $qb2->createNamedParameter('paused', IQueryBuilder::PARAM_STR)))
				->andWhere($qb2->expr()->isNull('end_time'))
				->andWhere($qb2->expr()->isNotNull('updated_at'))
				->executeStatement();

			// 3. Edge case: paused entry with neither end_time nor updated_at.
			//    Use start_time so the entry has at least a zero-duration completed record.
			$qb3 = $this->db->getQueryBuilder();
			$fixed3 = (int)$qb3
				->update('at_entries')
				->set('end_time', $qb3->createFunction('start_time'))
				->set('status', $qb3->createNamedParameter('completed', IQueryBuilder::PARAM_STR))
				->where($qb3->expr()->eq('status', $qb3->createNamedParameter('paused', IQueryBuilder::PARAM_STR)))
				->andWhere($qb3->expr()->isNull('end_time'))
				->andWhere($qb3->expr()->isNull('updated_at'))
				->executeStatement();

			$total = $fixed1 + $fixed2 + $fixed3;
			$output->info(sprintf('Done: %d paused entr%s repaired.', $total, $total === 1 ? 'y' : 'ies'));
		} catch (\Throwable $e) {
			// Non-fatal: log and continue. A subsequent upgrade will re-run this check.
			$output->warning('Could not repair paused entries: ' . $e->getMessage());
			\OCP\Log\logger('arbeitszeitcheck')->error(
				'Version1020 migration failed: ' . $e->getMessage(),
				['exception' => $e]
			);
		}
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
	{
		return null;
	}
}
