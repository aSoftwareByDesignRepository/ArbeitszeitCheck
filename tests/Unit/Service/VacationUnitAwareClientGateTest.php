<?php

declare(strict_types=1);

/**
 * Q8 / NN-08: unaware mobile clients must not receive hour magnitudes.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service;

use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Db\AbsenceMapper;
use OCA\ArbeitszeitCheck\Db\TimeEntryMapper;
use OCA\ArbeitszeitCheck\Service\AbsenceService;
use OCA\ArbeitszeitCheck\Service\DashboardWidgetDataService;
use OCA\ArbeitszeitCheck\Service\OvertimeBankService;
use OCA\ArbeitszeitCheck\Service\OvertimeDisplayService;
use OCA\ArbeitszeitCheck\Service\OvertimeService;
use OCA\ArbeitszeitCheck\Service\PermissionService;
use OCA\ArbeitszeitCheck\Service\ProjectCheckIntegrationService;
use OCA\ArbeitszeitCheck\Service\TeamResolverService;
use OCA\ArbeitszeitCheck\Service\TimeCaptureMethodService;
use OCA\ArbeitszeitCheck\Service\TimeTrackingService;
use OCA\ArbeitszeitCheck\Service\TimeZoneService;
use OCA\ArbeitszeitCheck\Service\VacationHoursDebitService;
use OCP\IConfig;
use OCP\IDateTimeZone;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class VacationUnitAwareClientGateTest extends TestCase
{
	private function service(array $vacationStats): DashboardWidgetDataService
	{
		$timeTracking = $this->createMock(TimeTrackingService::class);
		$timeTracking->method('lawProfile')->willReturn(
			\OCA\ArbeitszeitCheck\Support\LaborLawProfileFactory::profileForCountry('AT')
		);
		$timeTracking->method('getStatus')->willReturn([
			'status' => 'clocked_out',
			'working_today_hours' => 0.0,
			'current_session_duration' => 0,
		]);
		$timeTracking->method('getBreakStatus')->willReturn([]);
		$timeTracking->method('isAutoBreakCalculationEnabled')->willReturn(false);

		$overtime = $this->createMock(OvertimeService::class);
		$overtime->method('getWeeklyOvertime')->willReturn([
			'total_hours_worked' => 10.0,
			'required_hours' => 38.5,
			'weekly_hours' => 38.5,
			'implied_daily_hours' => 7.7,
			'cumulative_balance' => 1.0,
		]);

		$absence = $this->createMock(AbsenceService::class);
		$absence->method('getVacationStats')->willReturn($vacationStats);

		$display = $this->createMock(OvertimeDisplayService::class);
		$display->method('getYearToDateBalanceForTrafficLight')->willReturn(1.0);
		$display->method('buildTrafficLightViewModel')->willReturn(['state' => 'green']);

		$bank = $this->createMock(OvertimeBankService::class);
		$bank->method('isEnabled')->willReturn(false);

		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(static fn ($app, $key, $default) => $default);
		$dateTimeZone = $this->createMock(IDateTimeZone::class);
		$dateTimeZone->method('getTimeZone')->willReturn(new \DateTimeZone('Europe/Vienna'));
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn(null);
		$tz = new TimeZoneService($config, $dateTimeZone, $userSession, new NullLogger());

		$capture = $this->createMock(TimeCaptureMethodService::class);
		$capture->method('getSettings')->willReturn([
			'clockStampingEnabled' => true,
			'manualTimeEntryEnabled' => true,
		]);

		$debit = $this->createMock(VacationHoursDebitService::class);
		$debit->method('snapshotForUser')->willReturn([
			'basis' => 'org_hours_per_day',
			'average_daily' => 8.0,
			'weekday_nets' => null,
			'one_day_hours' => 8.0,
		]);

		return new DashboardWidgetDataService(
			$timeTracking,
			$overtime,
			$display,
			$bank,
			$absence,
			$this->createMock(AbsenceMapper::class),
			$this->createMock(TeamResolverService::class),
			$this->createMock(PermissionService::class),
			$this->createMock(IUserManager::class),
			$tz,
			$capture,
			$this->createMock(ProjectCheckIntegrationService::class),
			$debit,
			$this->createMock(TimeEntryMapper::class),
		);
	}

	public function testUnawareClientGetsDayEquivalentsInHoursMode(): void
	{
		$svc = $this->service([
			'year' => 2026,
			'remaining' => 160.0,
			'entitlement' => 200.0,
			'used' => 40.0,
			'vacation_unit' => Constants::VACATION_UNIT_HOURS,
			'vacation_hours_per_day' => 8.0,
			'carryover_days' => 16.0,
			'carryover_usable' => 8.0,
		]);

		$data = $svc->getEmployeeWidgetData('alice', false);
		$this->assertSame(Constants::VACATION_UNIT_DAYS, $data['vacationUnit']);
		$this->assertTrue($data['vacationClientUpdateRequired']);
		$this->assertSame(20.0, $data['vacationRemaining']);
		$this->assertSame(25.0, $data['vacationEntitlement']);
		$this->assertSame(5.0, $data['vacationUsed']);
		$this->assertSame(2.0, $data['vacationCarryover']);
		$this->assertSame(1.0, $data['vacationCarryoverUsable']);
		// Required companion keys always present (T-MOB-01).
		$this->assertArrayHasKey('displayBalance', $data);
		$this->assertArrayHasKey('weekHoursRequired', $data);
		$this->assertArrayHasKey('vacationRemaining', $data);
	}

	public function testAwareClientKeepsPureHours(): void
	{
		$svc = $this->service([
			'year' => 2026,
			'remaining' => 160.0,
			'entitlement' => 200.0,
			'used' => 40.0,
			'vacation_unit' => Constants::VACATION_UNIT_HOURS,
			'vacation_hours_per_day' => 8.0,
			'carryover_days' => 16.0,
			'carryover_usable' => 8.0,
		]);

		$data = $svc->getEmployeeWidgetData('alice', true);
		$this->assertSame(Constants::VACATION_UNIT_HOURS, $data['vacationUnit']);
		$this->assertFalse($data['vacationClientUpdateRequired']);
		$this->assertSame(160.0, $data['vacationRemaining']);
		$this->assertSame(200.0, $data['vacationEntitlement']);
	}

	public function testDaysModeIgnoresAwarenessFlag(): void
	{
		$svc = $this->service([
			'year' => 2026,
			'remaining' => 12.0,
			'entitlement' => 25.0,
			'used' => 13.0,
			'vacation_unit' => Constants::VACATION_UNIT_DAYS,
			'vacation_hours_per_day' => 8.0,
		]);

		$data = $svc->getEmployeeWidgetData('alice', false);
		$this->assertSame(Constants::VACATION_UNIT_DAYS, $data['vacationUnit']);
		$this->assertFalse($data['vacationClientUpdateRequired']);
		$this->assertSame(12.0, $data['vacationRemaining']);
	}

	/**
	 * T-MOB-02: additive keys stay parseable; premiumSummary is null when disabled.
	 */
	public function testAdditiveKeysPresentAndPremiumNullWhenDisabled(): void
	{
		$svc = $this->service([
			'year' => 2026,
			'remaining' => 12.0,
			'entitlement' => 25.0,
			'used' => 13.0,
			'vacation_unit' => Constants::VACATION_UNIT_DAYS,
			'vacation_hours_per_day' => 8.0,
			'vacation_year_mode' => Constants::VACATION_YEAR_MODE_CALENDAR,
			'vacation_year_label' => '2026',
		]);

		$data = $svc->getEmployeeWidgetData('alice', true);
		foreach ([
			'vacationRemaining',
			'vacationEntitlement',
			'displayBalance',
			'cumulativeBalance',
			'weekHoursRequired',
			'impliedDailyHours',
			'trafficLightState',
			'vacationUnit',
			'vacationYearMode',
			'vacationYearLabel',
			'premiumSummary',
		] as $key) {
			$this->assertArrayHasKey($key, $data, 'T-MOB-02 missing key: ' . $key);
		}
		$this->assertNull($data['premiumSummary']);
		$this->assertSame(Constants::VACATION_YEAR_MODE_CALENDAR, $data['vacationYearMode']);
	}
}
