<?php

declare(strict_types=1);

/**
 * Crash-window heal must run on unit reads, not only on migrate Apply.
 * Heal takes exclusive migrate lock (or no-ops when migrate holds it).
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;

class VacationUnitHealOnReadContractTest extends TestCase
{
	public function testGetUnitHealsPendingViaMigrationService(): void
	{
		$src = file_get_contents(dirname(__DIR__, 3) . '/lib/Service/VacationUnitService.php');
		$this->assertNotFalse($src);
		$this->assertStringContainsString('healPendingMigrationIfPossible', $src);
		$this->assertStringContainsString('VacationUnitMigrationService::class', $src);
		$this->assertStringContainsString('completePendingMigrationIfNeeded', $src);
		$this->assertStringContainsString('healingPending', $src);
		preg_match(
			'/public function getUnit\(\): string\s*\{(?P<body>.*?)\n\t\}/s',
			$src,
			$m
		);
		$body = $m['body'] ?? '';
		$this->assertStringContainsString('$this->healPendingMigrationIfPossible()', $body);
		$this->assertStringContainsString('return $this->readUnitFromConfig()', $body);
	}

	public function testMigrationHealReadsConfigNotGetUnit(): void
	{
		$src = file_get_contents(dirname(__DIR__, 3) . '/lib/Service/VacationUnitMigrationService.php');
		$this->assertNotFalse($src);
		$this->assertStringContainsString('readConfiguredUnit', $src);
		$this->assertStringContainsString('completePendingMigrationUnlocked', $src);
		$this->assertStringContainsString('alreadyHoldingExclusive', $src);
		// LockedException on heal ⇒ leave pending untouched (no clear).
		$this->assertStringContainsString('leave pending untouched', $src);
		preg_match(
			'/private function completePendingMigrationUnlocked\(\): bool\s*\{(?P<body>.*?)\n\t\}\n\n\tprivate function readConfiguredUnit/s',
			$src,
			$m
		);
		$body = $m['body'] ?? '';
		$this->assertNotSame('', $body, 'completePendingMigrationUnlocked body must be extractable');
		$this->assertStringContainsString('$this->readConfiguredUnit()', $body);
		$this->assertStringNotContainsString('$this->unitService->getUnit()', $body);
		$this->assertStringContainsString('hasCommittedMigrationAudit', $body);
	}

	public function testMigrateCallsHealWithAlreadyHoldingExclusive(): void
	{
		$src = file_get_contents(dirname(__DIR__, 3) . '/lib/Service/VacationUnitMigrationService.php');
		$this->assertNotFalse($src);
		$this->assertStringContainsString('completePendingMigrationIfNeeded(true)', $src);
		$this->assertStringContainsString('peekUnit()', $src);
	}
}
