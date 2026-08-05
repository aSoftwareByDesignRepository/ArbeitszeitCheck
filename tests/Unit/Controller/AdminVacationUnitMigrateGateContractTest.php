<?php

declare(strict_types=1);

/**
 * Admin vacation-unit migrate maps VAC_UNIT_CLIENT_GATE to HTTP 409.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\Controller;

use PHPUnit\Framework\TestCase;

class AdminVacationUnitMigrateGateContractTest extends TestCase
{
	public function testMigrateMapsClientGateToConflict(): void
	{
		$src = file_get_contents(dirname(__DIR__, 3) . '/lib/Controller/AdminController.php');
		$this->assertNotFalse($src);
		$this->assertStringContainsString('function migrateVacationUnit', $src);
		$this->assertStringContainsString('VAC_UNIT_CLIENT_GATE', $src);
		$this->assertStringContainsString('Http::STATUS_CONFLICT', $src);
		$this->assertStringContainsString('VAC_UNIT_MIGRATE_IN_PROGRESS', $src);
		$this->assertStringContainsString('Confirm that Employee apps are updated', $src);
	}
}
