<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Db\Absence;
use OCA\ArbeitszeitCheck\Db\AbsenceMapper;
use OCA\ArbeitszeitCheck\Db\OutlookIcalSubscriptionToken;
use OCA\ArbeitszeitCheck\Db\OutlookIcalSubscriptionTokenMapper;
use OCA\ArbeitszeitCheck\Db\Team;
use OCA\ArbeitszeitCheck\Db\TeamManagerMapper;
use OCA\ArbeitszeitCheck\Db\TeamMapper;
use OCA\ArbeitszeitCheck\Db\TeamMemberMapper;
use OCA\ArbeitszeitCheck\Exception\OutlookIcalSubscriptionBadRequestException;
use OCP\IL10N;
use OCA\ArbeitszeitCheck\Service\OutlookIcalSubscriptionFeedService;
use OCA\ArbeitszeitCheck\Service\OutlookIcalSubscriptionService;
use OCA\ArbeitszeitCheck\Service\PermissionService;
use OCA\ArbeitszeitCheck\Service\TeamResolverService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use PHPUnit\Framework\TestCase;

final class OutlookIcalSubscriptionServiceTest extends TestCase
{
	use OutlookIcalFeedServiceTestTrait;

	private function makeEnabledUser(string $uid, string $displayName = ''): IUser
	{
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		$user->method('isEnabled')->willReturn(true);
		if ($displayName !== '') {
			$user->method('getDisplayName')->willReturn($displayName);
		}
		return $user;
	}

	private function makeService(
		?OutlookIcalSubscriptionTokenMapper $tokenMapper = null,
		?AbsenceMapper $absenceMapper = null,
		?TeamMapper $teamMapper = null,
		?TeamMemberMapper $teamMemberMapper = null,
		?TeamManagerMapper $teamManagerMapper = null,
		?TeamResolverService $teamResolver = null,
		?PermissionService $permissionService = null,
		?IUserManager $userManager = null,
		?IConfig $config = null,
		?IDBConnection $db = null,
		?IFactory $l10nFactory = null,
	): OutlookIcalSubscriptionService {
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		if ($l10nFactory === null) {
			$l10nFactory = $this->createMock(IFactory::class);
			$l10nFactory->method('findLanguage')->willReturn('en');
			$l10nFactory->method('get')->willReturn($l10n);
		}

		return new OutlookIcalSubscriptionService(
			$tokenMapper ?? $this->createMock(OutlookIcalSubscriptionTokenMapper::class),
			new OutlookIcalSubscriptionFeedService($l10nFactory),
			$absenceMapper ?? $this->createMock(AbsenceMapper::class),
			$teamMapper ?? $this->createMock(TeamMapper::class),
			$teamMemberMapper ?? $this->createMock(TeamMemberMapper::class),
			$teamManagerMapper ?? $this->createMock(TeamManagerMapper::class),
			$teamResolver ?? $this->createMock(TeamResolverService::class),
			$permissionService ?? $this->createMock(PermissionService::class),
			$userManager ?? $this->createMock(IUserManager::class),
			$config ?? $this->createMock(IConfig::class),
			$db ?? $this->createMock(IDBConnection::class),
			$l10nFactory,
		);
	}

	public function testResolveRollingFeedRangeUsesPastThreeAndFutureTwelveMonths(): void
	{
		$anchor = new DateTimeImmutable('2026-08-19', new DateTimeZone('UTC'));
		$range = OutlookIcalSubscriptionService::resolveRollingFeedRange($anchor);

		self::assertSame('2026-05-19', $range['startYmd']);
		self::assertSame('2027-08-19', $range['endYmd']);
	}

	public function testRollingFeedRangeSpanNeverExceedsSubscriptionMax(): void
	{
		$anchors = [
			new DateTimeImmutable('2024-02-29', new DateTimeZone('UTC')),
			new DateTimeImmutable('2026-12-31', new DateTimeZone('UTC')),
			new DateTimeImmutable('2028-01-01', new DateTimeZone('UTC')),
		];

		foreach ($anchors as $anchor) {
			$range = OutlookIcalSubscriptionService::resolveRollingFeedRange($anchor);
			$spanDays = (int)$range['start']->diff($range['end'])->days + 1;
			self::assertLessThanOrEqual(Constants::MAX_SUBSCRIPTION_DATE_RANGE_DAYS, $spanDays, $anchor->format('Y-m-d'));
		}
	}

	public function testPreviewApprovedAbsenceCountRejectsUnknownTeam(): void
	{
		$teamMapper = $this->createMock(TeamMapper::class);
		$teamMapper->expects($this->once())
			->method('find')
			->with(77)
			->willThrowException(new DoesNotExistException('missing'));

		$service = $this->makeService(teamMapper: $teamMapper);

		$this->expectException(OutlookIcalSubscriptionBadRequestException::class);
		$this->expectExceptionMessage('');

		try {
			$service->previewApprovedAbsenceCount('manager', 77);
		} catch (OutlookIcalSubscriptionBadRequestException $e) {
			self::assertSame(OutlookIcalSubscriptionBadRequestException::ERROR_INVALID_TEAM_SCOPE, $e->errorCode);
			throw $e;
		}
	}

	public function testRotateTokenInsertsHashedTokenWhenScopeEmpty(): void
	{
		$tokenMapper = $this->createMock(OutlookIcalSubscriptionTokenMapper::class);
		$absenceMapper = $this->createMock(AbsenceMapper::class);
		$teamMapper = $this->createMock(TeamMapper::class);
		$teamMemberMapper = $this->createMock(TeamMemberMapper::class);
		$teamManagerMapper = $this->createMock(TeamManagerMapper::class);
		$teamResolver = $this->createMock(TeamResolverService::class);
		$permissionService = $this->createMock(PermissionService::class);
		$userManager = $this->createMock(IUserManager::class);
		$config = $this->createMock(IConfig::class);
		$db = $this->createMock(IDBConnection::class);

		$team = new Team();
		$team->setId(7);
		$team->setName('Support');
		$teamMapper->method('find')->with(7)->willReturn($team);
		$teamMapper->method('getIdsWithDescendants')->with(7)->willReturn([7]);
		$teamMemberMapper->method('getMemberUserIdsByTeamIds')->with([7])->willReturn(['alice']);
		$teamManagerMapper->method('getTeamIdsForManager')->with('manager')->willReturn([7]);
		$teamManagerMapper->method('getManagerUserIdsByTeamIds')->with([7])->willReturn(['manager']);
		$teamResolver->method('useAppTeams')->willReturn(true);
		$permissionService->method('isAdmin')->with('manager')->willReturn(false);
		$userManager->method('get')->willReturnCallback(fn (string $uid) => $this->makeEnabledUser($uid));
		$absenceMapper->method('countByUsersAndDateRange')->willReturn(1);
		$config->method('getSystemValue')->with('instanceid', '')->willReturn('tenant123');
		$db->expects($this->once())->method('beginTransaction');
		$db->expects($this->once())->method('commit');
		$db->expects($this->never())->method('rollBack');
		$tokenMapper->expects($this->once())->method('findForTeamScope')->with('tenant123', 7)->willReturn(null);
		$tokenMapper->expects($this->never())->method('update');
		$tokenMapper->expects($this->once())
			->method('insert')
			->with($this->callback(static function ($entity): bool {
				return $entity->getTenantId() === 'tenant123'
					&& $entity->getManagerUserId() === 'manager'
					&& $entity->getTeamId() === 7
					&& $entity->getFeedLanguageCode() === 'en'
					&& $entity->getTokenHash() !== ''
					&& strlen($entity->getTokenHash()) === 64;
			}));

		$service = $this->makeService(
			$tokenMapper,
			$absenceMapper,
			$teamMapper,
			$teamMemberMapper,
			$teamManagerMapper,
			$teamResolver,
			$permissionService,
			$userManager,
			$config,
			$db,
		);

		$anchor = new DateTimeImmutable('2026-01-15', new DateTimeZone('UTC'));
		$result = $service->rotateToken('manager', 7, 'en', $anchor);

		self::assertSame(1, $result['eventCount']);
		self::assertSame('2025-10-15', $result['windowStart']);
		self::assertSame('2027-01-15', $result['windowEnd']);
		self::assertNotSame('', $result['token']);
		self::assertGreaterThan(20, strlen($result['token']));
		self::assertSame('en', $result['feedLanguageCode']);
	}

	public function testRotateTokenInsertsOrgWideScopeWithTeamIdZero(): void
	{
		$tokenMapper = $this->createMock(OutlookIcalSubscriptionTokenMapper::class);
		$absenceMapper = $this->createMock(AbsenceMapper::class);
		$permissionService = $this->createMock(PermissionService::class);
		$userManager = $this->createMock(IUserManager::class);
		$config = $this->createMock(IConfig::class);
		$db = $this->createMock(IDBConnection::class);

		$permissionService->method('isAdmin')->with('admin')->willReturn(true);
		$permissionService->method('listAppAccessUserIds')->willReturn(['admin', 'alice']);
		$userManager->method('get')->willReturnCallback(fn (string $uid) => $this->makeEnabledUser($uid));
		$absenceMapper->method('countByUsersAndDateRange')->willReturn(0);
		$config->method('getSystemValue')->with('instanceid', '')->willReturn('tenant123');
		$db->expects($this->once())->method('beginTransaction');
		$db->expects($this->once())->method('commit');
		$tokenMapper->expects($this->once())
			->method('findForTeamScope')
			->with('tenant123', Constants::OUTLOOK_ICAL_ORG_WIDE_TEAM_ID)
			->willReturn(null);
		$tokenMapper->expects($this->once())
			->method('insert')
			->with($this->callback(static function ($entity): bool {
				return $entity->getTeamId() === Constants::OUTLOOK_ICAL_ORG_WIDE_TEAM_ID;
			}));

		$service = $this->makeService(
			$tokenMapper,
			$absenceMapper,
			permissionService: $permissionService,
			userManager: $userManager,
			config: $config,
			db: $db,
		);

		$result = $service->rotateToken('admin', Constants::OUTLOOK_ICAL_ORG_WIDE_TEAM_ID, 'de');

		self::assertSame('de', $result['feedLanguageCode']);
		self::assertNotSame('', $result['token']);
	}

	public function testRotateTokenRejectsUnsupportedLanguage(): void
	{
		$permissionService = $this->createMock(PermissionService::class);
		$permissionService->method('isAdmin')->with('admin')->willReturn(true);
		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('get')->with('admin')->willReturn($this->makeEnabledUser('admin'));
		$permissionService->method('listAppAccessUserIds')->willReturn(['admin']);

		$service = $this->makeService(
			permissionService: $permissionService,
			userManager: $userManager,
		);

		$this->expectException(OutlookIcalSubscriptionBadRequestException::class);
		$service->rotateToken('admin', Constants::OUTLOOK_ICAL_ORG_WIDE_TEAM_ID, 'klingon');
	}

	public function testRotateTokenUpdatesExistingScopeRowInPlace(): void
	{
		$tokenMapper = $this->createMock(OutlookIcalSubscriptionTokenMapper::class);
		$absenceMapper = $this->createMock(AbsenceMapper::class);
		$teamMapper = $this->createMock(TeamMapper::class);
		$teamMemberMapper = $this->createMock(TeamMemberMapper::class);
		$teamManagerMapper = $this->createMock(TeamManagerMapper::class);
		$teamResolver = $this->createMock(TeamResolverService::class);
		$permissionService = $this->createMock(PermissionService::class);
		$userManager = $this->createMock(IUserManager::class);
		$config = $this->createMock(IConfig::class);
		$db = $this->createMock(IDBConnection::class);

		$team = new Team();
		$team->setId(7);
		$team->setName('Support');
		$teamMapper->method('find')->with(7)->willReturn($team);
		$teamMapper->method('getIdsWithDescendants')->with(7)->willReturn([7]);
		$teamMemberMapper->method('getMemberUserIdsByTeamIds')->with([7])->willReturn(['alice']);
		$teamManagerMapper->method('getTeamIdsForManager')->with('manager')->willReturn([7]);
		$teamManagerMapper->method('getManagerUserIdsByTeamIds')->with([7])->willReturn(['manager']);
		$teamResolver->method('useAppTeams')->willReturn(true);
		$permissionService->method('isAdmin')->with('manager')->willReturn(false);
		$userManager->method('get')->willReturnCallback(fn (string $uid) => $this->makeEnabledUser($uid));
		$absenceMapper->method('countByUsersAndDateRange')->willReturn(0);
		$config->method('getSystemValue')->with('instanceid', '')->willReturn('tenant123');
		$db->expects($this->once())->method('beginTransaction');
		$db->expects($this->once())->method('commit');
		$db->expects($this->never())->method('rollBack');

		$existing = new OutlookIcalSubscriptionToken();
		$existing->setId(99);
		$existing->setTenantId('tenant123');
		$existing->setManagerUserId('manager');
		$existing->setTeamId(7);
		$existing->setTokenHash(hash('sha256', 'old-token'));
		$existing->setIsActive(1);

		$tokenMapper->expects($this->once())->method('findForTeamScope')->with('tenant123', 7)->willReturn($existing);
		$tokenMapper->expects($this->never())->method('insert');
		$tokenMapper->expects($this->once())
			->method('update')
			->with($this->callback(static function ($entity): bool {
				return $entity->getId() === 99
					&& $entity->getIsActive() === 1
					&& $entity->getRevokedAt() === null
					&& $entity->getTokenHash() !== hash('sha256', 'old-token')
					&& strlen($entity->getTokenHash()) === 64;
			}));

		$service = $this->makeService(
			$tokenMapper,
			$absenceMapper,
			$teamMapper,
			$teamMemberMapper,
			$teamManagerMapper,
			$teamResolver,
			$permissionService,
			$userManager,
			$config,
			$db,
		);

		$result = $service->rotateToken('manager', 7, 'en');

		self::assertSame(0, $result['eventCount']);
		self::assertNotSame('old-token', $result['token']);
	}

	public function testRotateTokenRetriesOnceAfterUniqueConstraintViolation(): void
	{
		$tokenMapper = $this->createMock(OutlookIcalSubscriptionTokenMapper::class);
		$absenceMapper = $this->createMock(AbsenceMapper::class);
		$teamMapper = $this->createMock(TeamMapper::class);
		$teamMemberMapper = $this->createMock(TeamMemberMapper::class);
		$teamManagerMapper = $this->createMock(TeamManagerMapper::class);
		$teamResolver = $this->createMock(TeamResolverService::class);
		$permissionService = $this->createMock(PermissionService::class);
		$userManager = $this->createMock(IUserManager::class);
		$config = $this->createMock(IConfig::class);
		$db = $this->createMock(IDBConnection::class);

		$team = new Team();
		$team->setId(3);
		$team->setName('Ops');
		$teamMapper->method('find')->with(3)->willReturn($team);
		$teamMapper->method('getIdsWithDescendants')->with(3)->willReturn([3]);
		$teamMemberMapper->method('getMemberUserIdsByTeamIds')->with([3])->willReturn([]);
		$teamManagerMapper->method('getTeamIdsForManager')->with('boss')->willReturn([3]);
		$teamResolver->method('useAppTeams')->willReturn(true);
		$permissionService->method('isAdmin')->with('boss')->willReturn(false);
		$userManager->method('get')->with('boss')->willReturn($this->makeEnabledUser('boss'));
		$absenceMapper->method('countByUsersAndDateRange')->willReturn(0);
		$config->method('getSystemValue')->with('instanceid', '')->willReturn('tenant123');

		$db->expects($this->exactly(2))->method('beginTransaction');
		$db->expects($this->once())->method('rollBack');
		$db->expects($this->once())->method('commit');
		$tokenMapper->expects($this->exactly(2))->method('findForTeamScope')->willReturn(null);

		$duplicate = $this->createMock(UniqueConstraintViolationException::class);
		$tokenMapper->expects($this->exactly(2))
			->method('insert')
			->willReturnCallback(function () use ($duplicate) {
				static $attempt = 0;
				$attempt++;
				if ($attempt === 1) {
					throw new \RuntimeException('dup', 0, $duplicate);
				}
				return new OutlookIcalSubscriptionToken();
			});

		$service = $this->makeService(
			$tokenMapper,
			$absenceMapper,
			$teamMapper,
			$teamMemberMapper,
			$teamManagerMapper,
			$teamResolver,
			$permissionService,
			$userManager,
			$config,
			$db,
		);

		$result = $service->rotateToken('boss', 3, 'en');

		self::assertSame(0, $result['eventCount']);
		self::assertNotSame('', $result['token']);
	}

	public function testPreviewApprovedAbsenceCountUsesOrgWideScopeWithoutAppTeams(): void
	{
		$permissionService = $this->createMock(PermissionService::class);
		$permissionService->method('isAdmin')->with('admin')->willReturn(true);
		$permissionService->method('listAppAccessUserIds')->willReturn(['admin', 'alice', 'bob']);

		$absenceMapper = $this->createMock(AbsenceMapper::class);
		$absenceMapper->expects($this->once())
			->method('countByUsersAndDateRange')
			->with(
				$this->callback(static fn (array $members): bool => $members === ['admin', 'alice', 'bob']),
				$this->anything(),
				$this->anything(),
				Absence::STATUS_APPROVED,
				null,
			)
			->willReturn(3);

		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('get')->willReturnCallback(function (string $uid) {
			return $this->makeEnabledUser($uid, $uid === 'alice' ? 'Alice Example' : $uid);
		});

		$teamResolver = $this->createMock(TeamResolverService::class);
		$teamResolver->method('useAppTeams')->willReturn(false);

		$service = $this->makeService(
			absenceMapper: $absenceMapper,
			teamResolver: $teamResolver,
			permissionService: $permissionService,
			userManager: $userManager,
		);

		$result = $service->previewApprovedAbsenceCount('admin', Constants::OUTLOOK_ICAL_ORG_WIDE_TEAM_ID);

		self::assertSame(3, $result['eventCount']);
	}

	public function testTeamScopeIncludesMembersAndManagersForWholeTeamFeed(): void
	{
		$team = new Team();
		$team->setId(7);
		$team->setName('Support');

		$teamMapper = $this->createMock(TeamMapper::class);
		$teamMapper->method('find')->with(7)->willReturn($team);
		$teamMapper->method('getIdsWithDescendants')->with(7)->willReturn([7]);

		$teamMemberMapper = $this->createMock(TeamMemberMapper::class);
		$teamMemberMapper->method('getMemberUserIdsByTeamIds')->with([7])->willReturn(['alice', 'carol']);

		$teamManagerMapper = $this->createMock(TeamManagerMapper::class);
		$teamManagerMapper->method('getTeamIdsForManager')->with('boss')->willReturn([7]);
		$teamManagerMapper->method('getManagerUserIdsByTeamIds')->with([7])->willReturn(['boss']);

		$teamResolver = $this->createMock(TeamResolverService::class);
		$teamResolver->method('useAppTeams')->willReturn(true);

		$permissionService = $this->createMock(PermissionService::class);
		$permissionService->method('isAdmin')->with('boss')->willReturn(false);

		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('get')->willReturnCallback(fn (string $uid) => $this->makeEnabledUser($uid));

		$absenceMapper = $this->createMock(AbsenceMapper::class);
		$absenceMapper->expects($this->once())
			->method('countByUsersAndDateRange')
			->with(
				$this->callback(static fn (array $members): bool => count($members) === 3
					&& in_array('alice', $members, true)
					&& in_array('carol', $members, true)
					&& in_array('boss', $members, true)),
				$this->anything(),
				$this->anything(),
				Absence::STATUS_APPROVED,
				null,
			)
			->willReturn(2);

		$service = $this->makeService(
			absenceMapper: $absenceMapper,
			teamMapper: $teamMapper,
			teamMemberMapper: $teamMemberMapper,
			teamManagerMapper: $teamManagerMapper,
			teamResolver: $teamResolver,
			permissionService: $permissionService,
			userManager: $userManager,
		);

		$result = $service->previewApprovedAbsenceCount('boss', 7);

		self::assertSame(2, $result['eventCount']);
	}

	public function testBuildTokenizedFeedOrgWideIncludesLocalizedEmployeeNameInSummary(): void
	{
		$tokenMapper = $this->createMock(OutlookIcalSubscriptionTokenMapper::class);
		$token = new OutlookIcalSubscriptionToken();
		$token->setManagerUserId('admin');
		$token->setFeedLanguageCode('de');
		$tokenMapper->method('findActiveByTeamAndTokenHash')->willReturn($token);

		$permissionService = $this->createMock(PermissionService::class);
		$permissionService->method('isAdmin')->with('admin')->willReturn(true);
		$permissionService->method('listAppAccessUserIds')->willReturn(['admin', 'alice']);

		$absence = new \OCA\ArbeitszeitCheck\Db\Absence();
		$absence->setId(42);
		$absence->setUserId('alice');
		$absence->setType(\OCA\ArbeitszeitCheck\Db\Absence::TYPE_VACATION);
		$absence->setStartDate(new DateTime('2026-04-01', new DateTimeZone('UTC')));
		$absence->setEndDate(new DateTime('2026-04-02', new DateTimeZone('UTC')));
		$absence->setDays(2.0);
		$absence->setStatus(\OCA\ArbeitszeitCheck\Db\Absence::STATUS_APPROVED);
		$absence->setReason('private');
		$absence->setUpdatedAt(new DateTime('2026-04-01 10:00:00', new DateTimeZone('UTC')));

		$absenceMapper = $this->createMock(AbsenceMapper::class);
		$absenceMapper->method('countByUsersAndDateRange')->willReturn(1);
		$absenceMapper->method('findByUsersAndDateRange')->willReturn([$absence]);

		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('get')->willReturnCallback(function (string $uid) {
			if ($uid === 'admin') {
				return $this->makeEnabledUser('admin');
			}
			if ($uid === 'alice') {
				return $this->makeEnabledUser('alice', 'Alice Example');
			}
			return null;
		});
		$userManager->method('getDisplayName')->willReturn('');

		$l10nFactory = $this->createMock(IFactory::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $text): string => match ($text) {
			'Vacation' => 'Urlaub',
			default => $text,
		});
		$l10nFactory->method('get')->willReturn($l10n);

		$config = $this->createMock(IConfig::class);
		$config->method('getSystemValue')->with('instanceid', '')->willReturn('tenant123');

		$teamResolver = $this->createMock(TeamResolverService::class);
		$teamResolver->method('useAppTeams')->willReturn(false);

		$service = new OutlookIcalSubscriptionService(
			$tokenMapper,
			$this->makeFeedService(['Vacation' => 'Urlaub'], 'de'),
			$absenceMapper,
			$this->createMock(TeamMapper::class),
			$this->createMock(TeamMemberMapper::class),
			$this->createMock(TeamManagerMapper::class),
			$teamResolver,
			$permissionService,
			$userManager,
			$config,
			$this->createMock(IDBConnection::class),
			$l10nFactory,
		);

		$feed = $service->buildTokenizedFeed('token', Constants::OUTLOOK_ICAL_ORG_WIDE_TEAM_ID, 'example.com');

		self::assertStringContainsString('SUMMARY:Alice Example (Urlaub)', $feed);
		self::assertStringNotContainsString('private', $feed);
	}
}
