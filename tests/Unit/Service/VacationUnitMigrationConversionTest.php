<?php

declare(strict_types=1);

/**
 * Conversion integrity for days ↔ hours (Bestandskunden / Q3=A).
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service;

use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Db\AbsenceMapper;
use OCA\ArbeitszeitCheck\Db\AuditLogMapper;
use OCA\ArbeitszeitCheck\Db\OrgVacationDefaultMapper;
use OCA\ArbeitszeitCheck\Db\VacationYearBalanceMapper;
use OCA\ArbeitszeitCheck\Service\VacationProrationService;
use OCA\ArbeitszeitCheck\Service\VacationUnitMigrationService;
use OCA\ArbeitszeitCheck\Service\VacationUnitService;
use OCA\ArbeitszeitCheck\Tests\Unit\Support\SchemaReadyDbMock;
use OCP\IConfig;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

class VacationUnitMigrationConversionTest extends TestCase
{
	use SchemaReadyDbMock;
	public function testOrgDefaultMapperIsNeverUpdatedDuringMigrate(): void
	{
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static function (string $app, string $key, string $default = ''): string {
				if ($key === Constants::CONFIG_VACATION_UNIT) {
					return Constants::VACATION_UNIT_DAYS;
				}
				if ($key === Constants::CONFIG_VACATION_CARRYOVER_MAX_DAYS) {
					return '5';
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
		$expr->method('isNotNull')->willReturn('nn');

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

		$orgMapper = $this->createMock(OrgVacationDefaultMapper::class);
		$orgMapper->expects($this->never())->method('findActiveByDate');
		$orgMapper->expects($this->never())->method('update');

		$balances = $this->createMock(VacationYearBalanceMapper::class);
		$balances->expects($this->never())->method('upsert');

		$audit = $this->createMock(AuditLogMapper::class);
		$audit->expects($this->once())->method('logAction');

		$svc = new VacationUnitMigrationService(
			$config,
			$db,
			new VacationUnitService($config),
			$this->createMock(AbsenceMapper::class),
			$balances,
			$audit,
			$orgMapper,
		);

		$r = $svc->migrate(Constants::VACATION_UNIT_HOURS, 8.0, true, 'admin');
		$this->assertSame(Constants::VACATION_UNIT_HOURS, $r['unit']);
		$this->assertSame(8.0, $r['hours_per_day']);
	}

	public function testCarryoverMaxConfigRescalesWithFactor(): void
	{
		$stored = ['vacation_carryover_max_days' => '5'];
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

		$svc = new VacationUnitMigrationService(
			$config,
			$this->createMock(IDBConnection::class),
			new VacationUnitService($config),
			$this->createMock(AbsenceMapper::class),
			$this->createMock(VacationYearBalanceMapper::class),
			$this->createMock(AuditLogMapper::class),
		);

		$ref = new \ReflectionClass($svc);
		$method = $ref->getMethod('rescaleCarryoverMaxConfig');
		$method->setAccessible(true);
		$method->invoke($svc, 8.0, true);
		$this->assertSame('40', $stored['vacation_carryover_max_days']);
		$method->invoke($svc, 8.0, false);
		$this->assertSame('5', $stored['vacation_carryover_max_days']);
	}

	public function testHoursCeilingAllowsFourHundredHourEntitlement(): void
	{
		$r = VacationProrationService::computeProration(
			2026,
			400.0,
			null,
			null,
			Constants::VACATION_PRORATION_METHOD_TWELFTHS,
			4000.0,
			8.0,
		);
		$this->assertSame(400.0, $r['days']);
		$this->assertFalse($r['prorated']);
	}

	public function testHoursModeStatutoryRoundingUsesHalfDayInHours(): void
	{
		// 200h × 8/12 = 133.333… → day-equivalent 16.666 days → round up to 17 days × 8 = 136h
		$r = VacationProrationService::computeProration(
			2026,
			200.0,
			new \DateTimeImmutable('2026-05-01'),
			null,
			Constants::VACATION_PRORATION_METHOD_TWELFTHS,
			4000.0,
			8.0,
		);
		$this->assertSame(136.0, $r['days']);
		$this->assertTrue($r['prorated']);
	}

	public function testDaysModeStatutoryRoundingUnchanged(): void
	{
		// 30 × 8/12 = 20 exact — no rounding change
		$r = VacationProrationService::computeProration(
			2026,
			30.0,
			new \DateTimeImmutable('2026-05-01'),
			null,
			Constants::VACATION_PRORATION_METHOD_TWELFTHS,
			366.0,
			1.0,
		);
		$this->assertSame(20.0, $r['days']);
	}

	public function testConvertAmountRoundTripHalfDay(): void
	{
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static function (string $app, string $key, string $default = ''): string {
				if ($key === Constants::CONFIG_VACATION_HOURS_PER_DAY) {
					return '8';
				}
				return $default;
			}
		);
		$s = new VacationUnitService($config);
		$this->assertSame(4.0, $s->daysToHours(0.5));
		$this->assertSame(0.5, $s->hoursToDays(4.0));
	}

	public function testSameUnitMigrateUpdatesHoursPerDayOnly(): void
	{
		$stored = [
			Constants::CONFIG_VACATION_UNIT => Constants::VACATION_UNIT_HOURS,
			Constants::CONFIG_VACATION_HOURS_PER_DAY => '8',
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

		$db = $this->createSchemaReadyDbMock();
		$db->expects($this->never())->method('beginTransaction');

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

		$r = $svc->migrate(Constants::VACATION_UNIT_HOURS, 7.5, true, 'admin');
		$this->assertSame(Constants::VACATION_UNIT_HOURS, $r['unit']);
		$this->assertSame(7.5, $r['hours_per_day']);
		$this->assertSame(0, $r['converted_absences']);
		$this->assertSame('7.5', $stored[Constants::CONFIG_VACATION_HOURS_PER_DAY]);
	}
}
