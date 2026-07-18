<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Controller;

use OCA\ArbeitszeitCheck\Capabilities;
use OCA\ArbeitszeitCheck\Controller\MobileBootstrapController;
use OCA\ArbeitszeitCheck\Service\DashboardWidgetDataService;
use OCA\ArbeitszeitCheck\Service\LicenseService;
use OCA\ArbeitszeitCheck\Service\MobileSeatService;
use OCA\ArbeitszeitCheck\Service\OvertimeBankService;
use OCA\ArbeitszeitCheck\Service\PermissionService;
use OCP\App\IAppManager;
use OCP\AppFramework\Http;
use OCP\AppFramework\Services\IAppConfig;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use OCP\L10N\IFactory as L10NFactory;
use PHPUnit\Framework\TestCase;

class MobileBootstrapControllerTest extends TestCase {
	public function testBootstrapReturnsEmployeePayload(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$user->method('getDisplayName')->willReturn('Alice');

		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);

		$widget = $this->createMock(DashboardWidgetDataService::class);
		$widget->method('getEmployeeWidgetData')->willReturn(['status' => 'clocked_out']);

		$permissions = $this->createMock(PermissionService::class);
		$permissions->method('canAccessManagerDashboard')->willReturn(false);
		$permissions->method('isAdmin')->willReturn(false);

		$bank = $this->createMock(OvertimeBankService::class);
		$bank->method('isEnabled')->willReturn(false);

		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('isEnabledForUser')->willReturn(true);

		$config = $this->createMock(IConfig::class);
		$appConfig = $this->createMock(IAppConfig::class);

		$l10nFactory = $this->createMock(L10NFactory::class);
		$l10nFactory->method('findLanguage')->willReturn('de');

		$capabilities = $this->createMock(Capabilities::class);
		$capabilities->method('getCapabilities')->willReturn(['arbeitszeitcheck' => ['version' => '1.3.9']]);

		$license = $this->createMock(LicenseService::class);
		$license->method('isMobilePlanActive')->willReturn(false);
		$license->method('getValidUntil')->willReturn(null);
		$license->method('buildEnvelope')->willReturn(null);
		$license->method('getMobileSeatLimit')->willReturn(0);

		$seats = $this->createMock(MobileSeatService::class);
		$seats->method('isUserAllowed')->willReturn(false);
		$seats->method('getAssignedCount')->willReturn(0);

		$controller = new MobileBootstrapController(
			'arbeitszeitcheck',
			$this->createMock(IRequest::class),
			$userSession,
			$widget,
			$permissions,
			$bank,
			$appManager,
			$config,
			$appConfig,
			$l10nFactory,
			$capabilities,
			$license,
			$seats,
		);

		$response = $controller->bootstrap();
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertTrue($data['success']);
		$this->assertSame('alice', $data['data']['userId']);
		$this->assertTrue($data['data']['pushAvailable']);
		$this->assertSame('clocked_out', $data['data']['employee']['status']);
	}

	public function testDashboardReturnsFullEmployeeWidgetData(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');

		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);

		$widget = $this->createMock(DashboardWidgetDataService::class);
		$widget->expects($this->once())
			->method('getEmployeeWidgetData')
			->with('alice')
			->willReturn([
				'status' => 'clocked_out',
				'vacationRemaining' => 12.0,
				'vacationEntitlement' => 30.0,
				'weekHoursWorked' => 8.0,
			]);

		$controller = new MobileBootstrapController(
			'arbeitszeitcheck',
			$this->createMock(IRequest::class),
			$userSession,
			$widget,
			$this->createMock(PermissionService::class),
			$this->createMock(OvertimeBankService::class),
			$this->createMock(IAppManager::class),
			$this->createMock(IConfig::class),
			$this->createMock(IAppConfig::class),
			$this->createMock(L10NFactory::class),
			$this->createMock(Capabilities::class),
			$this->createMock(LicenseService::class),
			$this->createMock(MobileSeatService::class),
		);

		$response = $controller->dashboard();
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertTrue($data['success']);
		$this->assertSame('clocked_out', $data['data']['status']);
		$this->assertSame(12.0, $data['data']['vacationRemaining']);
		$this->assertSame(30.0, $data['data']['vacationEntitlement']);
		$this->assertSame(8.0, $data['data']['weekHoursWorked']);
	}

	public function testBootstrapReturnsEnvelopeWhenPlanActiveButUserHasNoSeat(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('bob');
		$user->method('getDisplayName')->willReturn('Bob');

		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);

		$envelope = ['format' => 'AZC2', 'payloadB64' => 'abc', 'signatureB64' => 'sig'];

		$license = $this->createMock(LicenseService::class);
		$license->method('isMobilePlanActive')->willReturn(true);
		$license->method('getValidUntil')->willReturn(new \DateTimeImmutable('2027-05-29'));
		$license->method('buildEnvelope')->willReturn($envelope);
		$license->method('getMobileSeatLimit')->willReturn(5);

		$seats = $this->createMock(MobileSeatService::class);
		$seats->method('isUserAllowed')->with('bob')->willReturn(false);
		$seats->method('getAssignedCount')->willReturn(2);

		$controller = new MobileBootstrapController(
			'arbeitszeitcheck',
			$this->createMock(IRequest::class),
			$userSession,
			$this->createMock(DashboardWidgetDataService::class),
			$this->createMock(PermissionService::class),
			$this->createMock(OvertimeBankService::class),
			$this->createMock(IAppManager::class),
			$this->createMock(IConfig::class),
			$this->createMock(IAppConfig::class),
			$this->createMock(L10NFactory::class),
			$this->createMock(Capabilities::class),
			$license,
			$seats,
		);

		$response = $controller->bootstrap();
		$data = $response->getData();
		$this->assertFalse($data['data']['licensing']['mobile']['enabledForUser']);
		$this->assertSame($envelope, $data['data']['licensing']['envelope']);
	}

	public function testBootstrapUnauthorizedWithoutUser(): void {
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn(null);

		$controller = new MobileBootstrapController(
			'arbeitszeitcheck',
			$this->createMock(IRequest::class),
			$userSession,
			$this->createMock(DashboardWidgetDataService::class),
			$this->createMock(PermissionService::class),
			$this->createMock(OvertimeBankService::class),
			$this->createMock(IAppManager::class),
			$this->createMock(IConfig::class),
			$this->createMock(IAppConfig::class),
			$this->createMock(L10NFactory::class),
			$this->createMock(Capabilities::class),
			$this->createMock(LicenseService::class),
			$this->createMock(MobileSeatService::class),
		);

		$response = $controller->bootstrap();
		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}
}
