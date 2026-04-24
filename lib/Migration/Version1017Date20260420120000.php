<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1017Date20260420120000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('at_tariff_rule_sets')) {
			$table = $schema->createTable('at_tariff_rule_sets');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20]);
			$table->addColumn('tariff_code', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('version', Types::STRING, ['notnull' => true, 'length' => 32]);
			$table->addColumn('jurisdiction', Types::STRING, ['notnull' => false, 'length' => 128]);
			$table->addColumn('valid_from', Types::DATE, ['notnull' => true]);
			$table->addColumn('valid_to', Types::DATE, ['notnull' => false]);
			$table->addColumn('activation_mode', Types::STRING, ['notnull' => true, 'length' => 16, 'default' => 'immediate']);
			$table->addColumn('status', Types::STRING, ['notnull' => true, 'length' => 16, 'default' => 'draft']);
			$table->addColumn('reference_model', Types::TEXT, ['notnull' => false]);
			$table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
			$table->addColumn('updated_at', Types::DATETIME, ['notnull' => true]);
			$table->setPrimaryKey(['id'], 'at_tariff_rule_sets_pk');
			$table->addUniqueIndex(['tariff_code', 'version'], 'at_trs_code_ver_uq');
			$table->addIndex(['status'], 'at_tariff_rule_sets_status_idx');
			$table->addIndex(['valid_from', 'valid_to'], 'at_tariff_rule_sets_valid_idx');
		}

		if (!$schema->hasTable('at_tariff_rule_modules')) {
			$table = $schema->createTable('at_tariff_rule_modules');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20]);
			$table->addColumn('rule_set_id', Types::BIGINT, ['notnull' => true, 'length' => 20]);
			$table->addColumn('module_type', Types::STRING, ['notnull' => true, 'length' => 32]);
			$table->addColumn('config_json', Types::TEXT, ['notnull' => true]);
			$table->addColumn('sort_order', Types::INTEGER, ['notnull' => true, 'default' => 0]);
			$table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
			$table->addColumn('updated_at', Types::DATETIME, ['notnull' => true]);
			$table->setPrimaryKey(['id'], 'at_tariff_rule_modules_pk');
			$table->addIndex(['rule_set_id'], 'at_trm_ruleset_idx');
			$table->addIndex(['module_type'], 'at_trm_type_idx');
		}

		if (!$schema->hasTable('at_user_vacation_policies')) {
			$table = $schema->createTable('at_user_vacation_policies');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20]);
			$table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('vacation_mode', Types::STRING, ['notnull' => true, 'length' => 32]);
			$table->addColumn('manual_days', Types::FLOAT, ['notnull' => false, 'precision' => 6, 'scale' => 2]);
			$table->addColumn('tariff_rule_set_id', Types::BIGINT, ['notnull' => false, 'length' => 20]);
			$table->addColumn('override_reason', Types::TEXT, ['notnull' => false]);
			$table->addColumn('effective_from', Types::DATE, ['notnull' => true]);
			$table->addColumn('effective_to', Types::DATE, ['notnull' => false]);
			$table->addColumn('created_by', Types::STRING, ['notnull' => true, 'length' => 64, 'default' => 'system']);
			$table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
			$table->addColumn('updated_at', Types::DATETIME, ['notnull' => true]);
			$table->setPrimaryKey(['id'], 'at_user_vac_policy_pk');
			$table->addIndex(['user_id'], 'at_user_vac_policy_user_idx');
			$table->addIndex(['effective_from', 'effective_to'], 'at_uvp_effective_idx');
			$table->addIndex(['tariff_rule_set_id'], 'at_user_vac_policy_tariff_idx');
		}

		if (!$schema->hasTable('at_entitlement_snapshots')) {
			$table = $schema->createTable('at_entitlement_snapshots');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20]);
			$table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('period_key', Types::STRING, ['notnull' => true, 'length' => 16]);
			$table->addColumn('as_of_date', Types::DATE, ['notnull' => true]);
			$table->addColumn('effective_entitlement_days', Types::FLOAT, ['notnull' => true, 'precision' => 6, 'scale' => 2]);
			$table->addColumn('source', Types::STRING, ['notnull' => true, 'length' => 32]);
			$table->addColumn('rule_set_id', Types::BIGINT, ['notnull' => false, 'length' => 20]);
			$table->addColumn('calculation_trace_json', Types::TEXT, ['notnull' => true]);
			$table->addColumn('computed_at', Types::DATETIME, ['notnull' => true]);
			$table->addColumn('computed_by', Types::STRING, ['notnull' => true, 'length' => 64, 'default' => 'system']);
			$table->addColumn('policy_fingerprint', Types::STRING, ['notnull' => false, 'length' => 128]);
			$table->setPrimaryKey(['id'], 'at_entitlement_snapshots_pk');
			$table->addIndex(['user_id', 'period_key'], 'at_ents_user_period_idx');
			$table->addIndex(['computed_at'], 'at_ents_computed_idx');
		}

		return $schema;
	}
}

