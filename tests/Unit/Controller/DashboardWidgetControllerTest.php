<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Controller;

use OCA\ArbeitszeitCheck\Controller\DashboardWidgetController;
use OCA\ArbeitszeitCheck\Db\TimeEntry;
use OCA\ArbeitszeitCheck\Service\DashboardWidgetDataService;
use OCA\ArbeitszeitCheck\Service\PermissionService;
use OCA\ArbeitszeitCheck\Service\TimeTrackingService;
use OCP\AppFramework\Http;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DashboardWidgetControllerTest extends TestCase {
	private DashboardWidgetController $controller;
	/** @var IUserSession&MockObject */
	private $userSession;
	/** @var DashboardWidgetDataService&MockObject */
	private $widgetDataService;
	/** @var TimeTrackingService&MockObject */
	private $timeTrackingService;

	protected function setUp(): void {
		parent::setUp();
		$request = $this->createMock(IRequest::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->widgetDataService = $this->createMock(DashboardWidgetDataService::class);
		$this->timeTrackingService = $this->createMock(TimeTrackingService::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn ($s, $p = []) => $p ? (string)vsprintf($s, $p) : $s);
		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->method('linkToRoute')->willReturn('/dummy');
		$permissionService = $this->createMock(PermissionService::class);

		$this->controller = new DashboardWidgetController(
			'arbeitszeitcheck',
			$request,
			$this->userSession,
			$l10n,
			$urlGenerator,
			$this->widgetDataService,
			$this->timeTrackingService,
			$permissionService
		);
	}

	public function testEmployeeDataReturnsPayloadForAuthenticatedUser(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('u1');
		$this->userSession->method('getUser')->willReturn($user);
		$this->widgetDataService->method('getEmployeeWidgetData')->with('u1')->willReturn(['status' => 'active']);

		$response = $this->controller->employeeData();
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertTrue($response->getData()['success']);
	}

	public function testManagerDataRejectsUnauthorizedUser(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('u1');
		$this->userSession->method('getUser')->willReturn($user);
		$this->widgetDataService->method('getManagerWidgetData')->willReturn(['authorized' => false]);

		$response = $this->controller->managerData();
		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
		$this->assertFalse($response->getData()['success']);
	}

	public function testClockInReturnsUpdatedStatusOnSuccess(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('u1');
		$this->userSession->method('getUser')->willReturn($user);

		$entry = $this->createMock(TimeEntry::class);
		$entry->method('getSummary')->willReturn(['id' => 1, 'status' => 'active']);
		$this->timeTrackingService->method('clockIn')->willReturn($entry);
		$this->timeTrackingService->method('getStatus')->willReturn(['status' => 'active']);

		$response = $this->controller->clockIn();
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertTrue($response->getData()['success']);
		$this->assertSame('active', $response->getData()['status']['status']);
	}

	public function testManagerDataClampsLimit(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('u1');
		$this->userSession->method('getUser')->willReturn($user);
		$this->widgetDataService->expects($this->once())
			->method('getManagerWidgetData')
			->with('u1', 50)
			->willReturn(['authorized' => true, 'members' => [], 'summary' => []]);

		$response = $this->controller->managerData(5000);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}
}
