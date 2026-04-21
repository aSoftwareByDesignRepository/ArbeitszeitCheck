<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service;

use OCA\ArbeitszeitCheck\Service\DashboardWidgetDataService;
use OCA\ArbeitszeitCheck\Service\PermissionService;
use OCA\ArbeitszeitCheck\Service\TeamResolverService;
use OCA\ArbeitszeitCheck\Service\TimeTrackingService;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;

class DashboardWidgetDataServiceTest extends TestCase {
	public function testEmployeeWidgetDataUsesTimeTrackingStatus(): void {
		$timeTrackingService = $this->createMock(TimeTrackingService::class);
		$timeTrackingService->method('getStatus')->with('u1')->willReturn([
			'status' => 'active',
			'working_today_hours' => 4.5,
			'current_session_duration' => 1234,
		]);

		$service = new DashboardWidgetDataService(
			$timeTrackingService,
			$this->createMock(TeamResolverService::class),
			$this->createMock(PermissionService::class),
			$this->createMock(IUserManager::class)
		);

		$data = $service->getEmployeeWidgetData('u1');
		$this->assertSame('active', $data['status']);
		$this->assertSame(4.5, $data['workingTodayHours']);
	}

	public function testManagerWidgetDataDeniesUnauthorizedUsers(): void {
		$permission = $this->createMock(PermissionService::class);
		$permission->method('canAccessManagerDashboard')->with('u1')->willReturn(false);

		$service = new DashboardWidgetDataService(
			$this->createMock(TimeTrackingService::class),
			$this->createMock(TeamResolverService::class),
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
		$userManager = $this->createMock(IUserManager::class);
		// MAX_ADMIN_USERS = 200 is always used as the search window regardless of the display limit
		$userManager->method('search')->with('', 200, 0)->willReturn([$user]);

		$service = new DashboardWidgetDataService($timeTrackingService, $team, $permission, $userManager);
		$data = $service->getAdminWidgetData('admin1', 5);

		$this->assertTrue($data['authorized']);
		$this->assertSame(1, $data['summary']['total']);
		$this->assertCount(1, $data['users']);
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
			$users[] = $u;
		}

		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('search')->with('', 200, 0)->willReturn($users);

		$service = new DashboardWidgetDataService(
			$timeTrackingService,
			$this->createMock(TeamResolverService::class),
			$permission,
			$userManager
		);

		// Request more than max; display list must be capped at 50
		$data = $service->getAdminWidgetData('admin1', 100);
		$this->assertCount(50, $data['users']);
		// Summary counts all 60 users
		$this->assertSame(60, $data['summary']['total']);
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
		$userManager = $this->createMock(IUserManager::class);
		$userManager->expects($this->once())->method('search')->with('', 200, 0)->willReturn([$user]);

		$service = new DashboardWidgetDataService(
			$timeTrackingService,
			$this->createMock(TeamResolverService::class),
			$permission,
			$userManager
		);

		$data = $service->getAdminWidgetData('admin1', 999);
		$this->assertCount(1, $data['users']);
	}
}
