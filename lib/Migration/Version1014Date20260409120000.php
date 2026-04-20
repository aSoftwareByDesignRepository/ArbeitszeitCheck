<?php

declare(strict_types=1);

/**
 * Revision-safe month closure (snapshots + hash chain).
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1014Date20260409120000 extends SimpleMigrationStep
{
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
	{
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$changed = false;

		if (!$schema->hasTable('at_month_closure')) {
			$table = $schema->createTable('at_month_closure');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('user_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('year', Types::SMALLINT, [
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('month', Types::SMALLINT, [
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('version', Types::INTEGER, [
				'notnull' => true,
				'unsigned' => true,
				'default' => 1,
			]);
			$table->addColumn('status', Types::STRING, [
				'notnull' => true,
				'length' => 32,
			]);
			$table->addColumn('snapshot_hash', Types::STRING, [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('prev_snapshot_hash', Types::STRING, [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('canonical_payload', Types::TEXT, [
				'notnull' => false,
			]);
			$table->addColumn('finalized_at', Types::DATETIME, ['notnull' => false]);
			$table->addColumn('finalized_by', Types::STRING, [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('reopened_at', Types::DATETIME, ['notnull' => false]);
			$table->addColumn('reopened_by', Types::STRING, [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('reopen_reason', Types::TEXT, ['notnull' => false]);
			$table->setPrimaryKey(['id'], 'at_mc_pk');
			$table->addUniqueIndex(['user_id', 'year', 'month'], 'at_mc_user_ym_uq');
			$table->addIndex(['user_id', 'status'], 'at_mc_user_status_idx');
			$changed = true;
		}

		if (!$schema->hasTable('at_month_closure_revision')) {
			$table = $schema->createTable('at_month_closure_revision');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('closure_id', Types::BIGINT, [
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('version', Types::INTEGER, [
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('snapshot_hash', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('prev_snapshot_hash', Types::STRING, [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('canonical_payload', Types::TEXT, [
				'notnull' => true,
			]);
			$table->addColumn('sealed_at', Types::DATETIME, ['notnull' => true]);
			$table->addColumn('sealed_by', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->setPrimaryKey(['id'], 'at_mcr_pk');
			$table->addIndex(['closure_id', 'version'], 'at_mcr_closure_ver_idx');
			$table->addForeignKeyConstraint(
				'at_month_closure',
				['closure_id'],
				['id'],
				['onDelete' => 'CASCADE'],
				'at_mcr_closure_fk'
			);
			$changed = true;
		}

		return $changed ? $schema : null;
	}
}
