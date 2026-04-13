<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service;

use OCA\ArbeitszeitCheck\Service\PermissionService;
use OCA\ArbeitszeitCheck\Service\TeamResolverService;
use OCA\ArbeitszeitCheck\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\IGroupManager;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PermissionServiceTest extends TestCase
{
	private function createService(IGroupManager $groupManager, TeamResolverService $teamResolver, ?IAppManager $appManager = null, ?IUserManager $userManager = null): PermissionService
	{
		return new PermissionService(
			$groupManager,
			$appManager ?? $this->createMock(IAppManager::class),
			$userManager ?? $this->createMock(IUserManager::class),
			$teamResolver,
			$this->createMock(LoggerInterface::class)
		);
	}

	public function testCanManageEmployeeRejectsSelf(): void
	{
		$groupManager = $this->createMock(IGroupManager::class);
		$teamResolver = $this->createMock(TeamResolverService::class);
		$service = $this->createService($groupManager, $teamResolver);

		$this->assertFalse($service->canManageEmployee('u1', 'u1'));
	}

	public function testCanManageEmployeeAllowsAdmin(): void
	{
		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('isAdmin')->with('admin1')->willReturn(true);
		$teamResolver = $this->createMock(TeamResolverService::class);
		$teamResolver->expects($this->never())->method('canUserManageEmployee');
		$service = $this->createService($groupManager, $teamResolver);

		$this->assertTrue($service->canManageEmployee('admin1', 'employee1'));
	}

	public function testCanManageEmployeeDeniedWhenAppTeamsDisabledForNonAdmin(): void
	{
		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('isAdmin')->willReturn(false);
		$teamResolver = $this->createMock(TeamResolverService::class);
		$teamResolver->method('useAppTeams')->willReturn(false);
		$teamResolver->expects($this->never())->method('canUserManageEmployee');
		$service = $this->createService($groupManager, $teamResolver);

		$this->assertFalse($service->canManageEmployee('manager1', 'employee1'));
	}

	public function testCanManageEmployeeDelegatesToTeamResolver(): void
	{
		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('isAdmin')->willReturn(false);

		$teamResolver = $this->createMock(TeamResolverService::class);
		$teamResolver->method('useAppTeams')->willReturn(true);
		$teamResolver->expects($this->once())
			->method('canUserManageEmployee')
			->with('manager1', 'employee1')
			->willReturn(true);

		$service = $this->createService($groupManager, $teamResolver);

		$this->assertTrue($service->canManageEmployee('manager1', 'employee1'));
	}

	public function testCanAccessManagerDashboardAllowsAdmin(): void
	{
		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('isAdmin')->with('admin1')->willReturn(true);
		$teamResolver = $this->createMock(TeamResolverService::class);
		$teamResolver->expects($this->never())->method('getTeamMemberIds');
		$service = $this->createService($groupManager, $teamResolver);

		$this->assertTrue($service->canAccessManagerDashboard('admin1'));
	}

	public function testCanAccessManagerDashboardRequiresAtLeastOneTeamMember(): void
	{
		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('isAdmin')->willReturn(false);
		$teamResolver = $this->createMock(TeamResolverService::class);
		$teamResolver->method('useAppTeams')->willReturn(true);
		$teamResolver->method('getTeamMemberIds')->willReturnCallback(static fn (string $uid): array => match ($uid) {
			'manager1' => ['employee1'],
			default => [],
		});
		$service = $this->createService($groupManager, $teamResolver);

		$this->assertTrue($service->canAccessManagerDashboard('manager1'));
		$this->assertFalse($service->canAccessManagerDashboard('userNoTeam'));
	}

	public function testCanAccessManagerDashboardDeniedWhenAppTeamsDisabledForNonAdmin(): void
	{
		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('isAdmin')->willReturn(false);
		$teamResolver = $this->createMock(TeamResolverService::class);
		$teamResolver->method('useAppTeams')->willReturn(false);
		$teamResolver->expects($this->never())->method('getTeamMemberIds');
		$service = $this->createService($groupManager, $teamResolver);

		$this->assertFalse($service->canAccessManagerDashboard('manager1'));
	}

	public function testCanViewUserReportSelfAllowedOtherwiseDelegates(): void
	{
		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('isAdmin')->willReturn(false);
		$teamResolver = $this->createMock(TeamResolverService::class);
		$teamResolver->method('useAppTeams')->willReturn(true);
		$teamResolver->method('canUserManageEmployee')->with('manager1', 'employee1')->willReturn(true);
		$service = $this->createService($groupManager, $teamResolver);

		$this->assertTrue($service->canViewUserReport('u1', 'u1'));
		$this->assertTrue($service->canViewUserReport('manager1', 'employee1'));
	}

	public function testCanResolveViolationAdminOrManager(): void
	{
		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('isAdmin')->willReturnCallback(static fn (string $uid): bool => $uid === 'admin1');

		$teamResolver = $this->createMock(TeamResolverService::class);
		$teamResolver->method('useAppTeams')->willReturn(true);
		$teamResolver->method('canUserManageEmployee')->with('manager1', 'employee1')->willReturn(true);
		$service = $this->createService($groupManager, $teamResolver);

		$this->assertTrue($service->canResolveViolation('admin1', 'employee1'));
		$this->assertTrue($service->canResolveViolation('manager1', 'employee1'));
		$this->assertFalse($service->canResolveViolation('employee1', 'employee1'));
	}

	public function testIsUserAllowedByAccessGroupsDelegatesToAppManager(): void
	{
		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('isAdmin')->willReturn(false);
		$appManager = $this->createMock(IAppManager::class);
		$appManager->expects($this->once())->method('isEnabledForUser')->with(Application::APP_ID, $this->anything())->willReturn(true);
		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('get')->with('user1')->willReturn($this->createMock(\OCP\IUser::class));
		$teamResolver = $this->createMock(TeamResolverService::class);
		$service = $this->createService($groupManager, $teamResolver, $appManager, $userManager);

		$this->assertTrue($service->isUserAllowedByAccessGroups('user1'));
	}

	public function testGetAllowedAccessGroupsReadsAppRestriction(): void
	{
		$groupManager = $this->createMock(IGroupManager::class);
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppRestriction')->with(Application::APP_ID)->willReturn(['group_a', 'group_a', 'group_b']);
		$teamResolver = $this->createMock(TeamResolverService::class);
		$service = $this->createService($groupManager, $teamResolver, $appManager, $this->createMock(IUserManager::class));

		$this->assertSame(['group_a', 'group_b'], $service->getAllowedAccessGroups());
	}

	public function testIsUserAllowedByAccessGroupsAlwaysAllowsAdmin(): void
	{
		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('isAdmin')->willReturnCallback(static fn (string $uid): bool => $uid === 'admin1');
		$appManager = $this->createMock(IAppManager::class);
		$appManager->expects($this->never())->method('isEnabledForUser');
		$teamResolver = $this->createMock(TeamResolverService::class);
		$service = $this->createService($groupManager, $teamResolver, $appManager, $this->createMock(IUserManager::class));

		$this->assertTrue($service->isUserAllowedByAccessGroups('admin1'));
	}
}

