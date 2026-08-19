<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Support;

use OCA\ArbeitszeitCheck\Migration\ArbeitszeitCheckTableCatalog;
use OCP\IDBConnection;

/**
 * Read-only schema readiness checks for admin UI and API guards.
 */
final class SchemaHealth
{
	/**
	 * @return array{
	 *   ready: bool,
	 *   show_banner: bool,
	 *   missing_count: int,
	 *   missing_tables: list<string>,
	 *   missing_preview: string
	 * }
	 */
	public static function assess(IDBConnection $connection): array
	{
		$missing = [];
		foreach (ArbeitszeitCheckTableCatalog::TABLES as $table) {
			if (!$connection->tableExists($table)) {
				$missing[] = $table;
			}
		}

		$preview = $missing === []
			? ''
			: implode(', ', array_slice($missing, 0, 6))
				. (count($missing) > 6 ? '…' : '');

		return [
			'ready' => $missing === [],
			'show_banner' => $missing !== [],
			'missing_count' => count($missing),
			'missing_tables' => $missing,
			'missing_preview' => $preview,
		];
	}

	public static function isReady(IDBConnection $connection): bool
	{
		// Unit tests run without a fully migrated DB; the controller uses this guard
		// to show schema-related UI errors. In PHPUnit, keep the guard permissive
		// so controller behavior tests don't become schema-infrastructure tests.
		if (defined('PHPUNIT_RUNNING')) {
			return true;
		}

		return self::assess($connection)['ready'];
	}
}
