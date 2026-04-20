<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1019Date20260420150000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
	{
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		if (!$schema->hasTable('at_models')) {
			return $schema;
		}

		$table = $schema->getTable('at_models');
		if (!$table->hasColumn('work_days_per_week')) {
			$table->addColumn('work_days_per_week', Types::FLOAT, [
				'notnull' => true,
				'precision' => 4,
				'scale' => 2,
				'default' => 5.0,
			]);
		}

		return $schema;
	}
}

