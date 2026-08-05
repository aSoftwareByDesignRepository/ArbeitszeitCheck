<?php

declare(strict_types=1);

/**
 * Phase C (US-102): additive vacation hours columns.
 *
 * - at_absences.duration_hours — booked vacation hours (unit=hours)
 * - at_vacation_year_balance.carryover_hours — opening Resturlaub in hours
 *
 * Days columns remain for legacy/days mode and DutyCheck date-span display.
 * Idempotent: addColumn only when missing.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1037Date20260804120000 extends SimpleMigrationStep
{
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
	{
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$changed = false;

		if ($schema->hasTable('at_absences')) {
			$table = $schema->getTable('at_absences');
			if (!$table->hasColumn('duration_hours')) {
				$table->addColumn('duration_hours', 'decimal', [
					'notnull' => false,
					'precision' => 8,
					'scale' => 2,
					'default' => null,
				]);
				$changed = true;
			}
		}

		if ($schema->hasTable('at_vacation_year_balance')) {
			$table = $schema->getTable('at_vacation_year_balance');
			if (!$table->hasColumn('carryover_hours')) {
				$table->addColumn('carryover_hours', 'decimal', [
					'notnull' => false,
					'precision' => 8,
					'scale' => 2,
					'default' => null,
				]);
				$changed = true;
			}
		}

		return $changed ? $schema : null;
	}
}
