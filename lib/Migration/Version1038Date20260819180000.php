<?php

declare(strict_types=1);

/**
 * Outlook subscription feed tokens (per-team, per-manager).
 *
 * Table stores only hashed tokens and enforces tenant + active-token uniqueness.
 * Authorization is re-checked on each feed request to prevent permission drift.
 */

namespace OCA\ArbeitszeitCheck\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1038Date20260819180000 extends SimpleMigrationStep
{
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
	{
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('azc_outlook_ical_tokens')) {
			$t = $schema->createTable('azc_outlook_ical_tokens');
			$t->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
			]);
			$t->addColumn('tenant_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$t->addColumn('manager_user_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$t->addColumn('team_id', Types::INTEGER, [
				'notnull' => true,
			]);
			$t->addColumn('token_hash', Types::TEXT, [
				'notnull' => true,
			]);
			$t->addColumn('is_active', Types::SMALLINT, [
				'notnull' => true,
				'default' => 1,
			]);
			$t->addColumn('revoked_at', Types::DATETIME, [
				'notnull' => false,
				'default' => null,
			]);
			$t->addColumn('created_at', Types::DATETIME, [
				'notnull' => true,
			]);

			$t->setPrimaryKey(['id'], 'azc_out_ical_tok_pk');
			// One token row per tenant/manager/team; rotation updates in place.
			$t->addUniqueIndex(
				['tenant_id', 'manager_user_id', 'team_id'],
				'azc_out_ical_tok_scope_uq'
			);
		}

		return $schema;
	}
}

