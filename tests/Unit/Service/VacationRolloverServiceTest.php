<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service;

use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Db\AuditLogMapper;
use OCA\ArbeitszeitCheck\Db\VacationRolloverLogMapper;
use OCA\ArbeitszeitCheck\Db\VacationYearBalanceMapper;
use OCA\ArbeitszeitCheck\Service\PermissionService;
use OCA\ArbeitszeitCheck\Service\VacationAllocationService;
use OCA\ArbeitszeitCheck\Service\VacationRolloverService;
use OCP\IConfig;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;

class VacationRolloverServiceTest extends TestCase
{
	public function testProcessSkipsWhenTargetYearAlreadyHasBalance(): void
	{
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnMap([
			['arbeitszeitcheck', Constants::CONFIG_VACATION_ROLLOVER_ENABLED, '1', '1'],
			['arbeitszeitcheck', Constants::CONFIG_VACATION_ROLLOVER_INCLUDE_UNUSED_ANNUAL, '0', '0'],
		]);

		$alloc = $this->createMock(VacationAllocationService::class);
		$alloc->method('getCarryoverExpiryDateForYear')->willReturnCallback(function (int $y) {
			return new \DateTimeImmutable($y . '-03-31');
		});
		$alloc->method('computeYearAllocation')->willReturn([
			'carryover_remaining_after_approved' => 2.0,
			'annual_remaining_after_approved' => 0.0,
		]);
		$alloc->method('applyCapToOpeningBalance')->willReturnCallback(fn (float $d) => $d);

		$balance = $this->createMock(VacationYearBalanceMapper::class);
		$balance->method('getCarryoverDays')->willReturnCallback(function (string $uid, int $year) {
			return $year === 2027 ? 3.0 : 0.0;
		});

		$log = $this->createMock(VacationRolloverLogMapper::class);
		$log->method('existsForUserAndYears')->willReturn(false);

		$users = $this->createMock(IUserManager::class);
		$audit = $this->createMock(AuditLogMapper::class);
		$permissionService = $this->createMock(PermissionService::class);
		$permissionService->method('isUserAllowedByAccessGroups')->willReturn(true);

		$s = new VacationRolloverService(
			$config,
			$alloc,
			$balance,
			$log,
			$users,
			$audit,
			$permissionService
		);

		$r = $s->processUserForFromYear('u1', 2026, false, false, true);
		$this->assertSame('skipped_target_balance', $r['action']);
	}

	public function testAnniversaryModeDoesNotDisableAutomaticRolloverFlag(): void
	{
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnMap([
			['arbeitszeitcheck', Constants::CONFIG_VACATION_ROLLOVER_ENABLED, '1', '1'],
			['arbeitszeitcheck', Constants::CONFIG_VACATION_ROLLOVER_INCLUDE_UNUSED_ANNUAL, '0', '0'],
		]);

		$alloc = $this->createMock(VacationAllocationService::class);
		$alloc->method('isAnniversaryMode')->willReturn(true);

		$s = new VacationRolloverService(
			$config,
			$alloc,
			$this->createMock(VacationYearBalanceMapper::class),
			$this->createMock(VacationRolloverLogMapper::class),
			$this->createMock(IUserManager::class),
			$this->createMock(AuditLogMapper::class),
			$this->createMock(PermissionService::class)
		);

		$this->assertTrue($s->isAutomaticRolloverEnabled());
	}

	public function testHoursModeUpsertWritesCarryoverHoursAndDoesNotClearDaysColumnAsDaysMode(): void
	{
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static function (string $app, string $key, $default = '') {
				$map = [
					Constants::CONFIG_VACATION_ROLLOVER_ENABLED => '1',
					Constants::CONFIG_VACATION_ROLLOVER_INCLUDE_UNUSED_ANNUAL => '0',
					Constants::CONFIG_VACATION_UNIT => Constants::VACATION_UNIT_HOURS,
					Constants::CONFIG_VACATION_HOURS_PER_DAY => '7.7',
					Constants::CONFIG_VACATION_UNIT_MIGRATE_PENDING => '',
				];
				return $map[$key] ?? $default;
			}
		);

		$alloc = $this->createMock(VacationAllocationService::class);
		$alloc->method('getCarryoverExpiryDateForYear')->willReturnCallback(
			static fn (int $y) => new \DateTimeImmutable($y . '-03-31')
		);
		$alloc->method('computeYearAllocation')->willReturn([
			'carryover_remaining_after_approved' => 15.4,
			'annual_remaining_after_approved' => 0.0,
		]);
		$alloc->method('applyCapToOpeningBalance')->willReturnCallback(static fn (float $d) => $d);
		$alloc->method('isAnniversaryMode')->willReturn(false);

		$balance = $this->createMock(VacationYearBalanceMapper::class);
		$balance->method('getCarryoverAmount')->with('u1', 2027, true)->willReturn(0.0);
		$balance->expects($this->once())
			->method('upsert')
			->with('u1', 2027, 15.4, 15.4, false);

		$log = $this->createMock(VacationRolloverLogMapper::class);
		$log->method('existsForUserAndYears')->willReturn(false);
		$log->expects($this->once())->method('insertLog');

		$unit = new \OCA\ArbeitszeitCheck\Service\VacationUnitService($config);
		$locking = $this->createMock(\OCP\Lock\ILockingProvider::class);
		$locking->method('acquireLock');
		$locking->method('releaseLock');
		$migrate = new \OCA\ArbeitszeitCheck\Service\VacationUnitMigrationService(
			$config,
			$this->createMock(\OCP\IDBConnection::class),
			$unit,
			$this->createMock(\OCA\ArbeitszeitCheck\Db\AbsenceMapper::class),
			$balance,
			$this->createMock(AuditLogMapper::class),
			null,
			null,
			$locking,
		);

		$permissionService = $this->createMock(PermissionService::class);
		$permissionService->method('isUserAllowedByAccessGroups')->willReturn(true);

		$s = new VacationRolloverService(
			$config,
			$alloc,
			$balance,
			$log,
			$this->createMock(IUserManager::class),
			$this->createMock(AuditLogMapper::class),
			$permissionService,
			$unit,
			$migrate
		);

		$r = $s->processUserForFromYear('u1', 2026, false, false, true);
		$this->assertSame('applied', $r['action']);
		$this->assertSame(15.4, $r['amount']);
	}

	public function testProcessBlocksWhenVacationUnitMigrationInProgress(): void
	{
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static function (string $app, string $key, $default = '') {
				if ($key === Constants::CONFIG_VACATION_UNIT_MIGRATE_PENDING) {
					return '{"target":"hours"}';
				}
				return '1';
			}
		);
		$alloc = $this->createMock(VacationAllocationService::class);
		$alloc->method('getCarryoverExpiryDateForYear')->willReturn(new \DateTimeImmutable('2026-03-31'));
		$alloc->method('computeYearAllocation')->willReturn([
			'carryover_remaining_after_approved' => 2.0,
			'annual_remaining_after_approved' => 0.0,
		]);
		$alloc->method('applyCapToOpeningBalance')->willReturnCallback(static fn (float $d) => $d);

		$balance = $this->createMock(VacationYearBalanceMapper::class);
		$balance->method('getCarryoverDays')->willReturn(0.0);
		$balance->expects($this->never())->method('upsert');

		$log = $this->createMock(VacationRolloverLogMapper::class);
		$log->method('existsForUserAndYears')->willReturn(false);

		$unit = new \OCA\ArbeitszeitCheck\Service\VacationUnitService($config);
		$migrate = new \OCA\ArbeitszeitCheck\Service\VacationUnitMigrationService(
			$config,
			$this->createMock(\OCP\IDBConnection::class),
			$unit,
			$this->createMock(\OCA\ArbeitszeitCheck\Db\AbsenceMapper::class),
			$balance,
			$this->createMock(AuditLogMapper::class),
		);

		$s = new VacationRolloverService(
			$config,
			$alloc,
			$balance,
			$log,
			$this->createMock(IUserManager::class),
			$this->createMock(AuditLogMapper::class),
			$this->createMock(PermissionService::class),
			$unit,
			$migrate
		);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage(Constants::VAC_UNIT_MIGRATE_IN_PROGRESS);
		$s->processUserForFromYear('u1', 2026, false, false, true);
	}
}
