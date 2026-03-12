<?php

declare(strict_types=1);

/**
 * Add unique constraint on (state, date, scope) to at_holidays to prevent
 * duplicate holiday rows (e.g. from concurrent statutory seeding).
 *
 * Before adding the index, we remove existing duplicates, keeping the row
 * with the smallest id per (state, date, scope).
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1008Date20260312000001 extends SimpleMigrationStep
{
	public function __construct(
		private IDBConnection $db,
		private IConfig $config
	) {
	}

	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void
	{
		$prefix = $this->config->getSystemValueString('dbtableprefix', 'oc_');
		$table = $prefix . 'at_holidays';

		// Remove duplicate rows: keep the one with the smallest id per (state, date, scope)
		// MySQL/MariaDB: DELETE with self-join
		$sql = "DELETE h1 FROM `{$table}` h1 " .
			"INNER JOIN `{$table}` h2 " .
			"ON h1.state = h2.state AND h1.date = h2.date AND h1.scope = h2.scope AND h1.id > h2.id";
		try {
			$this->db->executeStatement($sql);
		} catch (\Throwable $e) {
			// Table might not exist yet (fresh install); ignore
			if (str_contains((string)$e->getMessage(), "doesn't exist")) {
				return;
			}
			throw $e;
		}
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
	{
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('at_holidays')) {
			return null;
		}

		$table = $schema->getTable('at_holidays');
		// Max 30 chars for Oracle; "at_holidays_state_date_scope_uniq" = 31
		$indexName = 'at_holidays_state_date_scope_u';
		if ($table->hasIndex($indexName)) {
			return null;
		}

		$table->addUniqueIndex(['state', 'date', 'scope'], $indexName);

		return $schema;
	}
}
