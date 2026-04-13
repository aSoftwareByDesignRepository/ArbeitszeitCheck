<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Middleware;

use OCA\ArbeitszeitCheck\Controller\AdminController;
use OCA\ArbeitszeitCheck\Exception\NotAppAdminException;
use OCA\ArbeitszeitCheck\Middleware\AppAdminMiddleware;
use OCA\ArbeitszeitCheck\Service\PermissionService;
use OCP\AppFramework\Http;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

class AppAdminMiddlewareTest extends TestCase
{
	public function testBeforeControllerSkipsNonAdminController(): void
	{
		$userSession = $this->createMock(IUserSession::class);
		$userSession->expects($this->never())->method('getUser');
		$permissionService = $this->createMock(PermissionService::class);
		$permissionService->expects($this->never())->method('isAdmin');
		$l10n = $this->createMock(IL10N::class);
		$middleware = new AppAdminMiddleware($userSession, $permissionService, $l10n);

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
		$middleware = new AppAdminMiddleware($userSession, $permissionService, $l10n);
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
		$middleware = new AppAdminMiddleware($userSession, $permissionService, $l10n);
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
		$middleware = new AppAdminMiddleware($userSession, $permissionService, $l10n);
		$controller = $this->getMockBuilder(AdminController::class)->disableOriginalConstructor()->getMock();

		$this->expectException(NotAppAdminException::class);
		$middleware->beforeController($controller, 'dashboard');
	}

	public function testAfterExceptionReturns403ForNotAppAdminException(): void
	{
		$userSession = $this->createMock(IUserSession::class);
		$permissionService = $this->createMock(PermissionService::class);
		$l10n = $this->createMock(IL10N::class);
		$middleware = new AppAdminMiddleware($userSession, $permissionService, $l10n);
		$exception = new NotAppAdminException('Access denied');

		$response = $middleware->afterException(new \stdClass(), 'dashboard', $exception);

		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
		$this->assertSame('403', $response->getTemplateName());
	}

	public function testAfterExceptionRethrowsUnknownException(): void
	{
		$userSession = $this->createMock(IUserSession::class);
		$permissionService = $this->createMock(PermissionService::class);
		$l10n = $this->createMock(IL10N::class);
		$middleware = new AppAdminMiddleware($userSession, $permissionService, $l10n);

		$this->expectException(\RuntimeException::class);
		$middleware->afterException(new \stdClass(), 'dashboard', new \RuntimeException('boom'));
	}
}
