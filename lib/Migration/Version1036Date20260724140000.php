<?php

declare(strict_types=1);

/**
 * Relax AZC2 instance-binding columns to nullable.
 *
 * {@see Version1033Date20260712120000} originally created
 * azc_license_state.bound_instance_id / license_fingerprint as
 * notnull with an empty-string default — a portability bug: Oracle stores ''
 * as NULL, so inserts relying on the default would violate the NOT NULL
 * constraint there. The columns are display/bookkeeping values whose readers
 * normalise NULL to '' ({@see \OCA\ArbeitszeitCheck\Db\LicenseState}), so
 * nullable is the correct shape. 1033 itself now creates them nullable on
 * fresh installs; this step relaxes databases that ran the released 1033.
 *
 * Idempotent: a re-run finds the columns already nullable and does nothing.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1036Date20260724140000 extends SimpleMigrationStep
{
	private const COLUMNS = ['bound_instance_id', 'license_fingerprint'];

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
	{
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('azc_license_state')) {
			return null;
		}

		$table = $schema->getTable('azc_license_state');
		$changed = false;

		foreach (self::COLUMNS as $name) {
			if (!$table->hasColumn($name)) {
				continue;
			}
			$column = $table->getColumn($name);
			if ($column->getNotnull()) {
				$column->setNotnull(false);
				$changed = true;
			}
		}

		return $changed ? $schema : null;
	}
}
