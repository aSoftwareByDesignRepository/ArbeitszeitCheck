<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Middleware;

use OCA\ArbeitszeitCheck\Middleware\ClientLicenseMiddleware;
use OCA\ArbeitszeitCheck\Middleware\ClientLicenseRequiredException;
use OCA\ArbeitszeitCheck\Service\LicenseService;
use OCA\ArbeitszeitCheck\Service\MobileSeatService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use OCP\IL10N;
use OCP\L10N\IFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ClientLicenseMiddlewareTest extends TestCase
{
	private function l10nFactory(): IFactory
	{
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$factory = $this->createMock(IFactory::class);
		$factory->method('get')->willReturn($l10n);

		return $factory;
	}

	public function testUnassignedMobileUserGets402OnGet(): void
	{
		$request = $this->createMock(IRequest::class);
		$request->method('getMethod')->willReturn('GET');
		$request->method('getPathInfo')->willReturn('/apps/arbeitszeitcheck/api/clock/status');
		$request->method('getHeader')->willReturnCallback(function (string $name): string {
			return match (strtolower($name)) {
				'authorization' => 'Basic dGVzdDp0ZXN0',
				default => '',
			};
		});

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);

		$license = $this->createMock(LicenseService::class);
		$license->method('isMobilePlanActive')->willReturn(true);
		$seats = $this->createMock(MobileSeatService::class);
		$seats->method('isUserAllowed')->with('alice')->willReturn(false);

		$middleware = new ClientLicenseMiddleware(
			$request,
			$userSession,
			$license,
			$seats,
			$this->l10nFactory(),
			$this->createMock(LoggerInterface::class),
		);

		$this->expectException(ClientLicenseRequiredException::class);
		$middleware->beforeController(new \stdClass(), 'status');
	}

	public function testBootstrapPathIsNotGated(): void
	{
		$request = $this->createMock(IRequest::class);
		$request->method('getMethod')->willReturn('GET');
		$request->method('getPathInfo')->willReturn('/apps/arbeitszeitcheck/api/mobile/bootstrap');
		$request->method('getHeader')->willReturnCallback(function (string $name): string {
			return strtolower($name) === 'authorization' ? 'Basic dGVzdDp0ZXN0' : '';
		});

		$license = $this->createMock(LicenseService::class);
		$license->expects($this->never())->method('isMobilePlanActive');

		$middleware = new ClientLicenseMiddleware(
			$request,
			$this->createMock(IUserSession::class),
			$license,
			$this->createMock(MobileSeatService::class),
			$this->l10nFactory(),
			$this->createMock(LoggerInterface::class),
		);

		$middleware->beforeController(new \stdClass(), 'bootstrap');
		$this->addToAssertionCount(1);
	}

	public function testUnassignedMobileUserGets402(): void
	{
		$request = $this->createMock(IRequest::class);
		$request->method('getMethod')->willReturn('POST');
		$request->method('getPathInfo')->willReturn('/apps/arbeitszeitcheck/api/clock/in');
		$request->method('getHeader')->willReturnCallback(function (string $name): string {
			return match (strtolower($name)) {
				'authorization' => 'Basic dGVzdDp0ZXN0',
				default => '',
			};
		});

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);

		$license = $this->createMock(LicenseService::class);
		$license->method('isMobilePlanActive')->willReturn(true);
		$seats = $this->createMock(MobileSeatService::class);
		$seats->method('isUserAllowed')->with('alice')->willReturn(false);

		$middleware = new ClientLicenseMiddleware(
			$request,
			$userSession,
			$license,
			$seats,
			$this->l10nFactory(),
			$this->createMock(LoggerInterface::class),
		);

		$this->expectException(ClientLicenseRequiredException::class);
		$middleware->beforeController(new \stdClass(), 'clockIn');
	}

	public function testBasicAuthWithoutMobileUaIsStillGated(): void
	{
		$request = $this->createMock(IRequest::class);
		$request->method('getMethod')->willReturn('POST');
		$request->method('getPathInfo')->willReturn('/apps/arbeitszeitcheck/api/clock/in');
		$request->method('getHeader')->willReturnCallback(function (string $name): string {
			return match (strtolower($name)) {
				'user-agent' => 'curl/8.0',
				'authorization' => 'Basic dGVzdDp0ZXN0',
				default => '',
			};
		});

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);

		$license = $this->createMock(LicenseService::class);
		$license->method('isMobilePlanActive')->willReturn(false);

		$middleware = new ClientLicenseMiddleware(
			$request,
			$userSession,
			$license,
			$this->createMock(MobileSeatService::class),
			$this->l10nFactory(),
			$this->createMock(LoggerInterface::class),
		);

		$this->expectException(ClientLicenseRequiredException::class);
		$middleware->beforeController(new \stdClass(), 'clockIn');
	}

	public function testWebSessionWithoutBasicAuthIsNotGated(): void
	{
		$request = $this->createMock(IRequest::class);
		$request->method('getMethod')->willReturn('POST');
		$request->method('getPathInfo')->willReturn('/apps/arbeitszeitcheck/api/clock/in');
		$request->method('getHeader')->willReturn('');

		$license = $this->createMock(LicenseService::class);
		$license->expects($this->never())->method('isMobilePlanActive');

		$middleware = new ClientLicenseMiddleware(
			$request,
			$this->createMock(IUserSession::class),
			$license,
			$this->createMock(MobileSeatService::class),
			$this->l10nFactory(),
			$this->createMock(LoggerInterface::class),
		);

		$middleware->beforeController(new \stdClass(), 'clockIn');
		$this->addToAssertionCount(1);
	}

	public function testDashboardWidgetClockPathIsGated(): void
	{
		$request = $this->createMock(IRequest::class);
		$request->method('getMethod')->willReturn('POST');
		$request->method('getPathInfo')->willReturn('/apps/arbeitszeitcheck/api/dashboard-widget/clock/in');
		$request->method('getHeader')->willReturnCallback(function (string $name): string {
			return strtolower($name) === 'authorization' ? 'Basic dGVzdDp0ZXN0' : '';
		});

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);

		$license = $this->createMock(LicenseService::class);
		$license->method('isMobilePlanActive')->willReturn(true);
		$seats = $this->createMock(MobileSeatService::class);
		$seats->method('isUserAllowed')->willReturn(false);

		$middleware = new ClientLicenseMiddleware(
			$request,
			$userSession,
			$license,
			$seats,
			$this->l10nFactory(),
			$this->createMock(LoggerInterface::class),
		);

		$this->expectException(ClientLicenseRequiredException::class);
		$middleware->beforeController(new \stdClass(), 'clockIn');
	}

	public function testAfterExceptionReturns402Json(): void
	{
		$request = $this->createMock(IRequest::class);
		$middleware = new ClientLicenseMiddleware(
			$request,
			$this->createMock(IUserSession::class),
			$this->createMock(LicenseService::class),
			$this->createMock(MobileSeatService::class),
			$this->l10nFactory(),
			$this->createMock(LoggerInterface::class),
		);

		$response = $middleware->afterException(
			new \stdClass(),
			'clockIn',
			new ClientLicenseRequiredException('no_seat'),
		);
		$this->assertInstanceOf(JSONResponse::class, $response);
		$this->assertSame(Http::STATUS_PAYMENT_REQUIRED, $response->getStatus());
		$data = $response->getData();
		$this->assertSame('LICENSE_REQUIRED', $data['code']);
		$this->assertSame('LICENSE_REQUIRED', $data['error_code']);
		$this->assertSame('ArbeitszeitCheck Mobile is not licensed for this user.', $data['error']);
		$this->assertSame('ArbeitszeitCheck Mobile is not licensed for this user.', $data['message']);
	}
}
