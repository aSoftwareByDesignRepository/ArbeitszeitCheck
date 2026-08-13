<?php

declare(strict_types=1);

/**
 * Mutation gauntlet for admin employee list access filter.
 * Run: php tests/Mutation/run-admin-employee-access-filter-mutations.php
 */

$root = dirname(__DIR__, 2);
require_once dirname(__DIR__) . '/bootstrap.php';
require_once $root . '/vendor/autoload.php';

use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Exception\InvalidEmployeeListFilterException;
use OCA\ArbeitszeitCheck\Service\AdminEmployeeDirectoryService;
use OCA\ArbeitszeitCheck\Service\PermissionService;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

$failures = 0;

function kill(string $label, callable $assert): void
{
	global $failures;
	try {
		$assert();
		fwrite(STDOUT, "KILL  {$label}\n");
	} catch (Throwable $e) {
		$failures++;
		fwrite(STDOUT, "SURVIVE {$label}: {$e->getMessage()}\n");
	}
}

/**
 * @param list<IUser> $users
 */
function makeService(
	bool $restricted,
	array $users,
	?callable $allowed = null,
): AdminEmployeeDirectoryService {
	$case = new class extends TestCase {
		public function mock(string $class)
		{
			return $this->createMock($class);
		}
	};

	$userManager = $case->mock(IUserManager::class);
	$userManager->method('search')->willReturn($users);
	$userManager->method('searchDisplayName')->willReturn([]);

	$permission = $case->mock(PermissionService::class);
	$permission->method('isAccessRestrictionEnabled')->willReturn($restricted);
	$permission->method('isUserAllowedByAccessGroups')->willReturnCallback(
		$allowed ?? static fn (string $uid): bool => true,
	);

	$timeEntryMapper = $case->mock(\OCA\ArbeitszeitCheck\Db\TimeEntryMapper::class);
	$timeEntryMapper->method('findDistinctUserIdsByDate')->willReturn([]);

	$l10n = $case->mock(IL10N::class);
	$l10n->method('t')->willReturnArgument(0);

	return new AdminEmployeeDirectoryService(
		$userManager,
		$permission,
		$timeEntryMapper,
		$l10n,
		$case->mock(LoggerInterface::class),
	);
}

function makeUser(string $uid, string $displayName): IUser
{
	$case = new class extends TestCase {
		public function mockUser(string $uid, string $displayName): IUser
		{
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn($uid);
			$user->method('getDisplayName')->willReturn($displayName);
			return $user;
		}
	};
	return $case->mockUser($uid, $displayName);
}

kill('invalid-filter-throws', static function (): void {
	$svc = makeService(false, []);
	try {
		$svc->listUsers('evil', '', 50, 0);
		throw new RuntimeException('Expected InvalidEmployeeListFilterException');
	} catch (InvalidEmployeeListFilterException) {
		// expected
	}
});

kill('restricted-default-is-app-access', static function (): void {
	$svc = makeService(true, [makeUser('a', 'A')]);
	$result = $svc->listUsers(null, '', 50, 0);
	if ($result['filter'] !== AdminEmployeeDirectoryService::FILTER_APP_ACCESS) {
		throw new RuntimeException('Default filter must be app_access when restricted');
	}
});

kill('open-default-is-all', static function (): void {
	$svc = makeService(false, [makeUser('a', 'A')]);
	$result = $svc->listUsers(null, '', 50, 0);
	if ($result['filter'] !== AdminEmployeeDirectoryService::FILTER_ALL) {
		throw new RuntimeException('Default filter must be all when open');
	}
});

kill('app-access-hides-denied', static function (): void {
	$svc = makeService(true, [makeUser('in', 'In'), makeUser('out', 'Out')], static fn (string $uid): bool => $uid === 'in');
	$result = $svc->listUsers(AdminEmployeeDirectoryService::FILTER_APP_ACCESS, '', 50, 0);
	if ($result['total'] !== 1 || $result['users'][0]->getUID() !== 'in') {
		throw new RuntimeException('Denied users must not appear in app_access filter');
	}
	if (($result['hiddenCount'] ?? -1) !== 1) {
		throw new RuntimeException('hiddenCount must reflect pre-filter minus post-filter');
	}
});

kill('pagination-after-filter-not-before', static function (): void {
	$users = [makeUser('a', 'A'), makeUser('b', 'B'), makeUser('c', 'C')];
	$svc = makeService(false, $users);
	$page = $svc->listUsers(AdminEmployeeDirectoryService::FILTER_ALL, '', 1, 1);
	if ($page['users'][0]->getUID() !== 'b') {
		throw new RuntimeException('Offset must apply after sort/filter, expected user b on page 2');
	}
});

kill('scan-cap-truncates', static function (): void {
	$cap = Constants::ADMIN_EMPLOYEE_FILTER_MAX_SCAN;
	$users = [];
	for ($i = 0; $i < $cap + 3; $i++) {
		$users[] = makeUser('u' . $i, 'U' . $i);
	}
	$svc = makeService(false, $users);
	$result = $svc->listUsers(AdminEmployeeDirectoryService::FILTER_ALL, '', 50, 0);
	if (!$result['truncated'] || $result['total'] !== $cap) {
		throw new RuntimeException('Scan cap must set truncated and cap total');
	}
});

fwrite(STDOUT, $failures === 0
	? "All admin employee access-filter mutants killed.\n"
	: "{$failures} mutant(s) survived — strengthen tests.\n");

exit($failures === 0 ? 0 : 1);
