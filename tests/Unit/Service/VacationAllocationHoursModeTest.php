<?php

declare(strict_types=1);

/**
 * Hours-mode vacation allocation (US-102 AC-102.1).
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service;

use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Db\Absence;
use OCA\ArbeitszeitCheck\Db\AbsenceMapper;
use OCA\ArbeitszeitCheck\Db\UserSettingsMapper;
use OCA\ArbeitszeitCheck\Db\UserWorkingTimeModelMapper;
use OCA\ArbeitszeitCheck\Db\VacationYearBalanceMapper;
use OCA\ArbeitszeitCheck\Service\EntitlementSnapshotService;
use OCA\ArbeitszeitCheck\Service\HolidayService;
use OCA\ArbeitszeitCheck\Service\VacationAllocationService;
use OCA\ArbeitszeitCheck\Service\VacationEntitlementEngine;
use OCA\ArbeitszeitCheck\Service\VacationProrationService;
use OCA\ArbeitszeitCheck\Service\VacationUnitService;
use OCA\ArbeitszeitCheck\Service\VacationYearWindowResolver;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;

class VacationAllocationHoursModeTest extends TestCase
{
	public function testFourHourBookingDebitsPureHoursFromTwoHundred(): void
	{
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static function (string $app, string $key, string $default = ''): string {
				return match ($key) {
					Constants::CONFIG_VACATION_UNIT => Constants::VACATION_UNIT_HOURS,
					Constants::CONFIG_VACATION_HOURS_PER_DAY => '8',
					Constants::CONFIG_VACATION_CARRYOVER_EXPIRY_MONTH => '3',
					Constants::CONFIG_VACATION_CARRYOVER_EXPIRY_DAY => '31',
					Constants::CONFIG_VACATION_CARRYOVER_MAX_DAYS => '',
					Constants::CONFIG_VACATION_YEAR_MODE => Constants::VACATION_YEAR_MODE_CALENDAR,
					default => $default,
				};
			}
		);

		$unit = new VacationUnitService($config);

		$engine = $this->createMock(VacationEntitlementEngine::class);
		$engine->method('computeForDate')->willReturn([
			'days' => 200.0,
			'source' => 'manual',
			'ruleSetId' => null,
			'trace' => [],
		]);

		$proration = $this->createMock(VacationProrationService::class);
		$proration->method('prorateForYear')->willReturnCallback(
			static fn (string $uid, int $year, float $days): array => [
				'days' => $days,
				'prorated' => false,
				'employed_in_year' => true,
				'method' => 'twelfths',
			]
		);

		$balances = $this->createMock(VacationYearBalanceMapper::class);
		$balances->method('getCarryoverAmount')->willReturn(0.0);
		$balances->method('getCarryoverDays')->willReturn(0.0);

		$absences = $this->createMock(AbsenceMapper::class);
		$absences->method('findVacationApprovedOverlappingYear')->willReturn([]);

		$holidays = $this->createMock(HolidayService::class);
		$holidays->method('computeWorkingDaysForUser')->willReturn(1.0);

		$yearResolver = new VacationYearWindowResolver(
			$config,
			$this->createMock(\OCA\ArbeitszeitCheck\Service\UserEmploymentSettingsService::class)
		);

		$svc = new VacationAllocationService(
			$config,
			$absences,
			$this->createMock(UserWorkingTimeModelMapper::class),
			$this->createMock(UserSettingsMapper::class),
			$balances,
			$holidays,
			$engine,
			$this->createMock(EntitlementSnapshotService::class),
			$proration,
			$yearResolver,
			$unit,
		);

		$before = $svc->computeYearAllocation('u1', 2026, null, null, null, new \DateTime('2026-06-15'), null, false);
		$this->assertSame(Constants::VACATION_UNIT_HOURS, $before['vacation_unit']);
		$this->assertSame(200.0, $before['entitlement']);
		$this->assertSame(200.0, $before['total_remaining_for_new_requests']);

		$after = $svc->computeYearAllocation(
			'u1',
			2026,
			null,
			new \DateTime('2026-06-16'),
			new \DateTime('2026-06-16'),
			new \DateTime('2026-06-15'),
			null,
			false,
			4.0
		);
		$this->assertTrue($after['allocation_valid']);
		$this->assertEqualsWithDelta(4.0, $after['used_total'], 0.001);
		$this->assertEqualsWithDelta(196.0, $after['total_remaining_for_new_requests'], 0.001);
	}

	public function testDaysModeUnchangedWhenUnitDays(): void
	{
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static function (string $app, string $key, string $default = ''): string {
				return match ($key) {
					Constants::CONFIG_VACATION_UNIT => Constants::VACATION_UNIT_DAYS,
					Constants::CONFIG_VACATION_CARRYOVER_EXPIRY_MONTH => '3',
					Constants::CONFIG_VACATION_CARRYOVER_EXPIRY_DAY => '31',
					Constants::CONFIG_VACATION_CARRYOVER_MAX_DAYS => '',
					Constants::CONFIG_VACATION_YEAR_MODE => Constants::VACATION_YEAR_MODE_CALENDAR,
					default => $default,
				};
			}
		);
		$unit = new VacationUnitService($config);
		$engine = $this->createMock(VacationEntitlementEngine::class);
		$engine->method('computeForDate')->willReturn([
			'days' => 25.0,
			'source' => 'manual',
			'ruleSetId' => null,
			'trace' => [],
		]);
		$proration = $this->createMock(VacationProrationService::class);
		$proration->method('prorateForYear')->willReturn([
			'days' => 25.0,
			'prorated' => false,
			'employed_in_year' => true,
			'method' => 'twelfths',
		]);
		$balances = $this->createMock(VacationYearBalanceMapper::class);
		$balances->method('getCarryoverAmount')->willReturn(0.0);
		$absences = $this->createMock(AbsenceMapper::class);
		$absences->method('findVacationApprovedOverlappingYear')->willReturn([]);
		$holidays = $this->createMock(HolidayService::class);
		$holidays->method('computeWorkingDaysForUser')->willReturn(1.0);

		$svc = new VacationAllocationService(
			$config,
			$absences,
			$this->createMock(UserWorkingTimeModelMapper::class),
			$this->createMock(UserSettingsMapper::class),
			$balances,
			$holidays,
			$engine,
			$this->createMock(EntitlementSnapshotService::class),
			$proration,
			new VacationYearWindowResolver(
				$config,
				$this->createMock(\OCA\ArbeitszeitCheck\Service\UserEmploymentSettingsService::class)
			),
			$unit,
		);

		$r = $svc->computeYearAllocation(
			'u1',
			2026,
			null,
			new \DateTime('2026-06-16'),
			new \DateTime('2026-06-16'),
			new \DateTime('2026-06-15'),
			null,
			false
		);
		$this->assertSame(Constants::VACATION_UNIT_DAYS, $r['vacation_unit']);
		$this->assertEqualsWithDelta(1.0, $r['used_total_working_days'], 0.001);
		$this->assertEqualsWithDelta(24.0, $r['total_remaining_for_new_requests'], 0.001);
	}
}
