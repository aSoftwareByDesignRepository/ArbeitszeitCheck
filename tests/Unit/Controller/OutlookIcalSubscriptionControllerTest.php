<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Controller;

use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Controller\OutlookIcalSubscriptionController;
use OCA\ArbeitszeitCheck\Db\AbsenceMapper;
use OCA\ArbeitszeitCheck\Db\OutlookIcalSubscriptionTokenMapper;
use OCA\ArbeitszeitCheck\Db\Team;
use OCA\ArbeitszeitCheck\Db\TeamManagerMapper;
use OCA\ArbeitszeitCheck\Exception\OutlookIcalSubscriptionAuthException;
use OCA\ArbeitszeitCheck\Exception\OutlookIcalSubscriptionBadRequestException;
use OCA\ArbeitszeitCheck\Exception\OutlookIcalSubscriptionFeedLimitException;
use OCA\ArbeitszeitCheck\Service\OutlookIcalSubscriptionFeedService;
use OCA\ArbeitszeitCheck\Service\OutlookIcalSubscriptionService;
use OCA\ArbeitszeitCheck\Service\PermissionService;
use OCA\ArbeitszeitCheck\Service\TeamResolverService;
use OCA\ArbeitszeitCheck\Db\TeamMapper;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use PHPUnit\Framework\TestCase;

final class OutlookIcalSubscriptionControllerTest extends TestCase
{
	private function makeController(
		?IRequest $request = null,
		?IUserSession $userSession = null,
		?OutlookIcalSubscriptionService $service = null,
		?PermissionService $permissionService = null,
		?TeamResolverService $teamResolver = null,
		?TeamMapper $teamMapper = null,
		?IUserManager $userManager = null,
		?IURLGenerator $urlGenerator = null,
		?IConfig $config = null,
		?IGroupManager $groupManager = null,
	): OutlookIcalSubscriptionController {
		if ($request === null) {
			$request = $this->createMock(IRequest::class);
		}
		/** @var IRequest&\PHPUnit\Framework\MockObject\MockObject $request */
		$request->method('getServerHost')->willReturn('cloud.example.test');
		if ($userSession === null) {
			$userSession = $this->createMock(IUserSession::class);
		}
		if ($service === null) {
			$service = $this->makeService();
		}
		if ($permissionService === null) {
			$permissionService = $this->createMock(PermissionService::class);
		}
		if ($teamResolver === null) {
			$teamResolver = $this->createMock(TeamResolverService::class);
		}
		if ($teamMapper === null) {
			$teamMapper = $this->createMock(TeamMapper::class);
		}
		if ($userManager === null) {
			$userManager = $this->createMock(IUserManager::class);
		}
		if ($urlGenerator === null) {
			$urlGenerator = $this->createMock(IURLGenerator::class);
		}
		if ($config === null) {
			$config = $this->createMock(IConfig::class);
			$config->method('getAppValue')->willReturnMap([
				['dav', 'webcalAllowLocalAccess', 'no', 'yes'],
			]);
		}
		if ($groupManager === null) {
			$groupManager = $this->createMock(IGroupManager::class);
		}
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);

		return new OutlookIcalSubscriptionController(
			'arbeitszeitcheck',
			$request,
			$userSession,
			$service,
			$permissionService,
			$teamResolver,
			$teamMapper,
			$userManager,
			$urlGenerator,
			$l10n,
			$config,
			$groupManager,
		);
	}

	private function makeService(
		?OutlookIcalSubscriptionTokenMapper $tokenMapper = null,
		?AbsenceMapper $absenceMapper = null,
		?TeamMapper $teamMapper = null,
		?\OCA\ArbeitszeitCheck\Db\TeamMemberMapper $teamMemberMapper = null,
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
			$teamMemberMapper ?? $this->createMock(\OCA\ArbeitszeitCheck\Db\TeamMemberMapper::class),
			$teamManagerMapper ?? $this->createMock(TeamManagerMapper::class),
			$teamResolver ?? $this->createMock(TeamResolverService::class),
			$permissionService ?? $this->createMock(PermissionService::class),
			$userManager ?? $this->createMock(IUserManager::class),
			$config ?? $this->createMock(IConfig::class),
			$db ?? $this->createMock(IDBConnection::class),
			$l10nFactory,
		);
	}

	private function sessionUser(string $uid): IUserSession
	{
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);
		return $session;
	}

	public function testTokenizedFeedReturnsEmptyBodyOnUnauthorizedToken(): void
	{
		$tokenMapper = $this->createMock(OutlookIcalSubscriptionTokenMapper::class);
		$tokenMapper->method('findActiveByTeamAndTokenHash')->willReturn(null);
		$config = $this->createMock(IConfig::class);
		$config->method('getSystemValue')->with('instanceid', '')->willReturn('tenant123');
		$service = $this->makeService(tokenMapper: $tokenMapper, config: $config);

		$controller = $this->makeController(service: $service);
		$response = $controller->tokenizedFeed('bad', 7);

		self::assertInstanceOf(DataDisplayResponse::class, $response);
		self::assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
		self::assertSame('', $response->getData());
	}

	public function testTokenizedFeedReturnsEmptyBodyOnEventCountLimit(): void
	{
		$tokenMapper = $this->createMock(OutlookIcalSubscriptionTokenMapper::class);
		$token = new \OCA\ArbeitszeitCheck\Db\OutlookIcalSubscriptionToken();
		$token->setManagerUserId('manager1');
		$tokenMapper->method('findActiveByTeamAndTokenHash')->willReturn($token);

		$absenceMapper = $this->createMock(AbsenceMapper::class);
		$absenceMapper->method('countByUsersAndDateRange')->willReturn(5001);

		$teamResolver = $this->createMock(TeamResolverService::class);
		$teamResolver->method('useAppTeams')->willReturn(true);

		$teamMapper = $this->createMock(TeamMapper::class);
		$teamMapper->method('getIdsWithDescendants')->with(7)->willReturn([7]);

		$teamMemberMapper = $this->createMock(\OCA\ArbeitszeitCheck\Db\TeamMemberMapper::class);
		$teamMemberMapper->method('getMemberUserIdsByTeamIds')->with([7])->willReturn(['manager1', 'alice']);

		$teamManagerMapper = $this->createMock(TeamManagerMapper::class);
		$teamManagerMapper->method('getManagerUserIdsByTeamIds')->with([7])->willReturn(['manager1']);

		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('get')->willReturnCallback(function (string $uid) {
			$user = $this->createMock(IUser::class);
			$user->method('isEnabled')->willReturn(true);
			return $user;
		});

		$permissionService = $this->createMock(PermissionService::class);
		$permissionService->method('isAdmin')->willReturn(true);

		$config = $this->createMock(IConfig::class);
		$config->method('getSystemValue')->with('instanceid', '')->willReturn('tenant123');

		$service = $this->makeService(
			tokenMapper: $tokenMapper,
			absenceMapper: $absenceMapper,
			teamMapper: $teamMapper,
			teamMemberMapper: $teamMemberMapper,
			teamManagerMapper: $teamManagerMapper,
			teamResolver: $teamResolver,
			permissionService: $permissionService,
			userManager: $userManager,
			config: $config,
		);

		$controller = $this->makeController(service: $service);
		$response = $controller->tokenizedFeed('bad', 7);

		self::assertInstanceOf(DataDisplayResponse::class, $response);
		self::assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		self::assertSame('', $response->getData());

		$headers = method_exists($response, 'getHeaders') ? $response->getHeaders() : [];
		self::assertArrayNotHasKey('X-Outlook-Ical-Error-Code', $headers);
		self::assertArrayNotHasKey('x-outlook-ical-error-code', $headers);
	}

	public function testAuthenticatedFeedReturnsUnauthorizedWithoutSession(): void
	{
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn(null);

		$controller = $this->makeController(userSession: $userSession);
		$response = $controller->authenticatedFeed(9);

		self::assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
		self::assertSame('', $response->getData());
	}

	public function testAdminTeamsRejectsNonAdminUser(): void
	{
		$permissionService = $this->createMock(PermissionService::class);
		$permissionService->method('isAdmin')->with('alice')->willReturn(false);

		$controller = $this->makeController(
			userSession: $this->sessionUser('alice'),
			permissionService: $permissionService,
		);
		$response = $controller->adminTeams('sup', 10);

		self::assertInstanceOf(JSONResponse::class, $response);
		self::assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
		$data = $response->getData();
		self::assertFalse($data['success']);
		self::assertSame('FORBIDDEN', $data['code']);
	}

	public function testAdminTeamsReturnsOrgWideWhenAppTeamsDisabled(): void
	{
		$permissionService = $this->createMock(PermissionService::class);
		$permissionService->method('isAdmin')->with('admin')->willReturn(true);
		$teamResolver = $this->createMock(TeamResolverService::class);
		$teamResolver->method('useAppTeams')->willReturn(false);

		$controller = $this->makeController(
			userSession: $this->sessionUser('admin'),
			permissionService: $permissionService,
			teamResolver: $teamResolver,
		);
		$response = $controller->adminTeams('', 10);

		self::assertSame(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		self::assertTrue($data['success']);
		self::assertCount(1, $data['teams']);
		self::assertSame(0, $data['teams'][0]['id']);
		self::assertTrue($data['teams'][0]['orgWide']);
	}

	public function testAdminTeamsReturnsMatchingTeamsForAppAdmin(): void
	{
		$permissionService = $this->createMock(PermissionService::class);
		$permissionService->method('isAdmin')->with('admin')->willReturn(true);
		$teamResolver = $this->createMock(TeamResolverService::class);
		$teamResolver->method('useAppTeams')->willReturn(true);

		$team = new Team();
		$team->setId(9);
		$team->setName('Support');
		$team->setParentId(null);

		$teamMapper = $this->createMock(TeamMapper::class);
		$teamMapper->method('findAll')->willReturn([$team]);

		$controller = $this->makeController(
			userSession: $this->sessionUser('admin'),
			permissionService: $permissionService,
			teamResolver: $teamResolver,
			teamMapper: $teamMapper,
		);
		$response = $controller->adminTeams('sup', 10);

		self::assertSame(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		self::assertTrue($data['success']);
		self::assertCount(1, $data['teams']);
		self::assertSame(9, $data['teams'][0]['id']);
	}

	public function testAdminTeamsEmptySearchIncludesOrgWideAndConfiguredTeams(): void
	{
		$permissionService = $this->createMock(PermissionService::class);
		$permissionService->method('isAdmin')->with('admin')->willReturn(true);
		$teamResolver = $this->createMock(TeamResolverService::class);
		$teamResolver->method('useAppTeams')->willReturn(true);

		$team = new Team();
		$team->setId(9);
		$team->setName('Support');
		$team->setParentId(null);

		$teamMapper = $this->createMock(TeamMapper::class);
		$teamMapper->method('findAll')->willReturn([$team]);

		$controller = $this->makeController(
			userSession: $this->sessionUser('admin'),
			permissionService: $permissionService,
			teamResolver: $teamResolver,
			teamMapper: $teamMapper,
		);
		$response = $controller->adminTeams('', 10);

		self::assertSame(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		self::assertCount(2, $data['teams']);
		self::assertSame(0, $data['teams'][0]['id']);
		self::assertTrue($data['teams'][0]['orgWide']);
		self::assertSame(9, $data['teams'][1]['id']);
	}

	public function testAdminRotateTokenReturnsAbsoluteFeedUrlAndEventCount(): void
	{
		$permissionService = $this->createMock(PermissionService::class);
		$permissionService->method('isAdmin')->with('admin')->willReturn(true);
		$tokenMapper = $this->createMock(OutlookIcalSubscriptionTokenMapper::class);
		$absenceMapper = $this->createMock(AbsenceMapper::class);
		$teamMapper = $this->createMock(TeamMapper::class);
		$teamMemberMapper = $this->createMock(\OCA\ArbeitszeitCheck\Db\TeamMemberMapper::class);
		$teamManagerMapper = $this->createMock(TeamManagerMapper::class);
		$teamResolver = $this->createMock(TeamResolverService::class);
		$userManager = $this->createMock(IUserManager::class);
		$config = $this->createMock(IConfig::class);
		$db = $this->createMock(IDBConnection::class);

		$team = new Team();
		$team->setId(12);
		$team->setName('Support');
		$teamMapper->method('find')->with(12)->willReturn($team);
		$teamMapper->method('getIdsWithDescendants')->with(12)->willReturn([12]);
		$teamMemberMapper->method('getMemberUserIdsByTeamIds')->with([12])->willReturn(['alice']);
		$teamManagerMapper->method('getTeamIdsForManager')->with('admin')->willReturn([12]);
		$teamManagerMapper->method('getManagerUserIdsByTeamIds')->with([12])->willReturn(['admin']);
		$teamResolver->method('useAppTeams')->willReturn(true);
		$userManager->method('get')->willReturnCallback(function (string $uid) {
			$user = $this->createMock(IUser::class);
			$user->method('isEnabled')->willReturn(true);
			return $user;
		});
		$permissionService->method('isAdmin')->willReturnMap([
			['admin', true],
		]);
		$absenceMapper->method('countByUsersAndDateRange')->willReturn(42);
		$config->method('getSystemValue')->with('instanceid', '')->willReturn('tenant123');
		$db->expects($this->once())->method('beginTransaction');
		$db->expects($this->once())->method('commit');
		$tokenMapper->expects($this->once())->method('findForTeamScope')->with('tenant123', 12)->willReturn(null);
		$tokenMapper->expects($this->once())->method('insert');
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

		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->method('linkToRoute')
			->with('arbeitszeitcheck.outlook_ical_subscription.tokenizedFeed')
			->willReturn('/apps/arbeitszeitcheck/api/outlook-ical/feed.ics');
		$urlGenerator->method('getAbsoluteURL')
			->willReturnCallback(static fn (string $path): string => 'https://cloud.example.test' . $path);

		$controller = $this->makeController(
			userSession: $this->sessionUser('admin'),
			service: $service,
			permissionService: $permissionService,
			urlGenerator: $urlGenerator,
		);
		$response = $controller->adminRotateToken(12, null, 'de');

		self::assertSame(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		self::assertTrue($data['success']);
		self::assertSame(42, $data['eventCount']);
		self::assertStringContainsString('token=', $data['feedUrl']);
		self::assertStringContainsString('teamId=12', $data['feedUrl']);
		self::assertStringContainsString('feed.ics', $data['feedUrl']);
		self::assertStringStartsWith('webcal://', $data['feedWebcalUrl']);
		self::assertStringContainsString('token=', $data['feedWebcalUrl']);
		self::assertStringNotContainsString('start=', $data['feedUrl']);
		self::assertStringNotContainsString('end=', $data['feedUrl']);
		self::assertNotSame('', $data['windowStart']);
		self::assertNotSame('', $data['windowEnd']);
	}

	public function testAdminRotateTokenOrgWideScopeUsesTeamIdZero(): void
	{
		$tokenMapper = $this->createMock(OutlookIcalSubscriptionTokenMapper::class);
		$absenceMapper = $this->createMock(AbsenceMapper::class);
		$permissionService = $this->createMock(PermissionService::class);
		$userManager = $this->createMock(IUserManager::class);
		$config = $this->createMock(IConfig::class);
		$db = $this->createMock(IDBConnection::class);

		$userManager->method('get')->willReturnCallback(function (string $uid) {
			$user = $this->createMock(IUser::class);
			$user->method('isEnabled')->willReturn(true);
			return $user;
		});
		$permissionService->method('isAdmin')->willReturnMap([
			['admin', true],
		]);
		$permissionService->method('listAppAccessUserIds')->willReturn(['admin', 'alice']);
		$absenceMapper->method('countByUsersAndDateRange')->willReturn(5);
		$config->method('getSystemValue')->with('instanceid', '')->willReturn('tenant123');
		$db->expects($this->once())->method('beginTransaction');
		$db->expects($this->once())->method('commit');
		$tokenMapper->expects($this->once())
			->method('findForTeamScope')
			->with('tenant123', Constants::OUTLOOK_ICAL_ORG_WIDE_TEAM_ID)
			->willReturn(null);
		$tokenMapper->expects($this->once())->method('insert');
		$service = $this->makeService(
			$tokenMapper,
			$absenceMapper,
			permissionService: $permissionService,
			userManager: $userManager,
			config: $config,
			db: $db,
		);

		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->method('linkToRoute')
			->with('arbeitszeitcheck.outlook_ical_subscription.tokenizedFeed')
			->willReturn('/apps/arbeitszeitcheck/api/outlook-ical/feed.ics');
		$urlGenerator->method('getAbsoluteURL')
			->willReturnCallback(static fn (string $path): string => 'https://cloud.example.test' . $path);

		$controller = $this->makeController(
			userSession: $this->sessionUser('admin'),
			service: $service,
			permissionService: $permissionService,
			urlGenerator: $urlGenerator,
		);
		$response = $controller->adminRotateToken(Constants::OUTLOOK_ICAL_ORG_WIDE_TEAM_ID, null, 'de');

		self::assertSame(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		self::assertTrue($data['success']);
		self::assertSame(5, $data['eventCount']);
		self::assertStringContainsString('teamId=0', $data['feedUrl']);
	}

	public function testAdminWebcalLocalAccessReportsEnabledForSystemAdmin(): void
	{
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->with('dav', 'webcalAllowLocalAccess', 'no')->willReturn('no');
		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('isAdmin')->with('admin')->willReturn(true);
		$permissionService = $this->createMock(PermissionService::class);
		$permissionService->method('isAdmin')->with('admin')->willReturn(true);

		$controller = $this->makeController(
			userSession: $this->sessionUser('admin'),
			permissionService: $permissionService,
			config: $config,
			groupManager: $groupManager,
		);
		$response = $controller->adminWebcalLocalAccess();

		self::assertSame(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		self::assertTrue($data['success']);
		self::assertFalse($data['enabled']);
		self::assertTrue($data['canEnable']);
	}

	public function testAdminEnableWebcalLocalAccessRequiresSystemAdmin(): void
	{
		$config = $this->createMock(IConfig::class);
		$config->expects($this->never())->method('setAppValue');
		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('isAdmin')->with('delegate')->willReturn(false);
		$permissionService = $this->createMock(PermissionService::class);
		$permissionService->method('isAdmin')->with('delegate')->willReturn(true);

		$controller = $this->makeController(
			userSession: $this->sessionUser('delegate'),
			permissionService: $permissionService,
			config: $config,
			groupManager: $groupManager,
		);
		$response = $controller->adminEnableWebcalLocalAccess();

		self::assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
	}

	public function testAdminEnableWebcalLocalAccessSetsDavConfig(): void
	{
		$config = $this->createMock(IConfig::class);
		$config->expects($this->once())
			->method('setAppValue')
			->with('dav', 'webcalAllowLocalAccess', 'yes');
		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('isAdmin')->with('admin')->willReturn(true);
		$permissionService = $this->createMock(PermissionService::class);
		$permissionService->method('isAdmin')->with('admin')->willReturn(true);

		$controller = $this->makeController(
			userSession: $this->sessionUser('admin'),
			permissionService: $permissionService,
			config: $config,
			groupManager: $groupManager,
		);
		$response = $controller->adminEnableWebcalLocalAccess();

		self::assertSame(Http::STATUS_OK, $response->getStatus());
		self::assertTrue($response->getData()['enabled']);
	}
}
