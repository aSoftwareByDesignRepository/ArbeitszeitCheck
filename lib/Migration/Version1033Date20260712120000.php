<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * AZC2 instance binding columns on license state.
 */
class Version1033Date20260712120000 extends SimpleMigrationStep
{
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
	{
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('azc_license_state')) {
			$table = $schema->getTable('azc_license_state');
			if (!$table->hasColumn('bound_instance_id')) {
				$table->addColumn('bound_instance_id', Types::STRING, [
					'notnull' => true,
					'length' => 64,
					'default' => '',
				]);
			}
			if (!$table->hasColumn('license_fingerprint')) {
				$table->addColumn('license_fingerprint', Types::STRING, [
					'notnull' => true,
					'length' => 64,
					'default' => '',
				]);
			}
		}

		return $schema;
	}
}
