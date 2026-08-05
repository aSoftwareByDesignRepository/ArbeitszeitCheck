<?php

declare(strict_types=1);

/**
 * Reverse hours→days must preserve partial-hour absences (Bestandskunden).
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service;

use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Db\Absence;
use OCA\ArbeitszeitCheck\Db\AbsenceMapper;
use OCA\ArbeitszeitCheck\Db\AuditLogMapper;
use OCA\ArbeitszeitCheck\Db\VacationYearBalanceMapper;
use OCA\ArbeitszeitCheck\Service\VacationUnitMigrationService;
use OCA\ArbeitszeitCheck\Service\VacationUnitService;
use OCP\IConfig;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

class VacationUnitMigrationReverseAbsenceTest extends TestCase
{
	private function mockEmptyRescaleDb(\OCP\DB\IResult $balanceResult, \OCP\DB\IResult $absResult): IDBConnection
	{
		$expr = $this->createMock(\OCP\DB\QueryBuilder\IExpressionBuilder::class);
		$expr->method('eq')->willReturn('eq');
		$expr->method('isNotNull')->willReturn('nn');
		$call = 0;
		$qb = $this->createMock(\OCP\DB\QueryBuilder\IQueryBuilder::class);
		$qb->method('select')->willReturnSelf();
		$qb->method('from')->willReturnSelf();
		$qb->method('where')->willReturnSelf();
		$qb->method('expr')->willReturn($expr);
		$qb->method('createNamedParameter')->willReturnArgument(0);
		$qb->method('executeQuery')->willReturnCallback(
			static function () use (&$call, $balanceResult, $absResult) {
				$call++;
				return $call === 1 ? $balanceResult : $absResult;
			}
		);
		$qb->method('executeStatement')->willReturn(0);
		$qb->method('update')->willReturnSelf();
		$qb->method('set')->willReturnSelf();

		$db = $this->createMock(IDBConnection::class);
		$db->expects($this->once())->method('beginTransaction');
		$db->expects($this->once())->method('commit');
		$db->method('getQueryBuilder')->willReturn($qb);
		return $db;
	}

	public function testReverseHoursToDaysUsesDurationHoursNotCalendarDays(): void
	{
		$stored = [
			Constants::CONFIG_VACATION_UNIT => Constants::VACATION_UNIT_HOURS,
			Constants::CONFIG_VACATION_HOURS_PER_DAY => '8',
			Constants::CONFIG_VACATION_LAST_CONVERT_FACTOR => '8',
			Constants::CONFIG_VACATION_CARRYOVER_MAX_DAYS => '',
		];
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static function (string $app, string $key, string $default = '') use (&$stored): string {
				return $stored[$key] ?? $default;
			}
		);
		$config->method('setAppValue')->willReturnCallback(
			static function (string $app, string $key, string $value) use (&$stored): void {
				$stored[$key] = $value;
			}
		);

		$absence = new Absence();
		$absence->setId(42);
		$absence->setType(Absence::TYPE_VACATION);
		$absence->setDays(1.0);
		$absence->setDurationHours(4.0);

		$updated = null;
		$absences = $this->createMock(AbsenceMapper::class);
		$absences->method('find')->with(42)->willReturn($absence);
		$absences->expects($this->once())->method('update')->willReturnCallback(
			static function (Absence $a) use (&$updated): Absence {
				$updated = $a;
				return $a;
			}
		);

		$balanceResult = $this->createMock(\OCP\DB\IResult::class);
		$balanceResult->method('fetch')->willReturn(false);
		$balanceResult->method('closeCursor');
		$absResult = $this->createMock(\OCP\DB\IResult::class);
		$absResult->method('fetch')->willReturnOnConsecutiveCalls(['id' => 42], false);
		$absResult->method('closeCursor');

		$svc = new VacationUnitMigrationService(
			$config,
			$this->mockEmptyRescaleDb($balanceResult, $absResult),
			new VacationUnitService($config),
			$absences,
			$this->createMock(VacationYearBalanceMapper::class),
			$this->createMock(AuditLogMapper::class),
		);

		$r = $svc->migrate(Constants::VACATION_UNIT_DAYS, 8.0, true, 'admin');
		$this->assertSame(Constants::VACATION_UNIT_DAYS, $r['unit']);
		$this->assertSame(1, $r['converted_absences']);
		$this->assertNotNull($updated);
		$this->assertSame(0.5, $updated->getDays());
		$this->assertNull($updated->getDurationHours());
		$this->assertSame('', $stored[Constants::CONFIG_VACATION_LAST_CONVERT_FACTOR]);
	}

	public function testReverseUsesLastConvertFactorNotTweakedHoursPerDay(): void
	{
		$stored = [
			Constants::CONFIG_VACATION_UNIT => Constants::VACATION_UNIT_HOURS,
			Constants::CONFIG_VACATION_HOURS_PER_DAY => '7.5',
			Constants::CONFIG_VACATION_LAST_CONVERT_FACTOR => '8',
			Constants::CONFIG_VACATION_CARRYOVER_MAX_DAYS => '',
		];
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static function (string $app, string $key, string $default = '') use (&$stored): string {
				return $stored[$key] ?? $default;
			}
		);
		$config->method('setAppValue')->willReturnCallback(
			static function (string $app, string $key, string $value) use (&$stored): void {
				$stored[$key] = $value;
			}
		);

		$absence = new Absence();
		$absence->setId(1);
		$absence->setType(Absence::TYPE_VACATION);
		$absence->setDays(1.0);
		$absence->setDurationHours(8.0);

		$updated = null;
		$absences = $this->createMock(AbsenceMapper::class);
		$absences->method('find')->willReturn($absence);
		$absences->expects($this->once())->method('update')->willReturnCallback(
			static function (Absence $a) use (&$updated): Absence {
				$updated = $a;
				return $a;
			}
		);

		$balanceResult = $this->createMock(\OCP\DB\IResult::class);
		$balanceResult->method('fetch')->willReturn(false);
		$balanceResult->method('closeCursor');
		$absResult = $this->createMock(\OCP\DB\IResult::class);
		$absResult->method('fetch')->willReturnOnConsecutiveCalls(['id' => 1], false);
		$absResult->method('closeCursor');

		$svc = new VacationUnitMigrationService(
			$config,
			$this->mockEmptyRescaleDb($balanceResult, $absResult),
			new VacationUnitService($config),
			$absences,
			$this->createMock(VacationYearBalanceMapper::class),
			$this->createMock(AuditLogMapper::class),
		);

		$svc->migrate(Constants::VACATION_UNIT_DAYS, 7.5, true, 'admin');
		$this->assertNotNull($updated);
		$this->assertSame(1.0, $updated->getDays()); // 8/8, not 8/7.5
		$this->assertSame('7.5', $stored[Constants::CONFIG_VACATION_HOURS_PER_DAY]);
		$this->assertSame(Constants::VACATION_UNIT_DAYS, $stored[Constants::CONFIG_VACATION_UNIT]);
	}
}
