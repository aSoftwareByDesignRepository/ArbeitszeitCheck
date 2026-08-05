<?php

declare(strict_types=1);

/**
 * withIdleShared holds LOCK_SHARED for the critical section (anti-TOCTOU).
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service;

use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Db\AbsenceMapper;
use OCA\ArbeitszeitCheck\Db\AuditLogMapper;
use OCA\ArbeitszeitCheck\Db\VacationYearBalanceMapper;
use OCA\ArbeitszeitCheck\Service\DbLockKeys;
use OCA\ArbeitszeitCheck\Service\VacationUnitMigrationService;
use OCA\ArbeitszeitCheck\Service\VacationUnitService;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;
use PHPUnit\Framework\TestCase;

class VacationUnitMigrationWithIdleSharedTest extends TestCase
{
	public function testWithIdleSharedHoldsSharedLockDuringCallback(): void
	{
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturn('');
		$locking = $this->createMock(ILockingProvider::class);
		$held = false;
		$locking->expects($this->once())
			->method('acquireLock')
			->with(DbLockKeys::vacationUnitMigration(), ILockingProvider::LOCK_SHARED, $this->anything())
			->willReturnCallback(static function () use (&$held): void {
				$held = true;
			});
		$locking->expects($this->once())
			->method('releaseLock')
			->with(DbLockKeys::vacationUnitMigration(), ILockingProvider::LOCK_SHARED)
			->willReturnCallback(static function () use (&$held): void {
				$held = false;
			});

		$svc = new VacationUnitMigrationService(
			$config,
			$this->createMock(IDBConnection::class),
			new VacationUnitService($config),
			$this->createMock(AbsenceMapper::class),
			$this->createMock(VacationYearBalanceMapper::class),
			$this->createMock(AuditLogMapper::class),
			null,
			null,
			$locking,
		);

		$result = $svc->withIdleShared(static function () use (&$held) {
			TestCase::assertTrue($held);
			return 42;
		});
		$this->assertSame(42, $result);
		$this->assertFalse($held);
	}

	public function testWithIdleSharedBlocksWhenExclusiveHeld(): void
	{
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturn('');
		$locking = $this->createMock(ILockingProvider::class);
		$locking->method('acquireLock')
			->willThrowException(new LockedException(DbLockKeys::vacationUnitMigration()));

		$svc = new VacationUnitMigrationService(
			$config,
			$this->createMock(IDBConnection::class),
			new VacationUnitService($config),
			$this->createMock(AbsenceMapper::class),
			$this->createMock(VacationYearBalanceMapper::class),
			$this->createMock(AuditLogMapper::class),
			null,
			null,
			$locking,
		);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage(Constants::VAC_UNIT_MIGRATE_IN_PROGRESS);
		$svc->withIdleShared(static fn () => null);
	}

	public function testYearModeLockKeyStableAndShort(): void
	{
		$this->assertSame('azc/vy/mode', DbLockKeys::vacationYearMode());
		$this->assertLessThanOrEqual(64, strlen(DbLockKeys::vacationYearMode()));
	}
}
