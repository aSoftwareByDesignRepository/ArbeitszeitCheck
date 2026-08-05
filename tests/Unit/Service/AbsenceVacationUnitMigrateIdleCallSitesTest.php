<?php

declare(strict_types=1);

/**
 * Call-site coverage: vacation migrate-idle asserts on every balance-touching mutation.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;

class AbsenceVacationUnitMigrateIdleCallSitesTest extends TestCase
{
	/**
	 * @return array<string, string> method name => body
	 */
	private function methodBodies(string $src): array
	{
		$bodies = [];
		if (!preg_match_all(
			'/\n\t(?:public|private|protected)\s+function\s+(\w+)\s*\(/',
			$src,
			$matches,
			PREG_OFFSET_CAPTURE
		)) {
			return [];
		}
		$names = $matches[1];
		for ($i = 0; $i < count($names); $i++) {
			$name = $names[$i][0];
			$start = $names[$i][1];
			$end = $i + 1 < count($names) ? $names[$i + 1][1] : strlen($src);
			$bodies[$name] = substr($src, $start, $end - $start);
		}

		return $bodies;
	}

	public function testVacationMutationEntryPointsAssertMigrateIdle(): void
	{
		$src = file_get_contents(dirname(__DIR__, 2) . '/../lib/Service/AbsenceService.php');
		$this->assertNotFalse($src);
		$bodies = $this->methodBodies($src);
		$methods = [
			'createAbsence',
			'createApprovedAbsenceForEmployeeByManager',
			'updateAbsence',
			'cancelAbsence',
			'shortenAbsence',
			'approveAbsence',
			'rejectAbsence',
			'approveBySubstitute',
			'declineBySubstitute',
			'doAutoApproveDbWork',
		];
		foreach ($methods as $method) {
			$this->assertArrayHasKey($method, $bodies, "Missing method {$method}");
			$this->assertStringContainsString(
				'assertVacationUnitMigrationIdle()',
				$bodies[$method],
				"Expected assertVacationUnitMigrationIdle in {$method}"
			);
		}
	}

	public function testMigrationServiceExposesIdleGuards(): void
	{
		$src = file_get_contents(dirname(__DIR__, 2) . '/../lib/Service/VacationUnitMigrationService.php');
		$this->assertNotFalse($src);
		$this->assertStringContainsString('public function assertIdle(): void', $src);
		$this->assertStringContainsString('public function withIdleShared(callable $fn): mixed', $src);
		$this->assertStringContainsString('LOCK_SHARED', $src);
	}

	public function testAdminCarryoverWriteGuardsMigrateIdle(): void
	{
		$src = file_get_contents(dirname(__DIR__, 2) . '/../lib/Service/AdminUserProfileUpdateService.php');
		$this->assertNotFalse($src);
		$this->assertTrue(
			str_contains($src, 'vacationUnitMigrationService->assertIdle()')
			|| str_contains($src, 'vacationUnitMigrationService->withIdleShared('),
			'Admin carryover writes must guard migrate idle'
		);
	}

	public function testRolloverAndImportGuardMigrateIdle(): void
	{
		$rollover = file_get_contents(dirname(__DIR__, 2) . '/../lib/Service/VacationRolloverService.php');
		$import = file_get_contents(dirname(__DIR__, 2) . '/../lib/Command/ImportVacationBalanceCommand.php');
		$app = file_get_contents(dirname(__DIR__, 2) . '/../lib/AppInfo/Application.php');
		$this->assertNotFalse($rollover);
		$this->assertNotFalse($import);
		$this->assertNotFalse($app);
		$this->assertStringContainsString('withIdleShared($write)', $rollover);
		$this->assertStringContainsString('withIdleShared(', $import);
		$this->assertMatchesRegularExpression(
			'/VacationRolloverService::class.*?VacationUnitService::class.*?VacationUnitMigrationService::class/s',
			$app
		);
		$this->assertMatchesRegularExpression(
			'/ImportVacationBalanceCommand::class.*?VacationUnitService::class.*?VacationUnitMigrationService::class/s',
			$app
		);
	}

	public function testAbsenceHoldsSharedMigrateLockUntilUserLockRelease(): void
	{
		$src = file_get_contents(dirname(__DIR__, 2) . '/../lib/Service/AbsenceService.php');
		$this->assertNotFalse($src);
		$this->assertStringContainsString('LOCK_SHARED', $src);
		$this->assertStringContainsString('heldVacationUnitMigrateSharedLock', $src);
		$this->assertStringContainsString('releaseVacationUnitMigrationSharedLock()', $src);
		$bodies = $this->methodBodies($src);
		$this->assertStringContainsString(
			'releaseVacationUnitMigrationSharedLock()',
			$bodies['releaseUserMutationLock'] ?? ''
		);
	}
}
