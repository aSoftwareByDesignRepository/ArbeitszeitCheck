<?php

declare(strict_types=1);

/**
 * Unit tests for AdminController
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\Controller;

use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Controller\AdminController;
use OCA\ArbeitszeitCheck\Db\AuditLogMapper;
use OCA\ArbeitszeitCheck\Db\ComplianceViolationMapper;
use OCA\ArbeitszeitCheck\Db\ComplianceViolation;
use OCA\ArbeitszeitCheck\Db\TeamMapper;
use OCA\ArbeitszeitCheck\Db\TeamMemberMapper;
use OCA\ArbeitszeitCheck\Db\TeamManagerMapper;
use OCA\ArbeitszeitCheck\Db\TimeEntryMapper;
use OCA\ArbeitszeitCheck\Db\UserSettingsMapper;
use OCA\ArbeitszeitCheck\Db\UserWorkingTimeModel;
use OCA\ArbeitszeitCheck\Db\UserWorkingTimeModelMapper;
use OCA\ArbeitszeitCheck\Db\WorkingTimeModelMapper;
use OCA\ArbeitszeitCheck\Db\WorkingTimeModel;
use OCA\ArbeitszeitCheck\Db\HolidayMapper;
use OCA\ArbeitszeitCheck\Db\AuditLog;
use OCA\ArbeitszeitCheck\Db\TariffRuleModuleMapper;
use OCA\ArbeitszeitCheck\Db\TariffRuleSet;
use OCA\ArbeitszeitCheck\Db\TariffRuleSetMapper;
use OCA\ArbeitszeitCheck\Db\UserVacationPolicyAssignmentMapper;
use OCA\ArbeitszeitCheck\Service\CSPService;
use OCA\ArbeitszeitCheck\Service\HolidayService;
use OCA\ArbeitszeitCheck\Service\VacationEntitlementEngine;
use OCP\App\IAppManager;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IAppConfig;
use OCP\IRequest;
use OCP\IL10N;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;

/**
 * Class AdminControllerTest
 */
class AdminControllerTest extends TestCase
{
	/** @var AdminController */
	private $controller;

	/** @var TimeEntryMapper|\PHPUnit\Framework\MockObject\MockObject */
	private $timeEntryMapper;

	/** @var ComplianceViolationMapper|\PHPUnit\Framework\MockObject\MockObject */
	private $violationMapper;

	/** @var UserWorkingTimeModelMapper|\PHPUnit\Framework\MockObject\MockObject */
	private $userWorkingTimeModelMapper;

	/** @var WorkingTimeModelMapper|\PHPUnit\Framework\MockObject\MockObject */
	private $workingTimeModelMapper;

	/** @var AuditLogMapper|\PHPUnit\Framework\MockObject\MockObject */
	private $auditLogMapper;

	/** @var IUserManager|\PHPUnit\Framework\MockObject\MockObject */
	private $userManager;

	/** @var IAppConfig|\PHPUnit\Framework\MockObject\MockObject */
	private $appConfig;

	/** @var TariffRuleSetMapper|\PHPUnit\Framework\MockObject\MockObject */
	private $tariffRuleSetMapper;

	/** @var VacationEntitlementEngine|\PHPUnit\Framework\MockObject\MockObject */
	private $vacationEntitlementEngine;

	/** @var IRequest|\PHPUnit\Framework\MockObject\MockObject */
	private $request;
	/** @var IGroupManager|\PHPUnit\Framework\MockObject\MockObject */
	private $groupManager;
	/** @var IAppManager|\PHPUnit\Framework\MockObject\MockObject */
	private $appManager;

	protected function setUp(): void
	{
		parent::setUp();

		$this->timeEntryMapper = $this->createMock(TimeEntryMapper::class);
		$this->violationMapper = $this->createMock(ComplianceViolationMapper::class);
		$this->userWorkingTimeModelMapper = $this->createMock(UserWorkingTimeModelMapper::class);
		$this->workingTimeModelMapper = $this->createMock(WorkingTimeModelMapper::class);
		$this->auditLogMapper = $this->createMock(AuditLogMapper::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->request = $this->createMock(IRequest::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->groupManager->method('search')->willReturn([]);
		$this->appManager = $this->createMock(IAppManager::class);
		$teamMapper = $this->createMock(TeamMapper::class);
		$teamMemberMapper = $this->createMock(TeamMemberMapper::class);
		$teamManagerMapper = $this->createMock(TeamManagerMapper::class);
		$userSettingsMapper = $this->createMock(UserSettingsMapper::class);
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn(null);
		$cspService = $this->createMock(CSPService::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(fn ($s, $p = []) => empty($p) ? $s : vsprintf($s, $p));
		$urlGenerator = $this->createMock(IURLGenerator::class);
		$holidayMapper = $this->createMock(HolidayMapper::class);
		$holidayCalendarService = $this->createMock(HolidayService::class);

		$vacationYearBalanceMapper = $this->createMock(\OCA\ArbeitszeitCheck\Db\VacationYearBalanceMapper::class);
		$vacationAllocationService = $this->createMock(\OCA\ArbeitszeitCheck\Service\VacationAllocationService::class);
		$vacationAllocationService->method('applyCapToOpeningBalance')->willReturnCallback(fn (float $d) => $d);
		$this->tariffRuleSetMapper = $this->createMock(TariffRuleSetMapper::class);
		$tariffRuleModuleMapper = $this->createMock(TariffRuleModuleMapper::class);
		$userVacationPolicyAssignmentMapper = $this->createMock(UserVacationPolicyAssignmentMapper::class);
		$this->vacationEntitlementEngine = $this->createMock(VacationEntitlementEngine::class);
		$this->vacationEntitlementEngine->method('computeForDate')->willReturn([
			'days' => 25.0,
			'source' => 'manual',
			'ruleSetId' => null,
			'trace' => [],
		]);

		$this->controller = new AdminController(
			'arbeitszeitcheck',
			$this->request,
			$this->timeEntryMapper,
			$this->violationMapper,
			$this->userWorkingTimeModelMapper,
			$this->workingTimeModelMapper,
			$this->auditLogMapper,
			$this->userManager,
			$this->appConfig,
			$userSettingsMapper,
			$teamMapper,
			$teamMemberMapper,
			$teamManagerMapper,
			$this->groupManager,
			$this->appManager,
			$userSession,
			$cspService,
			$l10n,
			$urlGenerator,
			$holidayMapper,
			$holidayCalendarService,
			$vacationYearBalanceMapper,
			$vacationAllocationService,
			$this->tariffRuleSetMapper,
			$tariffRuleModuleMapper,
			$userVacationPolicyAssignmentMapper,
			$this->vacationEntitlementEngine
		);
	}

	/**
	 * Test dashboard returns template
	 */
	public function testDashboardReturnsTemplate(): void
	{
		$response = $this->controller->dashboard();

		$this->assertInstanceOf(TemplateResponse::class, $response);
	}

	/**
	 * Test users returns template
	 */
	public function testUsersReturnsTemplate(): void
	{
		$response = $this->controller->users();

		$this->assertInstanceOf(TemplateResponse::class, $response);
	}

	/**
	 * Test settings returns template
	 */
	public function testSettingsReturnsTemplate(): void
	{
		$response = $this->controller->settings();

		$this->assertInstanceOf(TemplateResponse::class, $response);
	}

	public function testNotificationsReturnsTemplate(): void
	{
		$this->appConfig->method('getAppValueString')
			->willReturnCallback(fn (string $key, string $default = '') => $default);
		$response = $this->controller->notifications();
		$this->assertInstanceOf(TemplateResponse::class, $response);
	}

	/**
	 * Test workingTimeModels returns template
	 */
	public function testWorkingTimeModelsReturnsTemplate(): void
	{
		$response = $this->controller->workingTimeModels();

		$this->assertInstanceOf(TemplateResponse::class, $response);
	}

	/**
	 * Test auditLog returns template
	 */
	public function testAuditLogReturnsTemplate(): void
	{
		$response = $this->controller->auditLog();

		$this->assertInstanceOf(TemplateResponse::class, $response);
	}

	/**
	 * Test getAdminSettings returns settings
	 */
	public function testGetAdminSettingsReturnsSettings(): void
	{
		$this->appConfig->method('getAppValueString')
			->willReturnCallback(function (string $key, string $default = '') {
				$values = [
					'auto_compliance_check' => '1',
					'enable_violation_notifications' => '1',
					'missing_clock_in_reminders_enabled' => '1',
					'export_midnight_split_enabled' => '1',
					'max_daily_hours' => '10',
					'min_rest_period' => '11',
					'german_state' => 'NW',
					'retention_period' => '2',
					'default_working_hours' => '8'
				];
				return $values[$key] ?? $default;
			});
		$this->appManager->method('getAppRestriction')->with('arbeitszeitcheck')->willReturn([]);

		$response = $this->controller->getAdminSettings();
		$data = $response->getData();

		if (!($data['success'] ?? false)) {
			$this->fail('Response: ' . json_encode($data));
		}
		$this->assertArrayHasKey('settings', $data);
		$this->assertTrue($data['settings']['autoComplianceCheck']);
		$this->assertTrue($data['settings']['missingClockInRemindersEnabled']);
		$this->assertEquals(10.0, $data['settings']['maxDailyHours']);
		$this->assertArrayHasKey('accessAllowedGroups', $data['settings']);
	}

	public function testGetNotificationSettingsReturnsNormalizedPayload(): void
	{
		$this->appConfig->method('getAppValueString')
			->willReturnCallback(function (string $key, string $default = '') {
				if ($key === Constants::CONFIG_HR_NOTIFICATIONS_ENABLED) {
					return '1';
				}
				if ($key === Constants::CONFIG_HR_NOTIFICATION_RECIPIENTS) {
					return 'hr@example.com, HR@example.com,invalid';
				}
				if ($key === Constants::CONFIG_HR_NOTIFICATION_MATRIX_V1) {
					return '{"vacation":{"request_created":true}}';
				}
				return $default;
			});

		$response = $this->controller->getNotificationSettings();
		$data = $response->getData();

		$this->assertTrue($data['success'], 'Response: ' . json_encode($data));
		$this->assertTrue($data['settings']['enabled']);
		$this->assertSame('hr@example.com', $data['settings']['recipients']);
		$this->assertTrue($data['settings']['matrix']['vacation']['request_created']);
		$this->assertFalse($data['settings']['matrix']['vacation']['manager_rejected']);
	}

	public function testUpdateNotificationSettingsRejectsInvalidRecipient(): void
	{
		$this->request->method('getParams')->willReturn([
			'enabled' => true,
			'recipients' => ['ok@example.com', 'bad_mail'],
			'matrix' => ['vacation' => ['request_created' => true]],
		]);

		$response = $this->controller->updateNotificationSettings();
		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$data = $response->getData();
		$this->assertFalse($data['success']);
	}

	public function testUpdateNotificationSettingsRejectsEnabledWithoutRecipients(): void
	{
		$this->request->method('getParams')->willReturn([
			'enabled' => true,
			'recipients' => [],
			'matrix' => ['vacation' => ['request_created' => true]],
		]);

		$response = $this->controller->updateNotificationSettings();
		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$data = $response->getData();
		$this->assertFalse($data['success']);
	}

	public function testUpdateNotificationSettingsAcceptsMatrixJsonString(): void
	{
		$this->request->method('getParams')->willReturn([
			'enabled' => true,
			'recipients' => ['hr@example.com'],
			'matrix' => '{"vacation":{"request_created":true}}',
		]);

		$captured = [];
		$this->appConfig->method('setAppValueString')
			->willReturnCallback(function ($key, $value, $lazy = false, $sensitive = false) use (&$captured): bool {
				unset($lazy, $sensitive);
				$captured[(string)$key] = (string)$value;
				return true;
			});
		$this->appConfig->method('getAppValueString')
			->willReturnCallback(function ($key, $default = '') use (&$captured): string {
				$key = (string)$key;
				return $captured[$key] ?? (string)$default;
			});

		$response = $this->controller->updateNotificationSettings();
		$data = $response->getData();
		$this->assertTrue($data['success']);
		$matrix = json_decode($captured[Constants::CONFIG_HR_NOTIFICATION_MATRIX_V1], true);
		$this->assertTrue($matrix['vacation']['request_created']);
	}

	public function testUpdateNotificationSettingsPersistsNormalizedValues(): void
	{
		$this->request->method('getParams')->willReturn([
			'enabled' => 'true',
			'recipients' => ['HR@example.com', 'hr@example.com', 'ops@example.com'],
			'matrix' => [
				'vacation' => ['request_created' => true, 'manager_approved' => '1'],
				'invalid_type' => ['request_created' => true],
			],
		]);

		$captured = [];
		$this->appConfig->method('setAppValueString')
			->willReturnCallback(function ($key, $value, $lazy = false, $sensitive = false) use (&$captured): bool {
				unset($lazy, $sensitive);
				$captured[(string)$key] = (string)$value;
				return true;
			});
		$this->appConfig->method('getAppValueString')
			->willReturnCallback(function ($key, $default = '') use (&$captured): string {
				$key = (string)$key;
				return $captured[$key] ?? (string)$default;
			});

		$response = $this->controller->updateNotificationSettings();
		$data = $response->getData();

		$this->assertTrue($data['success'], 'Response: ' . json_encode($data));
		$this->assertArrayHasKey(Constants::CONFIG_HR_NOTIFICATIONS_ENABLED, $captured);
		$this->assertArrayHasKey(Constants::CONFIG_HR_NOTIFICATION_RECIPIENTS, $captured);
		$this->assertArrayHasKey(Constants::CONFIG_HR_NOTIFICATION_MATRIX_V1, $captured);
		$this->assertSame('1', $captured[Constants::CONFIG_HR_NOTIFICATIONS_ENABLED]);
		$this->assertSame('hr@example.com,ops@example.com', $captured[Constants::CONFIG_HR_NOTIFICATION_RECIPIENTS]);
		$matrix = json_decode($captured[Constants::CONFIG_HR_NOTIFICATION_MATRIX_V1], true);
		$this->assertTrue($matrix['vacation']['request_created']);
		$this->assertTrue($matrix['vacation']['manager_approved']);
		$this->assertArrayNotHasKey('invalid_type', $matrix);
	}

	public function testGetAdminSettingsReturnsConfiguredAppAdminsAndAvailableList(): void
	{
		$this->appConfig->method('getAppValueString')
			->willReturnCallback(function (string $key, string $default = '') {
				if ($key === Constants::CONFIG_APP_ADMIN_USER_IDS) {
					return '["hr_admin"]';
				}
				return $default;
			});
		$adminGroup = $this->createMock(\OCP\IGroup::class);
		$adminUser = $this->createMock(IUser::class);
		$adminUser->method('getUID')->willReturn('hr_admin');
		$adminUser->method('getDisplayName')->willReturn('HR Admin');
		$adminGroup->method('getUsers')->willReturn([$adminUser]);
		$this->groupManager->method('get')->with('admin')->willReturn($adminGroup);
		$this->groupManager->method('isAdmin')->willReturnCallback(static fn (string $uid): bool => $uid === 'hr_admin');
		$this->userManager->method('get')->with('hr_admin')->willReturn($adminUser);

		$response = $this->controller->getAdminSettings();
		$data = $response->getData();

		$this->assertTrue($data['success']);
		$this->assertSame(['hr_admin'], $data['settings']['appAdminUserIds']);
		$this->assertArrayHasKey('availableAppAdmins', $data);
		$this->assertCount(1, $data['availableAppAdmins']);
		$this->assertSame('hr_admin', $data['availableAppAdmins'][0]['id']);
	}

	public function testUpdateAdminSettingsNormalizesAccessAllowedGroups(): void
	{
		$this->request->method('getParams')
			->willReturn([
				'accessAllowedGroups' => ['group_a', 'group_a', 'missing_group', 'group_b'],
			]);

		$this->groupManager->method('get')->willReturnCallback(function (string $gid) {
			if (!in_array($gid, ['group_a', 'group_b'], true)) {
				return null;
			}
			$group = $this->createMock(\OCP\IGroup::class);
			$group->method('getGID')->willReturn($gid);
			return $group;
		});
		$this->appManager->expects($this->once())->method('enableAppForGroups')
			->with('arbeitszeitcheck', $this->callback(static fn (array $groups): bool => count($groups) === 2));
		$this->appManager->method('getAppRestriction')->with('arbeitszeitcheck')->willReturn(['group_a', 'group_b']);

		$response = $this->controller->updateAdminSettings();
		$data = $response->getData();
		$this->assertTrue($data['success']);
	}

	/**
	 * Test updateAdminSettings updates settings
	 */
	public function testUpdateAdminSettingsUpdatesSettings(): void
	{
		$this->request->method('getParams')
			->willReturn([
				'maxDailyHours' => 9.5,
				'germanState' => 'BY',
				'missingClockInRemindersEnabled' => false,
			]);

		$this->appConfig->expects($this->exactly(3))
			->method('setAppValueString')
			->withConsecutive(
				['missing_clock_in_reminders_enabled', '0'],
				['max_daily_hours', '9.5'],
				['german_state', 'BY']
			);

		$response = $this->controller->updateAdminSettings();
		$data = $response->getData();

		$this->assertTrue($data['success']);
		$this->assertArrayHasKey('settings', $data);
	}

	public function testUpdateAdminSettingsNormalizesAppAdminUsers(): void
	{
		$this->request->method('getParams')
			->willReturn([
				'appAdminUserIds' => ['hr_admin', 'hr_admin', 'missing', 'non_admin', 'security_admin'],
			]);

		$this->groupManager->method('isAdmin')->willReturnCallback(static function (string $uid): bool {
			return in_array($uid, ['hr_admin', 'security_admin'], true);
		});
		$this->userManager->method('get')->willReturnCallback(function (string $uid) {
			if (!in_array($uid, ['hr_admin', 'security_admin'], true)) {
				return null;
			}
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn($uid);
			return $user;
		});
		$this->appConfig->expects($this->once())
			->method('setAppValueString')
			->with(Constants::CONFIG_APP_ADMIN_USER_IDS, '["hr_admin","security_admin"]');

		$response = $this->controller->updateAdminSettings();
		$data = $response->getData();

		$this->assertTrue($data['success']);
		$this->assertSame(['hr_admin', 'security_admin'], $data['settings']['appAdminUserIds']);
	}

	/**
	 * Test updateAdminSettings validates maxDailyHours range
	 */
	public function testUpdateAdminSettingsValidatesMaxDailyHoursRange(): void
	{
		$this->request->method('getParams')
			->willReturn(['maxDailyHours' => 25]); // Invalid: > 24

		$response = $this->controller->updateAdminSettings();

		$this->assertEquals(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$data = $response->getData();
		$this->assertFalse($data['success']);
		$this->assertStringContainsString('Maximum daily hours must be between', $data['error']);
	}

	/**
	 * Test updateAdminSettings validates minRestPeriod range
	 */
	public function testUpdateAdminSettingsValidatesMinRestPeriodRange(): void
	{
		$this->request->method('getParams')
			->willReturn(['minRestPeriod' => 25]); // Invalid: > 24

		$response = $this->controller->updateAdminSettings();

		$this->assertEquals(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$data = $response->getData();
		$this->assertFalse($data['success']);
		$this->assertStringContainsString('Minimum rest period must be between', $data['error']);
	}

	/**
	 * Test updateAdminSettings validates German state code
	 */
	public function testUpdateAdminSettingsValidatesGermanState(): void
	{
		$this->request->method('getParams')
			->willReturn(['germanState' => 'XX']); // Invalid state code

		$response = $this->controller->updateAdminSettings();

		$this->assertEquals(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$data = $response->getData();
		$this->assertFalse($data['success']);
		$this->assertStringContainsString('Invalid German state code', $data['error']);
	}

	/**
	 * Test updateAdminSettings returns error when no settings provided
	 */
	public function testUpdateAdminSettingsReturnsErrorWhenNoSettings(): void
	{
		$this->request->method('getParams')->willReturn([]);

		$response = $this->controller->updateAdminSettings();

		$this->assertEquals(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$data = $response->getData();
		$this->assertFalse($data['success']);
		$this->assertStringContainsString('No valid settings provided', $data['error']);
	}

	/**
	 * Test getStatistics returns statistics
	 */
	public function testGetStatisticsReturnsStatistics(): void
	{
		$this->userManager->method('countUsersTotal')
			->willReturn(100);

		$this->timeEntryMapper->method('countDistinctUsersByDate')
			->willReturn(50);

		$this->violationMapper->method('count')
			->willReturn(5);

		$violation = new ComplianceViolation();
		$violation->setUserId('user1');

		$this->violationMapper->method('findUnresolved')
			->willReturn([$violation]);

		$response = $this->controller->getStatistics();
		$data = $response->getData();

		$this->assertTrue($data['success']);
		$this->assertArrayHasKey('statistics', $data);
		$this->assertEquals(100, $data['statistics']['total_users']);
		$this->assertEquals(50, $data['statistics']['active_users_today']);
		$this->assertEquals(5, $data['statistics']['unresolved_violations']);
	}

	/**
	 * Test getUsers returns users list
	 */
	public function testGetUsersReturnsUsersList(): void
	{
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user1');
		$user->method('getDisplayName')->willReturn('User One');
		$user->method('getEMailAddress')->willReturn('user1@example.com');
		$user->method('isEnabled')->willReturn(true);

		$this->userManager->method('search')
			->willReturn([$user]);

		$this->userManager->method('countUsersTotal')
			->willReturn(1);

		$this->userWorkingTimeModelMapper->method('findCurrentByUser')
			->willReturn(null);

		$this->timeEntryMapper->method('countDistinctUsersByDate')
			->willReturn(0);

		$response = $this->controller->getUsers();
		$data = $response->getData();

		$this->assertTrue($data['success']);
		$this->assertArrayHasKey('users', $data);
		$this->assertCount(1, $data['users']);
		$this->assertEquals('user1', $data['users'][0]['userId']);
	}

	/**
	 * Test getUsers applies search filter
	 */
	public function testGetUsersAppliesSearchFilter(): void
	{
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user1');
		$user->method('getDisplayName')->willReturn('User One');
		$user->method('getEMailAddress')->willReturn('user1@example.com');
		$user->method('isEnabled')->willReturn(true);

		$this->userManager->expects($this->once())
			->method('search')
			->with('test', 50, 0)
			->willReturn([$user]);

		$this->userManager->method('countUsersTotal')->willReturn(1);
		$this->userWorkingTimeModelMapper->method('findCurrentByUser')->willReturn(null);
		$this->timeEntryMapper->method('countDistinctUsersByDate')->willReturn(0);

		$response = $this->controller->getUsers('test', 50, 0);
		$data = $response->getData();

		$this->assertTrue($data['success']);
	}

	/**
	 * Test getUser returns user details
	 */
	public function testGetUserReturnsUserDetails(): void
	{
		$userId = 'user1';
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$user->method('getDisplayName')->willReturn('User One');
		$user->method('getEMailAddress')->willReturn('user1@example.com');
		$user->method('isEnabled')->willReturn(true);

		$this->userManager->method('get')
			->with($userId)
			->willReturn($user);

		$this->userWorkingTimeModelMapper->method('findCurrentByUser')
			->willReturn(null);

		$model = new WorkingTimeModel();
		$model->setId(1);
		$model->setName('Full-time');
		$model->setType(WorkingTimeModel::TYPE_FULL_TIME);
		$model->setWeeklyHours(40.0);
		$model->setDailyHours(8.0);

		$this->workingTimeModelMapper->method('findAll')
			->willReturn([$model]);

		$response = $this->controller->getUser($userId);
		$data = $response->getData();

		$this->assertTrue($data['success']);
		$this->assertArrayHasKey('user', $data);
		$this->assertEquals($userId, $data['user']['userId']);
		$this->assertArrayHasKey('availableWorkingTimeModels', $data['user']);
	}

	/**
	 * Test getUser returns not found when user doesn't exist
	 */
	public function testGetUserReturnsNotFoundWhenUserMissing(): void
	{
		$userId = 'nonexistent';

		$this->userManager->method('get')
			->with($userId)
			->willReturn(null);

		$response = $this->controller->getUser($userId);

		$this->assertEquals(Http::STATUS_NOT_FOUND, $response->getStatus());
		$data = $response->getData();
		$this->assertFalse($data['success']);
		$this->assertEquals('User not found', $data['error']);
	}

	/**
	 * Test getWorkingTimeModels returns models list
	 */
	public function testGetWorkingTimeModelsReturnsModelsList(): void
	{
		$model = new WorkingTimeModel();
		$model->setId(1);
		$model->setName('Full-time');
		$model->setDescription('40 hours per week');
		$model->setType(WorkingTimeModel::TYPE_FULL_TIME);
		$model->setWeeklyHours(40.0);
		$model->setDailyHours(8.0);
		$model->setIsDefault(true);

		$this->workingTimeModelMapper->expects($this->once())
			->method('findAll')
			->willReturn([$model]);

		$response = $this->controller->getWorkingTimeModels();
		$data = $response->getData();

		$this->assertTrue($data['success']);
		$this->assertArrayHasKey('models', $data);
		$this->assertCount(1, $data['models']);
		$this->assertEquals('Full-time', $data['models'][0]['name']);
	}

	/**
	 * Test getWorkingTimeModel returns model details
	 */
	public function testGetWorkingTimeModelReturnsModelDetails(): void
	{
		$modelId = 1;
		$model = new WorkingTimeModel();
		$model->setId($modelId);
		$model->setName('Full-time');
		$model->setDescription('40 hours per week');
		$model->setType(WorkingTimeModel::TYPE_FULL_TIME);
		$model->setWeeklyHours(40.0);
		$model->setDailyHours(8.0);
		$model->setBreakRulesArray([]);
		$model->setOvertimeRulesArray([]);
		$model->setIsDefault(true);

		$this->workingTimeModelMapper->expects($this->once())
			->method('find')
			->with($modelId)
			->willReturn($model);

		$response = $this->controller->getWorkingTimeModel($modelId);
		$data = $response->getData();

		$this->assertTrue($data['success']);
		$this->assertArrayHasKey('model', $data);
		$this->assertEquals($modelId, $data['model']['id']);
	}

	/**
	 * Test getWorkingTimeModel returns not found when model doesn't exist
	 */
	public function testGetWorkingTimeModelReturnsNotFoundWhenModelMissing(): void
	{
		$modelId = 999;

		$this->workingTimeModelMapper->expects($this->once())
			->method('find')
			->willThrowException(new \OCP\AppFramework\Db\DoesNotExistException('Model not found'));

		$response = $this->controller->getWorkingTimeModel($modelId);

		$this->assertEquals(Http::STATUS_NOT_FOUND, $response->getStatus());
		$data = $response->getData();
		$this->assertFalse($data['success']);
		$this->assertEquals('Working time model not found', $data['error']);
	}

	/**
	 * Test createWorkingTimeModel creates model
	 */
	public function testCreateWorkingTimeModelCreatesModel(): void
	{
		$this->request->method('getParams')
			->willReturn([
				'name' => 'Part-time',
				'type' => 'part_time',
				'weeklyHours' => 20.0,
				'dailyHours' => 4.0,
				'isDefault' => false
			]);

		$model = new WorkingTimeModel();
		$model->setId(1);
		$model->setName('Part-time');
		$model->setType(WorkingTimeModel::TYPE_PART_TIME);
		$model->setWeeklyHours(20.0);
		$model->setDailyHours(4.0);
		$model->setIsDefault(false);

		$this->workingTimeModelMapper->method('findDefault')->willReturn(null);
		$this->workingTimeModelMapper->expects($this->once())
			->method('insert')
			->willReturn($model);

		$response = $this->controller->createWorkingTimeModel();
		$data = $response->getData();

		$this->assertTrue($data['success']);
		$this->assertEquals(Http::STATUS_CREATED, $response->getStatus());
		$this->assertArrayHasKey('model', $data);
		$this->assertEquals('Part-time', $data['model']['name']);
	}

	public function testCreateWorkingTimeModelAcceptsCommaDecimals(): void
	{
		$this->request->method('getParams')
			->willReturn([
				'name' => 'Tarifmodell',
				'type' => 'full_time',
				'weeklyHours' => '38,7',
				'dailyHours' => '7,74',
				'isDefault' => false
			]);

		$this->workingTimeModelMapper->method('findDefault')->willReturn(null);
		$this->workingTimeModelMapper->expects($this->once())
			->method('insert')
			->with($this->callback(function (WorkingTimeModel $model): bool {
				return abs($model->getWeeklyHours() - 38.7) < 0.0001
					&& abs($model->getDailyHours() - 7.74) < 0.0001;
			}))
			->willReturnCallback(function (WorkingTimeModel $model) {
				$model->setId(99);
				return $model;
			});

		$response = $this->controller->createWorkingTimeModel();
		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$data = $response->getData();
		$this->assertTrue($data['success']);
	}

	/**
	 * Test createWorkingTimeModel unsets other defaults when setting as default
	 */
	public function testCreateWorkingTimeModelUnsetsOtherDefaults(): void
	{
		$this->request->method('getParams')
			->willReturn([
				'name' => 'New Default',
				'isDefault' => true
			]);

		$currentDefault = new WorkingTimeModel();
		$currentDefault->setId(1);
		$currentDefault->setName('Old Default');
		$currentDefault->setType(WorkingTimeModel::TYPE_FULL_TIME);
		$currentDefault->setWeeklyHours(40.0);
		$currentDefault->setDailyHours(8.0);
		$currentDefault->setIsDefault(true);
		$currentDefault->setUpdatedAt(new \DateTime());

		$newModel = new WorkingTimeModel();
		$newModel->setId(2);
		$newModel->setName('New Default');
		$newModel->setType(WorkingTimeModel::TYPE_FULL_TIME);
		$newModel->setWeeklyHours(40.0);
		$newModel->setDailyHours(8.0);
		$newModel->setIsDefault(true);

		$this->workingTimeModelMapper->method('findDefault')
			->willReturn($currentDefault);

		$this->workingTimeModelMapper->expects($this->once())
			->method('update')
			->with($currentDefault);

		$this->workingTimeModelMapper->expects($this->once())
			->method('insert')
			->willReturn($newModel);

		$response = $this->controller->createWorkingTimeModel();
		$data = $response->getData();

		$this->assertTrue($data['success']);
	}

	/**
	 * Test updateWorkingTimeModel updates model
	 */
	public function testUpdateWorkingTimeModelUpdatesModel(): void
	{
		$modelId = 1;
		$model = new WorkingTimeModel();
		$model->setId($modelId);
		$model->setName('Updated Name');
		$model->setType(WorkingTimeModel::TYPE_FULL_TIME);
		$model->setWeeklyHours(40.0);
		$model->setDailyHours(8.0);
		$model->setIsDefault(false);
		$model->setUpdatedAt(new \DateTime());

		$this->request->method('getParams')
			->willReturn(['name' => 'Updated Name']);

		$this->workingTimeModelMapper->method('find')
			->with($modelId)
			->willReturn($model);

		$this->workingTimeModelMapper->expects($this->once())
			->method('update')
			->with($model)
			->willReturn($model);

		$response = $this->controller->updateWorkingTimeModel($modelId);
		$data = $response->getData();

		$this->assertTrue($data['success']);
		$this->assertArrayHasKey('model', $data);
	}

	/**
	 * Test deleteWorkingTimeModel deletes model
	 */
	public function testDeleteWorkingTimeModelDeletesModel(): void
	{
		$modelId = 1;
		$model = $this->createMock(\OCA\ArbeitszeitCheck\Db\WorkingTimeModel::class);

		$this->workingTimeModelMapper->method('find')
			->with($modelId)
			->willReturn($model);

		$this->userWorkingTimeModelMapper->method('findByWorkingTimeModel')
			->with($modelId, false)
			->willReturn([]);

		$this->workingTimeModelMapper->expects($this->once())
			->method('delete')
			->with($model);

		$response = $this->controller->deleteWorkingTimeModel($modelId);
		$data = $response->getData();

		$this->assertTrue($data['success']);
		$this->assertArrayHasKey('message', $data);
	}

	/**
	 * Test deleteWorkingTimeModel returns error when users assigned
	 */
	public function testDeleteWorkingTimeModelReturnsErrorWhenUsersAssigned(): void
	{
		$modelId = 1;
		$model = $this->createMock(\OCA\ArbeitszeitCheck\Db\WorkingTimeModel::class);

		$userModel = $this->createMock(\OCA\ArbeitszeitCheck\Db\UserWorkingTimeModel::class);

		$this->workingTimeModelMapper->method('find')
			->with($modelId)
			->willReturn($model);

		$this->userWorkingTimeModelMapper->method('findByWorkingTimeModel')
			->with($modelId, false)
			->willReturn([$userModel]);

		$response = $this->controller->deleteWorkingTimeModel($modelId);

		$this->assertEquals(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$data = $response->getData();
		$this->assertFalse($data['success']);
		$this->assertStringContainsString('Cannot delete working time model', $data['error']);
	}

	/**
	 * Test updateUserWorkingTimeModel ends assignment when workingTimeModelId is null (No Model Assigned)
	 */
	public function testUpdateUserWorkingTimeModelRemovesAssignmentWhenNull(): void
	{
		$userId = 'admin';
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);

		$currentAssignment = new UserWorkingTimeModel();
		$currentAssignment->setId(1);
		$currentAssignment->setUserId($userId);
		$currentAssignment->setWorkingTimeModelId(1);
		$currentAssignment->setVacationDaysPerYear(25);
		$currentAssignment->setStartDate(new \DateTime('2024-01-01'));
		$currentAssignment->setUpdatedAt(new \DateTime());

		$endedAssignment = new UserWorkingTimeModel();
		$endedAssignment->setId(1);
		$endedAssignment->setUserId($userId);
		$endedAssignment->setWorkingTimeModelId(1);
		$endedAssignment->setVacationDaysPerYear(25);
		$endedAssignment->setStartDate(new \DateTime('2024-01-01'));
		$endedAssignment->setEndDate(new \DateTime('2024-01-02'));
		$endedAssignment->setUpdatedAt(new \DateTime());

		$this->request->method('getParams')
			->willReturn([
				'workingTimeModelId' => null,
				'vacationDaysPerYear' => 25,
				'startDate' => null,
				'endDate' => null
			]);

		$this->userManager->method('get')->with($userId)->willReturn($user);
		$this->userWorkingTimeModelMapper->method('findCurrentByUser')
			->with($userId)
			->willReturn($currentAssignment);
		$this->userWorkingTimeModelMapper->expects($this->once())
			->method('endCurrentAssignment')
			->with($userId, $this->isInstanceOf(\DateTime::class))
			->willReturn($endedAssignment);

		$response = $this->controller->updateUserWorkingTimeModel($userId);
		$data = $response->getData();

		$this->assertTrue($data['success']);
		$this->assertArrayHasKey('userWorkingTimeModel', $data);
	}

	/**
	 * Test updateUserWorkingTimeModel succeeds when no assignment and null model (nothing to do)
	 */
	public function testUpdateUserWorkingTimeModelSucceedsWhenNoAssignmentAndNullModel(): void
	{
		$userId = 'admin';
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);

		$this->request->method('getParams')
			->willReturn([
				'workingTimeModelId' => null,
				'vacationDaysPerYear' => 25,
				'startDate' => null,
				'endDate' => null
			]);

		$this->userManager->method('get')->with($userId)->willReturn($user);
		$this->userWorkingTimeModelMapper->method('findCurrentByUser')
			->with($userId)
			->willReturn(null);
		$this->userWorkingTimeModelMapper->expects($this->never())
			->method('endCurrentAssignment');

		$response = $this->controller->updateUserWorkingTimeModel($userId);
		$data = $response->getData();

		$this->assertTrue($data['success']);
		$this->assertNull($data['userWorkingTimeModel']);
	}

	public function testAssignVacationPolicyRejectsTariffRuleSetStartingAfterPolicyDate(): void
	{
		$userId = 'user1';
		$user = $this->createMock(IUser::class);
		$this->userManager->method('get')->with($userId)->willReturn($user);

		$this->request->method('getParams')->willReturn([
			'vacationMode' => Constants::VACATION_MODE_TARIFF_RULE_BASED,
			'tariffRuleSetId' => 11,
			'effectiveFrom' => '2026-05-01',
		]);

		$ruleSet = new TariffRuleSet();
		$ruleSet->setId(11);
		$ruleSet->setValidFrom(new \DateTime('2026-06-01'));
		$ruleSet->setValidTo(null);
		$ruleSet->setStatus(Constants::TARIFF_RULE_SET_STATUS_ACTIVE);
		$this->tariffRuleSetMapper->expects($this->once())
			->method('find')
			->with(11)
			->willReturn($ruleSet);

		$response = $this->controller->assignVacationPolicy($userId);
		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$data = $response->getData();
		$this->assertFalse($data['success']);
		$this->assertSame('Tariff rule set starts after policy effective date', $data['error']);
	}

	public function testActivateTariffRuleSetWithNextMonthAdjustsValidityAndClosesOverlap(): void
	{
		$ruleSet = new TariffRuleSet();
		$ruleSet->setId(22);
		$ruleSet->setTariffCode('TVOD');
		$ruleSet->setActivationMode('next_month');
		$ruleSet->setValidFrom(new \DateTime('2026-01-01'));
		$ruleSet->setStatus(Constants::TARIFF_RULE_SET_STATUS_DRAFT);
		$ruleSet->setUpdatedAt(new \DateTime('2026-04-01'));

		$existingActive = new TariffRuleSet();
		$existingActive->setId(21);
		$existingActive->setTariffCode('TVOD');
		$existingActive->setValidFrom(new \DateTime('2025-01-01'));
		$existingActive->setValidTo(null);
		$existingActive->setStatus(Constants::TARIFF_RULE_SET_STATUS_ACTIVE);
		$existingActive->setUpdatedAt(new \DateTime('2026-04-01'));

		$this->tariffRuleSetMapper->expects($this->once())
			->method('find')
			->with(22)
			->willReturn($ruleSet);
		$this->tariffRuleSetMapper->expects($this->once())
			->method('findActiveByTariffCode')
			->with('TVOD')
			->willReturn([$existingActive]);
		$this->tariffRuleSetMapper->expects($this->exactly(2))
			->method('update');

		$response = $this->controller->activateTariffRuleSet(22);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertTrue($data['success']);
		$this->assertSame(Constants::TARIFF_RULE_SET_STATUS_ACTIVE, $ruleSet->getStatus());
		$this->assertSame((new \DateTimeImmutable('first day of next month'))->format('Y-m-d'), $ruleSet->getValidFrom()->format('Y-m-d'));
		$this->assertSame((new \DateTimeImmutable('first day of next month'))->modify('-1 day')->format('Y-m-d'), $existingActive->getValidTo()->format('Y-m-d'));
	}

	public function testSimulateVacationPolicyAcceptsDraftPolicy(): void
	{
		$this->request->method('getParams')->willReturn([
			'userId' => 'alice',
			'asOfDate' => '2026-04-20',
			'draftPolicy' => [
				'vacationMode' => Constants::VACATION_MODE_MANUAL_FIXED,
				'manualDays' => '28,5',
			],
		]);

		$this->vacationEntitlementEngine->expects($this->once())
			->method('computeForPolicy')
			->with(
				'alice',
				$this->callback(function ($policy) {
					return $policy->getVacationMode() === Constants::VACATION_MODE_MANUAL_FIXED
						&& $policy->getManualDays() === 28.5
						&& $policy->getUserId() === 'alice';
				}),
				$this->isInstanceOf(\DateTimeInterface::class)
			)
			->willReturn([
				'days' => 28.5,
				'source' => 'manual',
				'ruleSetId' => null,
				'trace' => ['formula' => 'manual'],
			]);

		$response = $this->controller->simulateVacationPolicy();
		$data = $response->getData();

		$this->assertTrue($data['success']);
		$this->assertSame(28.5, $data['effectiveEntitlementDays']);
		$this->assertSame('manual', $data['source']);
	}

	/**
	 * Test getAuditLogs returns audit logs
	 */
	public function testGetAuditLogsReturnsAuditLogs(): void
	{
		$log = new AuditLog();
		$log->setId(1);
		$log->setUserId('user1');
		$log->setAction('time_entry_created');
		$log->setEntityType('time_entry');
		$log->setEntityId(1);
		$log->setOldValues(null);
		$log->setNewValues('{"id":1}');
		$log->setPerformedBy('user1');
		$log->setIpAddress('127.0.0.1');
		$log->setUserAgent('Test');
		$log->setCreatedAt(new \DateTime());

		$this->request->method('getParams')
			->willReturn([]);

		$user = $this->createMock(IUser::class);
		$user->method('getDisplayName')->willReturn('User One');

		$this->userManager->method('get')
			->willReturn($user);

		$this->auditLogMapper->expects($this->once())
			->method('findByDateRange')
			->willReturn([$log]);

		$response = $this->controller->getAuditLogs();
		$data = $response->getData();

		$this->assertTrue($data['success']);
		$this->assertArrayHasKey('logs', $data);
		$this->assertCount(1, $data['logs']);
	}

	/**
	 * Test getAuditLogStats returns statistics
	 */
	public function testGetAuditLogStatsReturnsStatistics(): void
	{
		$stats = [
			'total_actions' => 100,
			'actions_by_type' => []
		];

		$this->request->method('getParams')->willReturn([]);

		$this->auditLogMapper->expects($this->once())
			->method('getStatistics')
			->willReturn($stats);

		$response = $this->controller->getAuditLogStats();
		$data = $response->getData();

		$this->assertTrue($data['success']);
		$this->assertArrayHasKey('statistics', $data);
		$this->assertEquals(100, $data['statistics']['total_actions']);
	}

	/**
	 * Test exportUsers exports users data
	 */
	public function testExportUsersExportsUsersData(): void
	{
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user1');
		$user->method('getDisplayName')->willReturn('User One');
		$user->method('getEMailAddress')->willReturn('user1@example.com');
		$user->method('isEnabled')->willReturn(true);

		$this->userManager->method('search')
			->willReturn([$user]);

		$this->userWorkingTimeModelMapper->method('findCurrentByUser')
			->willReturn(null);

		$response = $this->controller->exportUsers('csv');

		$this->assertInstanceOf(DataDownloadResponse::class, $response);
		$headers = method_exists($response, 'getHeaders') ? $response->getHeaders() : [];
		$contentDisposition = $headers['Content-Disposition'] ?? $headers['content-disposition'] ?? '';
		$this->assertStringContainsString('users-export-', $contentDisposition);
		$this->assertStringContainsString('.csv', $contentDisposition);
	}

	/**
	 * Test exportAuditLogs exports audit logs
	 */
	public function testExportAuditLogsExportsAuditLogs(): void
	{
		$log = new AuditLog();
		$log->setId(1);
		$log->setUserId('user1');
		$log->setAction('time_entry_created');
		$log->setEntityType('time_entry');
		$log->setEntityId(1);
		$log->setOldValues(null);
		$log->setNewValues('{"id":1}');
		$log->setPerformedBy('user1');
		$log->setIpAddress('127.0.0.1');
		$log->setUserAgent('Test');
		$log->setCreatedAt(new \DateTime());

		$this->request->method('getParams')->willReturn([]);

		$user = $this->createMock(IUser::class);
		$user->method('getDisplayName')->willReturn('User One');

		$this->userManager->method('get')->willReturn($user);

		$this->auditLogMapper->method('findByDateRange')
			->willReturn([$log]);

		$response = $this->controller->exportAuditLogs('csv');

		$this->assertInstanceOf(DataDownloadResponse::class, $response);
		$headers = method_exists($response, 'getHeaders') ? $response->getHeaders() : [];
		$contentDisposition = $headers['Content-Disposition'] ?? $headers['content-disposition'] ?? '';
		$this->assertStringContainsString('audit-logs-export-', $contentDisposition);
		$this->assertStringContainsString('.csv', $contentDisposition);
	}

	/**
	 * Test getAdminSettings handles exceptions
	 */
	public function testGetAdminSettingsHandlesException(): void
	{
		$this->appConfig->expects($this->once())
			->method('getAppValueString')
			->willThrowException(new \Exception('Config error'));

		$response = $this->controller->getAdminSettings();

		$this->assertEquals(Http::STATUS_INTERNAL_SERVER_ERROR, $response->getStatus());
		$data = $response->getData();
		$this->assertFalse($data['success']);
		$this->assertEquals('An unexpected error occurred. Please try again. If the problem continues, contact your administrator.', $data['error']);
	}

	/**
	 * Test getStatistics handles exceptions
	 */
	public function testGetStatisticsHandlesException(): void
	{
		$this->userManager->expects($this->once())
			->method('countUsersTotal')
			->willThrowException(new \Exception('Database error'));

		$response = $this->controller->getStatistics();

		$this->assertEquals(Http::STATUS_INTERNAL_SERVER_ERROR, $response->getStatus());
		$data = $response->getData();
		$this->assertFalse($data['success']);
	}
}
