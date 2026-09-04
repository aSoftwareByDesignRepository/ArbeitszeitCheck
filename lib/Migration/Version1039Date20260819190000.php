<?php

declare(strict_types=1);

/**
 * Fix Outlook iCal token uniqueness: one row per tenant/manager/team scope.
 *
 * Version1038 shipped a unique index on (tenant_id, manager_user_id, team_id, is_active)
 * which allowed at most one revoked row — the second rotation failed when soft-revoking.
 */

namespace OCA\ArbeitszeitCheck\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1039Date20260819190000 extends SimpleMigrationStep
{
	public const LEGACY_ACTIVE_UNIQUE = 'azc_out_ical_tok_active_uid_uq';

	public const SCOPE_UNIQUE = 'azc_out_ical_tok_scope_uq';

	public function __construct(
		private IDBConnection $db,
	) {
	}

	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void
	{
		if (!$this->db->tableExists('azc_outlook_ical_tokens')) {
			return;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('tenant_id', 'manager_user_id', 'team_id')
			->selectAlias($qb->func()->max('id'), 'keep_id')
			->selectAlias($qb->func()->count('id'), 'row_count')
			->from('azc_outlook_ical_tokens')
			->groupBy('tenant_id', 'manager_user_id', 'team_id');

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
			$del->delete('azc_outlook_ical_tokens')
				->where($del->expr()->eq('tenant_id', $del->createNamedParameter((string)$group['tenant_id'])))
				->andWhere($del->expr()->eq('manager_user_id', $del->createNamedParameter((string)$group['manager_user_id'])))
				->andWhere($del->expr()->eq('team_id', $del->createNamedParameter((int)$group['team_id'], IQueryBuilder::PARAM_INT)))
				->andWhere($del->expr()->neq('id', $del->createNamedParameter((int)$group['keep_id'], IQueryBuilder::PARAM_INT)));
			$deleted += $del->executeStatement();
		}

		if ($deleted > 0) {
			$output->info(sprintf(
				'arbeitszeitcheck: removed %d duplicate Outlook iCal token row(s) before enforcing scope uniqueness.',
				$deleted
			));
		}
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
	{
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('azc_outlook_ical_tokens')) {
			return null;
		}

		$table = $schema->getTable('azc_outlook_ical_tokens');
		$changed = false;

		if ($table->hasIndex(self::LEGACY_ACTIVE_UNIQUE)) {
			$table->dropIndex(self::LEGACY_ACTIVE_UNIQUE);
			$changed = true;
		}

		if (!$table->hasIndex(self::SCOPE_UNIQUE)) {
			$table->addUniqueIndex(
				['tenant_id', 'manager_user_id', 'team_id'],
				self::SCOPE_UNIQUE
			);
			$changed = true;
		}

		return $changed ? $schema : null;
	}
}
