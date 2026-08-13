<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service;

use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Exception\InvalidEmployeeListFilterException;
use OCA\ArbeitszeitCheck\Service\AdminEmployeeDirectoryService;
use OCA\ArbeitszeitCheck\Service\PermissionService;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class AdminEmployeeDirectoryServiceTest extends TestCase
{
	private IUserManager $userManager;
	private PermissionService $permissionService;
	private \OCA\ArbeitszeitCheck\Db\TimeEntryMapper $timeEntryMapper;
	private AdminEmployeeDirectoryService $service;

	protected function setUp(): void
	{
		parent::setUp();

		$this->userManager = $this->createMock(IUserManager::class);
		$this->permissionService = $this->createMock(PermissionService::class);
		$this->timeEntryMapper = $this->createMock(\OCA\ArbeitszeitCheck\Db\TimeEntryMapper::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);

		$this->service = new AdminEmployeeDirectoryService(
			$this->userManager,
			$this->permissionService,
			$this->timeEntryMapper,
			$l10n,
			$this->createMock(LoggerInterface::class),
		);
	}

	private function makeUser(string $uid, string $displayName): IUser
	{
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		$user->method('getDisplayName')->willReturn($displayName);
		return $user;
	}

	public function testResolveDefaultFilterUsesAppAccessWhenRestricted(): void
	{
		$this->permissionService->method('isAccessRestrictionEnabled')->willReturn(true);
		$this->assertSame(AdminEmployeeDirectoryService::FILTER_APP_ACCESS, $this->service->resolveDefaultFilter());
	}

	public function testResolveDefaultFilterUsesAllWhenOpen(): void
	{
		$this->permissionService->method('isAccessRestrictionEnabled')->willReturn(false);
		$this->assertSame(AdminEmployeeDirectoryService::FILTER_ALL, $this->service->resolveDefaultFilter());
	}

	public function testInvalidFilterThrowsBeforeScan(): void
	{
		$this->permissionService->method('isAccessRestrictionEnabled')->willReturn(false);
		$this->userManager->expects($this->never())->method('search');

		$this->expectException(InvalidEmployeeListFilterException::class);
		$this->service->listUsers('bogus', '', 50, 0);
	}

	public function testNullFilterUsesDefault(): void
	{
		$this->permissionService->method('isAccessRestrictionEnabled')->willReturn(true);
		$allowed = $this->makeUser('alice', 'Alice');
		$denied = $this->makeUser('bob', 'Bob');

		$this->userManager->method('search')->willReturn([$allowed, $denied]);
		$this->permissionService->method('isUserAllowedByAccessGroups')->willReturnCallback(
			static fn (string $uid): bool => $uid === 'alice',
		);

		$result = $this->service->listUsers(null, '', 50, 0);

		$this->assertSame(AdminEmployeeDirectoryService::FILTER_APP_ACCESS, $result['filter']);
		$this->assertCount(1, $result['users']);
		$this->assertSame('alice', $result['users'][0]->getUID());
		$this->assertSame(1, $result['total']);
		$this->assertSame(2, $result['directoryTotal']);
		$this->assertSame(1, $result['hiddenCount']);
	}

	public function testAppAccessFilterExcludesDeniedUsers(): void
	{
		$this->permissionService->method('isAccessRestrictionEnabled')->willReturn(true);
		$users = [
			$this->makeUser('zoe', 'Zoe'),
			$this->makeUser('alice', 'Alice'),
			$this->makeUser('mike', 'Mike'),
		];
		$this->userManager->method('search')->willReturn($users);
		$this->permissionService->method('isUserAllowedByAccessGroups')->willReturnCallback(
			static fn (string $uid): bool => $uid === 'alice' || $uid === 'zoe',
		);

		$result = $this->service->listUsers(AdminEmployeeDirectoryService::FILTER_APP_ACCESS, '', 50, 0);

		$this->assertSame(['alice', 'zoe'], array_map(static fn (IUser $u) => $u->getUID(), $result['users']));
		$this->assertSame(2, $result['total']);
		$this->assertSame(1, $result['hiddenCount']);
	}

	public function testAllFilterIncludesEveryone(): void
	{
		$this->permissionService->method('isAccessRestrictionEnabled')->willReturn(true);
		$users = [
			$this->makeUser('bob', 'Bob'),
			$this->makeUser('alice', 'Alice'),
		];
		$this->userManager->method('search')->willReturn($users);

		$result = $this->service->listUsers(AdminEmployeeDirectoryService::FILTER_ALL, '', 50, 0);

		$this->assertSame(2, $result['total']);
		$this->assertNull($result['hiddenCount']);
		$this->assertNull($result['directoryTotal']);
	}

	public function testPaginationAppliesAfterFilterAndSort(): void
	{
		$this->permissionService->method('isAccessRestrictionEnabled')->willReturn(false);
		$users = [
			$this->makeUser('c', 'Charlie'),
			$this->makeUser('a', 'Alpha'),
			$this->makeUser('b', 'Bravo'),
		];
		$this->userManager->method('search')->willReturn($users);

		$page = $this->service->listUsers(AdminEmployeeDirectoryService::FILTER_ALL, '', 2, 1);

		$this->assertSame(3, $page['total']);
		$this->assertCount(2, $page['users']);
		$this->assertSame(['b', 'c'], array_map(static fn (IUser $u) => $u->getUID(), $page['users']));
		$this->assertSame(1, $page['offset']);
	}

	public function testActiveTodayNarrowsAfterAccessFilter(): void
	{
		$this->permissionService->method('isAccessRestrictionEnabled')->willReturn(false);
		$users = [
			$this->makeUser('alice', 'Alice'),
			$this->makeUser('bob', 'Bob'),
		];
		$this->userManager->method('search')->willReturn($users);
		$this->timeEntryMapper->method('findDistinctUserIdsByDate')->willReturn(['bob']);

		$result = $this->service->listUsers(AdminEmployeeDirectoryService::FILTER_ALL, '', 50, 0, true);

		$this->assertTrue($result['activeToday']);
		$this->assertSame(1, $result['total']);
		$this->assertSame('bob', $result['users'][0]->getUID());
	}

	public function testSearchMergesUidAndDisplayNameMatches(): void
	{
		$this->permissionService->method('isAccessRestrictionEnabled')->willReturn(false);
		$byId = $this->makeUser('uuid-1', 'Someone');
		$byName = $this->makeUser('other', 'Max Mustermann');

		$this->userManager->method('search')->willReturn([$byId]);
		$this->userManager->method('searchDisplayName')->willReturn([$byName]);

		$result = $this->service->listUsers(AdminEmployeeDirectoryService::FILTER_ALL, 'max', 50, 0);

		$this->assertSame(2, $result['total']);
		$uids = array_map(static fn (IUser $u) => $u->getUID(), $result['users']);
		$this->assertContains('uuid-1', $uids);
		$this->assertContains('other', $uids);
	}

	public function testScanTruncationFlagsAndCapsCandidates(): void
	{
		$this->permissionService->method('isAccessRestrictionEnabled')->willReturn(false);
		$cap = Constants::ADMIN_EMPLOYEE_FILTER_MAX_SCAN;
		$users = [];
		for ($i = 0; $i < $cap + 5; $i++) {
			$users[] = $this->makeUser('user' . $i, 'User ' . $i);
		}
		$this->userManager->method('search')->willReturn($users);

		$result = $this->service->listUsers(AdminEmployeeDirectoryService::FILTER_ALL, '', 50, 0);

		$this->assertTrue($result['truncated']);
		$this->assertSame($cap, $result['total']);
	}

	public function testHiddenCountNotAffectedByActiveToday(): void
	{
		$this->permissionService->method('isAccessRestrictionEnabled')->willReturn(true);
		$users = [
			$this->makeUser('alice', 'Alice'),
			$this->makeUser('bob', 'Bob'),
			$this->makeUser('carol', 'Carol'),
		];
		$this->userManager->method('search')->willReturn($users);
		$this->permissionService->method('isUserAllowedByAccessGroups')->willReturnCallback(
			static fn (string $uid): bool => $uid !== 'bob',
		);
		$this->timeEntryMapper->method('findDistinctUserIdsByDate')->willReturn(['alice']);

		$result = $this->service->listUsers(AdminEmployeeDirectoryService::FILTER_APP_ACCESS, '', 50, 0, true);

		$this->assertSame(1, $result['hiddenCount']);
		$this->assertSame(3, $result['directoryTotal']);
		$this->assertSame(1, $result['total']);
	}

	/**
	 * AC-010: NC admin not in allowlist still appears when PermissionService grants access.
	 */
	public function testAppAccessFilterIncludesNcAdminWhenAllowedByPermissionService(): void
	{
		$this->permissionService->method('isAccessRestrictionEnabled')->willReturn(true);
		$ncAdmin = $this->makeUser('ncadmin', 'NC Admin');
		$external = $this->makeUser('extern', 'External User');
		$this->userManager->method('search')->willReturn([$ncAdmin, $external]);
		$this->permissionService->method('isUserAllowedByAccessGroups')->willReturnCallback(
			static fn (string $uid): bool => $uid === 'ncadmin',
		);

		$result = $this->service->listUsers(AdminEmployeeDirectoryService::FILTER_APP_ACCESS, '', 50, 0);

		$this->assertSame(1, $result['total']);
		$this->assertSame('ncadmin', $result['users'][0]->getUID());
	}
}
