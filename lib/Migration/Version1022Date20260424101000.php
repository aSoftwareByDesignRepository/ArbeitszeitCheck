<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1022Date20260424101000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('at_entitlement_snapshots')) {
			return $schema;
		}

		$table = $schema->getTable('at_entitlement_snapshots');
		if (!$table->hasIndex('at_ents_user_period_asof_uq')) {
			$table->addUniqueIndex(['user_id', 'period_key', 'as_of_date'], 'at_ents_user_period_asof_uq');
		}

		return $schema;
	}
}
