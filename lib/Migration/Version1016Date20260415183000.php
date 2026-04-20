<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1016Date20260415183000 extends SimpleMigrationStep
{
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
	{
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('at_entries')) {
			return $schema;
		}

		$table = $schema->getTable('at_entries');
		if (!$table->hasColumn('ended_reason')) {
			$table->addColumn('ended_reason', Types::STRING, [
				'notnull' => false,
				'length' => 64,
			]);
		}
		if (!$table->hasColumn('policy_applied')) {
			$table->addColumn('policy_applied', Types::STRING, [
				'notnull' => false,
				'length' => 64,
			]);
		}

		return $schema;
	}
}

