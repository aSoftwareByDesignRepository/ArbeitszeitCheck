<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Middleware;

use OCA\ArbeitszeitCheck\Controller\FakeControllerForMiddlewareTest;
use OCA\ArbeitszeitCheck\Middleware\SessionCsrfGuardMiddleware;
use OCA\ArbeitszeitCheck\Middleware\SessionCsrfRequiredException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Utility\IControllerMethodReflector;
use OCP\IL10N;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

class SessionCsrfGuardMiddlewareTest extends TestCase
{
	private function makeMiddleware(
		IRequest $request,
		?IControllerMethodReflector $reflector = null,
	): SessionCsrfGuardMiddleware {
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$reflector ??= $this->createMock(IControllerMethodReflector::class);
		$reflector->method('hasAnnotation')->willReturn(false);
		return new SessionCsrfGuardMiddleware($request, $reflector, $l10n);
	}

	public function testAllowsGetWithoutCsrf(): void
	{
		$request = $this->createMock(IRequest::class);
		$request->method('getMethod')->willReturn('GET');
		$request->expects($this->never())->method('passesCSRFCheck');

		$mw = $this->makeMiddleware($request);
		$mw->beforeController(new FakeControllerForMiddlewareTest(), 'clockIn');
		$this->addToAssertionCount(1);
	}

	public function testSkipsWhenNoSessionCookie(): void
	{
		$request = $this->createMock(IRequest::class);
		$request->method('getMethod')->willReturn('POST');
		$request->method('getCookie')->willReturn(null);
		$request->expects($this->never())->method('passesCSRFCheck');

		$mw = $this->makeMiddleware($request);
		$mw->beforeController(new FakeControllerForMiddlewareTest(), 'clockIn');
		$this->addToAssertionCount(1);
	}

	public function testRequiresCsrfWhenSessionCookiePresent(): void
	{
		$request = $this->createMock(IRequest::class);
		$request->method('getMethod')->willReturn('POST');
		$request->method('getCookie')->willReturnCallback(static function (string $name) {
			return $name === 'nc_session_id' ? 'sess' : null;
		});
		$request->method('passesCSRFCheck')->willReturn(false);

		$mw = $this->makeMiddleware($request);
		$this->expectException(SessionCsrfRequiredException::class);
		$mw->beforeController(new FakeControllerForMiddlewareTest(), 'clockIn');
	}

	public function testAllowsWhenSessionCookieAndCsrfPass(): void
	{
		$request = $this->createMock(IRequest::class);
		$request->method('getMethod')->willReturn('POST');
		$request->method('getCookie')->willReturnCallback(static function (string $name) {
			return $name === 'nc_session_id' ? 'sess' : null;
		});
		$request->method('passesCSRFCheck')->willReturn(true);

		$mw = $this->makeMiddleware($request);
		$mw->beforeController(new FakeControllerForMiddlewareTest(), 'clockIn');
		$this->addToAssertionCount(1);
	}

	public function testRequiresCsrfEvenWithForgedBasicHeaderWhenSessionCookiePresent(): void
	{
		$request = $this->createMock(IRequest::class);
		$request->method('getMethod')->willReturn('POST');
		$request->method('getHeader')->willReturnCallback(static function (string $name) {
			return strtolower($name) === 'authorization' ? 'Basic aW52YWxpZDppbnZhbGlk' : '';
		});
		$request->method('getCookie')->willReturnCallback(static function (string $name) {
			return $name === 'nc_session_id' ? 'sess' : null;
		});
		$request->method('passesCSRFCheck')->willReturn(false);

		$mw = $this->makeMiddleware($request);
		$this->expectException(SessionCsrfRequiredException::class);
		$mw->beforeController(new FakeControllerForMiddlewareTest(), 'clockIn');
	}

	public function testSkipsPublicPageEvenWithSessionCookie(): void
	{
		$request = $this->createMock(IRequest::class);
		$request->method('getMethod')->willReturn('POST');
		$request->method('getCookie')->willReturnCallback(static function (string $name) {
			return $name === 'nc_session_id' ? 'sess' : null;
		});
		$request->expects($this->never())->method('passesCSRFCheck');

		$mw = $this->makeMiddleware($request);
		$mw->beforeController(new FakeControllerForMiddlewareTest(), 'publicPing');
		$this->addToAssertionCount(1);
	}

	public function testAfterExceptionReturnsJsonForApiPath(): void
	{
		$request = $this->createMock(IRequest::class);
		$request->method('getPathInfo')->willReturn('/apps/arbeitszeitcheck/api/clock/in');
		$request->method('getHeader')->willReturn('');

		$mw = $this->makeMiddleware($request);
		$response = $mw->afterException(
			new FakeControllerForMiddlewareTest(),
			'clockIn',
			new SessionCsrfRequiredException('Security check failed. Reload the page and try again.')
		);
		$this->assertInstanceOf(JSONResponse::class, $response);
		$this->assertSame(Http::STATUS_PRECONDITION_FAILED, $response->getStatus());
		$data = $response->getData();
		$this->assertSame('csrf_failed', $data['error_code']);
		$this->assertFalse($data['success']);
	}
}
