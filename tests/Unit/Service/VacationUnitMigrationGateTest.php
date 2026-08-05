<?php

declare(strict_types=1);

/**
 * Q8 hard-gate: hours migration requires client confirmation.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service;

use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Db\AbsenceMapper;
use OCA\ArbeitszeitCheck\Db\AuditLogMapper;
use OCA\ArbeitszeitCheck\Db\VacationYearBalanceMapper;
use OCA\ArbeitszeitCheck\Service\VacationUnitMigrationService;
use OCA\ArbeitszeitCheck\Service\VacationUnitService;
use OCP\IConfig;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

class VacationUnitMigrationGateTest extends TestCase
{
	public function testHoursWithoutClientConfirmationThrowsGate(): void
	{
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static function (string $app, string $key, string $default = ''): string {
				if ($key === Constants::CONFIG_VACATION_UNIT) {
					return Constants::VACATION_UNIT_DAYS;
				}
				return $default;
			}
		);
		$db = $this->createMock(IDBConnection::class);
		$db->expects($this->never())->method('beginTransaction');

		$svc = new VacationUnitMigrationService(
			$config,
			$db,
			new VacationUnitService($config),
			$this->createMock(AbsenceMapper::class),
			$this->createMock(VacationYearBalanceMapper::class),
			$this->createMock(AuditLogMapper::class),
		);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage(Constants::VAC_UNIT_CLIENT_GATE);
		$svc->migrate(Constants::VACATION_UNIT_HOURS, 8.0, false, 'admin');
	}

	public function testSameUnitIsNoOp(): void
	{
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturn(Constants::VACATION_UNIT_DAYS);
		$db = $this->createMock(IDBConnection::class);
		$db->expects($this->never())->method('beginTransaction');

		$svc = new VacationUnitMigrationService(
			$config,
			$db,
			new VacationUnitService($config),
			$this->createMock(AbsenceMapper::class),
			$this->createMock(VacationYearBalanceMapper::class),
			$this->createMock(AuditLogMapper::class),
		);

		$r = $svc->migrate(Constants::VACATION_UNIT_DAYS, 8.0, true, 'admin');
		$this->assertSame(Constants::VACATION_UNIT_DAYS, $r['unit']);
		$this->assertSame(0, $r['converted_absences']);
	}
}
