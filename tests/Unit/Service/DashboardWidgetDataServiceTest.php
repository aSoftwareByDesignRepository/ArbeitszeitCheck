<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service;

use OCA\ArbeitszeitCheck\Db\Absence;
use OCA\ArbeitszeitCheck\Db\AbsenceMapper;
use OCA\ArbeitszeitCheck\Service\AbsenceService;
use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Service\DashboardWidgetDataService;
use OCA\ArbeitszeitCheck\Service\OvertimeBankService;
use OCA\ArbeitszeitCheck\Service\OvertimeDisplayService;
use OCA\ArbeitszeitCheck\Service\OvertimeService;
use OCA\ArbeitszeitCheck\Service\PermissionService;
use OCA\ArbeitszeitCheck\Service\TeamResolverService;
use OCA\ArbeitszeitCheck\Service\TimeTrackingService;
use OCA\ArbeitszeitCheck\Service\TimeCaptureMethodService;
use OCA\ArbeitszeitCheck\Service\TimeZoneService;
use OCP\IConfig;
use OCP\IDateTimeZone;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class DashboardWidgetDataServiceTest extends TestCase {
	private function createTimeCaptureMethodService(): TimeCaptureMethodService {
		$service = $this->createMock(TimeCaptureMethodService::class);
		$service->method('getSettings')->willReturn([
			'clockStampingEnabled' => true,
			'manualTimeEntryEnabled' => true,
		]);

		return $service;
	}

	private function createTimeZoneService(): TimeZoneService {
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(fn ($app, $key, $default) => match ($key) {
			'app_timezone' => 'Europe/Berlin',
			default => $default,
		});
		$dateTimeZone = $this->createMock(IDateTimeZone::class);
		$dateTimeZone->method('getTimeZone')->willReturn(new \DateTimeZone('Europe/Berlin'));
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn(null);

		return new TimeZoneService($config, $dateTimeZone, $userSession, new NullLogger());
	}

	private function createService(
		TimeTrackingService $timeTrackingService,
		PermissionService $permissionService,
		IUserManager $userManager,
		?TeamResolverService $teamResolverService = null
	): DashboardWidgetDataService {
		$display = $this->createMock(OvertimeDisplayService::class);
		$display->method('getYearToDateBalanceForTrafficLight')->willReturn(0.0);
		$display->method('buildTrafficLightViewModel')->willReturn(['state' => 'green']);

		$bank = $this->createMock(OvertimeBankService::class);
		$bank->method('isEnabled')->willReturn(false);

		return new DashboardWidgetDataService(
			$timeTrackingService,
			$this->createMock(OvertimeService::class),
			$display,
			$bank,
			$this->createMock(AbsenceService::class),
			$this->createMock(AbsenceMapper::class),
			$teamResolverService ?? $this->createMock(TeamResolverService::class),
			$permissionService,
			$userManager,
			$this->createTimeZoneService(),
			$this->createTimeCaptureMethodService(),
		);
	}

	public function testEmployeeWidgetDataFormatsIsoSessionStartInUserDisplayTz(): void {
		$timeTrackingService = $this->createMock(TimeTrackingService::class);
		$timeTrackingService->method('getStatus')->with('u1')->willReturn([
			'status' => 'active',
			'working_today_hours' => 1.0,
			'current_session_duration' => 60,
			'current_entry' => [
				'startTime' => '2026-01-15T08:30:00+01:00',
			],
		]);
		$timeTrackingService->method('getBreakStatus')->willReturn([]);
		$timeTrackingService->method('isAutoBreakCalculationEnabled')->willReturn(true);

		$overtime = $this->createMock(OvertimeService::class);
		$overtime->method('getWeeklyOvertime')->willReturn([]);

		$absence = $this->createMock(AbsenceService::class);
		$absence->method('getVacationStats')->willReturn(['year' => 2026]);

		$display = $this->createMock(OvertimeDisplayService::class);
		$display->method('getYearToDateBalanceForTrafficLight')->willReturn(2.5);
		$display->method('buildTrafficLightViewModel')->willReturn(['state' => 'green']);
		$bank = $this->createMock(OvertimeBankService::class);
		$bank->method('isEnabled')->willReturn(false);

		$service = new DashboardWidgetDataService(
			$timeTrackingService,
			$overtime,
			$display,
			$bank,
			$absence,
			$this->createMock(AbsenceMapper::class),
			$this->createMock(TeamResolverService::class),
			$this->createMock(PermissionService::class),
			$this->createMock(IUserManager::class),
			$this->createTimeZoneService(),
			$this->createTimeCaptureMethodService(),
		);

		$data = $service->getEmployeeWidgetData('u1');
		$this->assertSame('08:30', $data['sessionStartFormatted']);
		$this->assertTrue($data['autoBreakCalculation']);
	}

	public function testEmployeeStatusSummaryReturnsLeanPayloadWithoutHeavyQueries(): void {
		$timeTrackingService = $this->createMock(TimeTrackingService::class);
		$timeTrackingService->method('getStatus')->with('u1')->willReturn([
			'status' => 'active',
			'working_today_hours' => 3.25,
			'current_session_duration' => 11700,
			'server_now' => '2026-01-15T12:00:00+01:00',
			'server_timezone' => 'Europe/Berlin',
			'current_entry' => [
				'startTime' => '2026-01-15T08:30:00+01:00',
			],
		]);

		// The desklet summary must NOT trigger the expensive overtime / vacation /
		// traffic-light computations that the full widget payload performs.
		$overtime = $this->createMock(OvertimeService::class);
		$overtime->expects($this->never())->method('getWeeklyOvertime');
		$absence = $this->createMock(AbsenceService::class);
		$absence->expects($this->never())->method('getVacationStats');
		$display = $this->createMock(OvertimeDisplayService::class);
		$display->expects($this->never())->method('getYearToDateBalanceForTrafficLight');
		$display->expects($this->never())->method('buildTrafficLightViewModel');
		$timeTrackingService->expects($this->never())->method('getBreakStatus');

		$service = new DashboardWidgetDataService(
			$timeTrackingService,
			$overtime,
			$display,
			$this->createMock(OvertimeBankService::class),
			$absence,
			$this->createMock(AbsenceMapper::class),
			$this->createMock(TeamResolverService::class),
			$this->createMock(PermissionService::class),
			$this->createMock(IUserManager::class),
			$this->createTimeZoneService(),
			$this->createTimeCaptureMethodService(),
		);

		$data = $service->getEmployeeStatusSummary('u1');

		$this->assertSame('active', $data['status']);
		$this->assertSame(3.25, $data['workingTodayHours']);
		$this->assertSame(11700, $data['currentSessionDuration']);
		$this->assertSame('08:30', $data['sessionStartFormatted']);
		$this->assertSame('Europe/Berlin', $data['serverTimezone']);
		$this->assertTrue($data['timeCapture']['clockStampingEnabled']);
		// Heavy fields are intentionally absent from the lean payload.
		$this->assertArrayNotHasKey('cumulativeBalance', $data);
		$this->assertArrayNotHasKey('vacationRemaining', $data);
	}

	public function testEmployeeWidgetDataExposesBreakStartTimeIso(): void {
		$timeTrackingService = $this->createMock(TimeTrackingService::class);
		$timeTrackingService->method('getStatus')->with('u1')->willReturn([
			'status' => 'break',
			'working_today_hours' => 2.0,
			'current_session_duration' => 3600,
			'current_entry' => [
				'breakStartTime' => '2026-01-15T12:00:00+01:00',
			],
		]);
		$timeTrackingService->method('getBreakStatus')->willReturn([]);
		$timeTrackingService->method('isAutoBreakCalculationEnabled')->willReturn(true);

		$overtime = $this->createMock(OvertimeService::class);
		$overtime->method('getWeeklyOvertime')->willReturn([]);
		$absence = $this->createMock(AbsenceService::class);
		$absence->method('getVacationStats')->willReturn(['year' => 2026]);
		$display = $this->createMock(OvertimeDisplayService::class);
		$display->method('getYearToDateBalanceForTrafficLight')->willReturn(0.0);
		$display->method('buildTrafficLightViewModel')->willReturn(['state' => 'green']);
		$bank = $this->createMock(OvertimeBankService::class);
		$bank->method('isEnabled')->willReturn(false);

		$service = new DashboardWidgetDataService(
			$timeTrackingService,
			$overtime,
			$display,
			$bank,
			$absence,
			$this->createMock(AbsenceMapper::class),
			$this->createMock(TeamResolverService::class),
			$this->createMock(PermissionService::class),
			$this->createMock(IUserManager::class),
			$this->createTimeZoneService(),
			$this->createTimeCaptureMethodService(),
		);

		$data = $service->getEmployeeWidgetData('u1');
		$this->assertSame('2026-01-15T12:00:00+01:00', $data['breakStartTime']);
	}

	public function testEmployeeWidgetDataExposesServerClockAnchor(): void {
		$timeTrackingService = $this->createMock(TimeTrackingService::class);
		$timeTrackingService->method('getStatus')->with('u1')->willReturn([
			'status' => 'active',
			'server_now' => '2026-01-15T10:00:00+01:00',
			'server_timezone' => 'Europe/Berlin',
		]);
		$timeTrackingService->method('getBreakStatus')->willReturn([]);
		$timeTrackingService->method('isAutoBreakCalculationEnabled')->willReturn(false);

		$overtime = $this->createMock(OvertimeService::class);
		$overtime->method('getWeeklyOvertime')->willReturn([]);
		$absence = $this->createMock(AbsenceService::class);
		$absence->method('getVacationStats')->willReturn(['year' => 2026]);
		$display = $this->createMock(OvertimeDisplayService::class);
		$display->method('getYearToDateBalanceForTrafficLight')->willReturn(0.0);
		$display->method('buildTrafficLightViewModel')->willReturn(['state' => 'green']);
		$bank = $this->createMock(OvertimeBankService::class);
		$bank->method('isEnabled')->willReturn(false);

		$service = new DashboardWidgetDataService(
			$timeTrackingService,
			$overtime,
			$display,
			$bank,
			$absence,
			$this->createMock(AbsenceMapper::class),
			$this->createMock(TeamResolverService::class),
			$this->createMock(PermissionService::class),
			$this->createMock(IUserManager::class),
			$this->createTimeZoneService(),
			$this->createTimeCaptureMethodService(),
		);

		$data = $service->getEmployeeWidgetData('u1');
		$this->assertSame('2026-01-15T10:00:00+01:00', $data['serverNow']);
		$this->assertSame('Europe/Berlin', $data['serverTimezone']);
		$this->assertFalse($data['autoBreakCalculation']);
	}

	public function testEmployeeWidgetDataUsesTimeTrackingStatus(): void {
		$timeTrackingService = $this->createMock(TimeTrackingService::class);
		$timeTrackingService->method('getStatus')->with('u1')->willReturn([
			'status' => 'active',
			'working_today_hours' => 4.5,
			'current_session_duration' => 1234,
		]);
		$timeTrackingService->method('getBreakStatus')->willReturn([]);
		$timeTrackingService->method('isAutoBreakCalculationEnabled')->willReturn(true);

		$service = $this->createService(
			$timeTrackingService,
			$this->createMock(PermissionService::class),
			$this->createMock(IUserManager::class)
		);

		$data = $service->getEmployeeWidgetData('u1');
		$this->assertSame('active', $data['status']);
		$this->assertSame(4.5, $data['workingTodayHours']);
		$this->assertTrue($data['autoBreakCalculation']);
	}

	public function testManagerWidgetDataDeniesUnauthorizedUsers(): void {
		$permission = $this->createMock(PermissionService::class);
		$permission->method('canAccessManagerDashboard')->with('u1')->willReturn(false);

		$service = $this->createService(
			$this->createMock(TimeTrackingService::class),
			$permission,
			$this->createMock(IUserManager::class)
		);

		$data = $service->getManagerWidgetData('u1');
		$this->assertFalse($data['authorized']);
		$this->assertSame([], $data['members']);
	}

	public function testAdminWidgetDataReturnsSummary(): void {
		$permission = $this->createMock(PermissionService::class);
		$permission->method('isAdmin')->with('admin1')->willReturn(true);

		$team = $this->createMock(TeamResolverService::class);
		$timeTrackingService = $this->createMock(TimeTrackingService::class);
		$timeTrackingService->method('getStatus')->willReturn([
			'status' => 'clocked_out',
			'working_today_hours' => 0.0,
		]);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('u1');
		$user->method('getDisplayName')->willReturn('User One');
		$user->method('isEnabled')->willReturn(true);
		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('search')->with('', Constants::MAX_LIST_LIMIT, 0)->willReturn([$user]);
		$userManager->method('countUsersTotal')->with(0, false)->willReturn(1);

		$service = $this->createService($timeTrackingService, $permission, $userManager, $team);
		$data = $service->getAdminWidgetData('admin1', 5);

		$this->assertTrue($data['authorized']);
		$this->assertSame(1, $data['summary']['total']);
		$this->assertCount(1, $data['users']);
		$this->assertFalse($data['summaryTruncated']);
	}

	public function testAdminWidgetDataCapsDisplayListAtMaxAdminWidgetUsers(): void {
		$permission = $this->createMock(PermissionService::class);
		$permission->method('isAdmin')->willReturn(true);

		$timeTrackingService = $this->createMock(TimeTrackingService::class);
		$timeTrackingService->method('getStatus')->willReturn([
			'status' => 'active',
			'working_today_hours' => 1.0,
		]);

		// Build 60 user mocks — more than MAX_ADMIN_WIDGET_USERS (50)
		$users = [];
		for ($i = 1; $i <= 60; $i++) {
			$u = $this->createMock(IUser::class);
			$u->method('getUID')->willReturn('u' . $i);
			$u->method('getDisplayName')->willReturn('User ' . $i);
			$u->method('isEnabled')->willReturn(true);
			$users[] = $u;
		}

		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('search')->with('', Constants::MAX_LIST_LIMIT, 0)->willReturn($users);
		$userManager->method('countUsersTotal')->with(0, false)->willReturn(60);

		$service = $this->createService(
			$timeTrackingService,
			$permission,
			$userManager
		);

		// Request more than max; display list must be capped at 50
		$data = $service->getAdminWidgetData('admin1', 100);
		$this->assertCount(50, $data['users']);
		// Summary counts all 60 users
		$this->assertSame(60, $data['summary']['total']);
	}

	public function testManagerAbsenceSummaryUsesStorageCalendarToday(): void {
		$permission = $this->createMock(PermissionService::class);
		$permission->method('canAccessManagerDashboard')->willReturn(true);

		$team = $this->createMock(TeamResolverService::class);
		$team->method('getTeamMemberIds')->willReturn(['member1']);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('member1');
		$user->method('getDisplayName')->willReturn('Member');
		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('get')->with('member1')->willReturn($user);

		$timeTrackingService = $this->createMock(TimeTrackingService::class);
		$timeTrackingService->method('getStatus')->willReturn([
			'status' => 'clocked_out',
			'working_today_hours' => 0.0,
		]);

		$absenceMapper = $this->createMock(AbsenceMapper::class);
		$absenceMapper->expects($this->once())
			->method('findByUsersAndDateRange')
			->with(
				['member1'],
				$this->callback(static function (\DateTimeInterface $start): bool {
					return $start->getTimezone()->getName() === 'Europe/Berlin'
						&& $start->format('H:i:s') === '00:00:00';
				}),
				$this->isInstanceOf(\DateTimeInterface::class),
				Absence::STATUS_APPROVED
			)
			->willReturn([]);

		$display = $this->createMock(OvertimeDisplayService::class);
		$bank = $this->createMock(OvertimeBankService::class);
		$service = new DashboardWidgetDataService(
			$timeTrackingService,
			$this->createMock(OvertimeService::class),
			$display,
			$bank,
			$this->createMock(AbsenceService::class),
			$absenceMapper,
			$team,
			$permission,
			$userManager,
			$this->createTimeZoneService(),
			$this->createTimeCaptureMethodService(),
		);
		$service->getManagerWidgetData('mgr1');
	}

	public function testAdminWidgetDataLimitsSearchWindow(): void {
		$permission = $this->createMock(PermissionService::class);
		$permission->method('isAdmin')->willReturn(true);

		$timeTrackingService = $this->createMock(TimeTrackingService::class);
		$timeTrackingService->method('getStatus')->willReturn([
			'status' => 'active',
			'working_today_hours' => 1.0,
		]);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('u1');
		$user->method('getDisplayName')->willReturn('User One');
		$user->method('isEnabled')->willReturn(true);
		$userManager = $this->createMock(IUserManager::class);
		$userManager->expects($this->once())->method('search')->with('', Constants::MAX_LIST_LIMIT, 0)->willReturn([$user]);
		$userManager->method('countUsersTotal')->with(0, false)->willReturn(600);

		$service = $this->createService(
			$timeTrackingService,
			$permission,
			$userManager
		);

		$data = $service->getAdminWidgetData('admin1', 999);
		$this->assertCount(1, $data['users']);
		$this->assertFalse($data['summaryTruncated']);
		$this->assertSame(600, $data['directoryTotal']);
	}

	public function testAdminWidgetDataTruncatedOnlyWhenScanCapHit(): void {
		$permission = $this->createMock(PermissionService::class);
		$permission->method('isAdmin')->willReturn(true);

		$timeTrackingService = $this->createMock(TimeTrackingService::class);
		$timeTrackingService->method('getStatus')->willReturn([
			'status' => 'clocked_out',
			'working_today_hours' => 0.0,
		]);

		$users = [];
		for ($i = 1; $i <= Constants::MAX_LIST_LIMIT; $i++) {
			$u = $this->createMock(IUser::class);
			$u->method('getUID')->willReturn('u' . $i);
			$u->method('getDisplayName')->willReturn('User ' . $i);
			$u->method('isEnabled')->willReturn(true);
			$users[] = $u;
		}

		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('search')->with('', Constants::MAX_LIST_LIMIT, 0)->willReturn($users);
		$userManager->method('countUsersTotal')->with(0, false)->willReturn(600);

		$service = $this->createService($timeTrackingService, $permission, $userManager);
		$data = $service->getAdminWidgetData('admin1', 10);

		$this->assertTrue($data['summaryTruncated']);
		$this->assertSame(Constants::MAX_LIST_LIMIT, $data['summary']['total']);
	}
}
