<?php

declare(strict_types=1);

/**
 * Outlook iCal tokens: encrypted storage + one row per tenant/team/language.
 */

namespace OCA\ArbeitszeitCheck\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1042Date20260819220000 extends SimpleMigrationStep
{
	public const TEAM_SCOPE_UNIQUE = Version1040Date20260819200000::TEAM_SCOPE_UNIQUE;

	public const SCOPE_LANGUAGE_UNIQUE = 'azc_out_ical_tok_scope_lang_uq';

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
		$qb->select('tenant_id', 'team_id', 'feed_language_code')
			->selectAlias($qb->func()->max('id'), 'keep_id')
			->selectAlias($qb->func()->count('id'), 'row_count')
			->from('azc_outlook_ical_tokens')
			->groupBy('tenant_id', 'team_id', 'feed_language_code');

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
				->andWhere($del->expr()->eq('team_id', $del->createNamedParameter((int)$group['team_id'], IQueryBuilder::PARAM_INT)))
				->andWhere($del->expr()->eq('feed_language_code', $del->createNamedParameter((string)$group['feed_language_code'])))
				->andWhere($del->expr()->neq('id', $del->createNamedParameter((int)$group['keep_id'], IQueryBuilder::PARAM_INT)));
			$deleted += $del->executeStatement();
		}

		if ($deleted > 0) {
			$output->info(sprintf(
				'arbeitszeitcheck: removed %d duplicate Outlook iCal token row(s) before enforcing scope+language uniqueness.',
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

		if (!$table->hasColumn('token_encrypted')) {
			$table->addColumn('token_encrypted', Types::TEXT, [
				'notnull' => false,
				'default' => null,
			]);
			$changed = true;
		}

		if ($table->hasIndex(self::TEAM_SCOPE_UNIQUE)) {
			$table->dropIndex(self::TEAM_SCOPE_UNIQUE);
			$changed = true;
		}

		if (!$table->hasIndex(self::SCOPE_LANGUAGE_UNIQUE)) {
			$table->addUniqueIndex(
				['tenant_id', 'team_id', 'feed_language_code'],
				self::SCOPE_LANGUAGE_UNIQUE
			);
			$changed = true;
		}

		return $changed ? $schema : null;
	}
}
