<?php

declare(strict_types=1);

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

class VacationUnitMigrationSchemaGateTest extends TestCase
{
	public function testHoursMigrateFailsFastWhenDurationHoursColumnMissing(): void
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

		$qb = $this->createMock(\OCP\DB\QueryBuilder\IQueryBuilder::class);
		$qb->method('select')->willReturnSelf();
		$qb->method('from')->willReturnSelf();
		$qb->method('setMaxResults')->willReturnSelf();
		$qb->method('executeQuery')->willThrowException(
			new \Exception('SQLSTATE[42S22]: Column not found: 1054 Unknown column \'duration_hours\' in \'field list\'')
		);

		$db = $this->createMock(IDBConnection::class);
		$db->method('getQueryBuilder')->willReturn($qb);

		$svc = new VacationUnitMigrationService(
			$config,
			$db,
			new VacationUnitService($config),
			$this->createMock(AbsenceMapper::class),
			$this->createMock(VacationYearBalanceMapper::class),
			$this->createMock(AuditLogMapper::class),
		);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage(Constants::VAC_UNIT_SCHEMA_OUTDATED);
		$svc->migrate(Constants::VACATION_UNIT_HOURS, 8.0, true, 'admin');
	}

	public function testDaysMigrateDoesNotProbeHoursSchema(): void
	{
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static function (string $app, string $key, string $default = ''): string {
				if ($key === Constants::CONFIG_VACATION_UNIT) {
					return Constants::VACATION_UNIT_HOURS;
				}
				return $default;
			}
		);
		$config->expects($this->atLeastOnce())->method('setAppValue');

		$result = $this->createMock(\OCP\DB\IResult::class);
		$result->method('fetch')->willReturn(false);
		$result->method('closeCursor');

		$expr = $this->createMock(\OCP\DB\QueryBuilder\IExpressionBuilder::class);
		$expr->method('eq')->willReturn('eq');

		$qb = $this->createMock(\OCP\DB\QueryBuilder\IQueryBuilder::class);
		$qb->method('select')->willReturnSelf();
		$qb->method('from')->willReturnSelf();
		$qb->method('where')->willReturnSelf();
		$qb->method('expr')->willReturn($expr);
		$qb->method('createNamedParameter')->willReturnArgument(0);
		$qb->method('executeQuery')->willReturn($result);
		$qb->method('executeStatement')->willReturn(0);
		$qb->method('update')->willReturnSelf();
		$qb->method('set')->willReturnSelf();

		$db = $this->createMock(IDBConnection::class);
		$db->expects($this->once())->method('beginTransaction');
		$db->expects($this->once())->method('commit');
		$db->method('getQueryBuilder')->willReturn($qb);

		$audit = $this->createMock(AuditLogMapper::class);
		$audit->expects($this->once())->method('logAction');

		$svc = new VacationUnitMigrationService(
			$config,
			$db,
			new VacationUnitService($config),
			$this->createMock(AbsenceMapper::class),
			$this->createMock(VacationYearBalanceMapper::class),
			$audit,
		);

		$r = $svc->migrate(Constants::VACATION_UNIT_DAYS, 8.0, false, 'admin');
		$this->assertSame(Constants::VACATION_UNIT_DAYS, $r['unit']);
	}
}
