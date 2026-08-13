<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Middleware;

use OCA\ArbeitszeitCheck\Controller\AdminController;
use OCA\ArbeitszeitCheck\Exception\NotAppAdminException;
use OCA\ArbeitszeitCheck\Middleware\AppAdminMiddleware;
use OCA\ArbeitszeitCheck\Service\PermissionService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

class AppAdminMiddlewareTest extends TestCase
{
	private function makeRequest(string $path = '/apps/arbeitszeitcheck/admin', string $method = 'GET', array $headers = []): IRequest
	{
		$request = $this->createMock(IRequest::class);
		$request->method('getPathInfo')->willReturn($path);
		$request->method('getMethod')->willReturn($method);
		$request->method('getHeader')->willReturnCallback(static function (string $name) use ($headers): string {
			return (string)($headers[$name] ?? '');
		});
		return $request;
	}

	public function testBeforeControllerSkipsNonAdminController(): void
	{
		$userSession = $this->createMock(IUserSession::class);
		$userSession->expects($this->never())->method('getUser');
		$permissionService = $this->createMock(PermissionService::class);
		$permissionService->expects($this->never())->method('isAdmin');
		$l10n = $this->createMock(IL10N::class);
		$middleware = new AppAdminMiddleware($userSession, $permissionService, $l10n, $this->makeRequest());

		$middleware->beforeController(new \stdClass(), 'anyMethod');

		$this->assertTrue(true);
	}

	public function testBeforeControllerAllowsConfiguredAppAdmin(): void
	{
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('hr_admin');
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);
		$permissionService = $this->createMock(PermissionService::class);
		$permissionService->expects($this->once())->method('isAdmin')->with('hr_admin')->willReturn(true);
		$l10n = $this->createMock(IL10N::class);
		$middleware = new AppAdminMiddleware($userSession, $permissionService, $l10n, $this->makeRequest());
		$controller = $this->getMockBuilder(AdminController::class)->disableOriginalConstructor()->getMock();

		$middleware->beforeController($controller, 'dashboard');

		$this->assertTrue(true);
	}

	public function testBeforeControllerThrowsWhenNoAuthenticatedUser(): void
	{
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn(null);
		$permissionService = $this->createMock(PermissionService::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturn('Access denied');
		$middleware = new AppAdminMiddleware($userSession, $permissionService, $l10n, $this->makeRequest());
		$controller = $this->getMockBuilder(AdminController::class)->disableOriginalConstructor()->getMock();

		$this->expectException(NotAppAdminException::class);
		$middleware->beforeController($controller, 'dashboard');
	}

	public function testBeforeControllerThrowsWhenUserIsNotAppAdmin(): void
	{
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('other_admin');
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);
		$permissionService = $this->createMock(PermissionService::class);
		$permissionService->method('isAdmin')->with('other_admin')->willReturn(false);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturn('Access denied');
		$middleware = new AppAdminMiddleware($userSession, $permissionService, $l10n, $this->makeRequest());
		$controller = $this->getMockBuilder(AdminController::class)->disableOriginalConstructor()->getMock();

		$this->expectException(NotAppAdminException::class);
		$middleware->beforeController($controller, 'dashboard');
	}

	public function testAfterExceptionReturnsHtml403ForBrowserPageLoads(): void
	{
		$userSession = $this->createMock(IUserSession::class);
		$permissionService = $this->createMock(PermissionService::class);
		$l10n = $this->createMock(IL10N::class);
		$middleware = new AppAdminMiddleware(
			$userSession,
			$permissionService,
			$l10n,
			$this->makeRequest('/apps/arbeitszeitcheck/admin', 'GET', ['Accept' => 'text/html'])
		);
		$exception = new NotAppAdminException('Access denied');

		$response = $middleware->afterException(new \stdClass(), 'dashboard', $exception);

		$this->assertInstanceOf(TemplateResponse::class, $response);
		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
		$this->assertSame('403', $response->getTemplateName());
	}

	public function testAfterExceptionReturnsJsonForApiPaths(): void
	{
		$userSession = $this->createMock(IUserSession::class);
		$permissionService = $this->createMock(PermissionService::class);
		$l10n = $this->createMock(IL10N::class);
		$middleware = new AppAdminMiddleware(
			$userSession,
			$permissionService,
			$l10n,
			$this->makeRequest('/apps/arbeitszeitcheck/api/admin/settings', 'GET')
		);
		$exception = new NotAppAdminException('Access denied');

		$response = $middleware->afterException(new \stdClass(), 'getAdminSettings', $exception);

		$this->assertInstanceOf(JSONResponse::class, $response);
		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
		$data = $response->getData();
		$this->assertIsArray($data);
		$this->assertFalse($data['ok']);
		$this->assertSame('admin_required', $data['error']['code']);
	}

	/**
	 * AC-014: employee list API and export are admin-gated for every filter value.
	 *
	 * @dataProvider employeeListAdminApiMethodsProvider
	 */
	public function testAfterExceptionReturnsJson403ForEmployeeListApi(string $method, string $path): void
	{
		$userSession = $this->createMock(IUserSession::class);
		$permissionService = $this->createMock(PermissionService::class);
		$l10n = $this->createMock(IL10N::class);
		$middleware = new AppAdminMiddleware(
			$userSession,
			$permissionService,
			$l10n,
			$this->makeRequest($path, 'GET')
		);
		$exception = new NotAppAdminException('Access denied');

		$response = $middleware->afterException(new \stdClass(), $method, $exception);

		$this->assertInstanceOf(JSONResponse::class, $response);
		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
		$data = $response->getData();
		$this->assertFalse($data['ok']);
		$this->assertSame('admin_required', $data['error']['code']);
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function employeeListAdminApiMethodsProvider(): array
	{
		return [
			'getUsers app_access' => ['getUsers', '/apps/arbeitszeitcheck/api/admin/users?filter=app_access'],
			'getUsers all' => ['getUsers', '/apps/arbeitszeitcheck/api/admin/users?filter=all'],
			'exportUsers default' => ['exportUsers', '/apps/arbeitszeitcheck/api/admin/users/export?format=csv'],
			'exportUsers all' => ['exportUsers', '/apps/arbeitszeitcheck/api/admin/users/export?format=csv&filter=all'],
		];
	}

	public function testAfterExceptionReturnsJsonForXmlHttpRequest(): void
	{
		$userSession = $this->createMock(IUserSession::class);
		$permissionService = $this->createMock(PermissionService::class);
		$l10n = $this->createMock(IL10N::class);
		$middleware = new AppAdminMiddleware(
			$userSession,
			$permissionService,
			$l10n,
			$this->makeRequest('/apps/arbeitszeitcheck/admin', 'GET', ['X-Requested-With' => 'XMLHttpRequest'])
		);
		$exception = new NotAppAdminException('Access denied');

		$response = $middleware->afterException(new \stdClass(), 'dashboard', $exception);

		$this->assertInstanceOf(JSONResponse::class, $response);
		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
		$data = $response->getData();
		$this->assertFalse($data['ok']);
		$this->assertSame('admin_required', $data['error']['code']);
	}

	public function testAfterExceptionRethrowsUnknownException(): void
	{
		$userSession = $this->createMock(IUserSession::class);
		$permissionService = $this->createMock(PermissionService::class);
		$l10n = $this->createMock(IL10N::class);
		$middleware = new AppAdminMiddleware($userSession, $permissionService, $l10n, $this->makeRequest());

		$this->expectException(\RuntimeException::class);
		$middleware->afterException(new \stdClass(), 'dashboard', new \RuntimeException('boom'));
	}
}
