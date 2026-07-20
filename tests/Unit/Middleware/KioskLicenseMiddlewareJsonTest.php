<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Middleware;

use OCA\ArbeitszeitCheck\Middleware\KioskLicenseMiddleware;
use OCA\ArbeitszeitCheck\Service\Kiosk\KioskErrorMessages;
use OCA\ArbeitszeitCheck\Service\Kiosk\KioskSettingsService;
use OCA\ArbeitszeitCheck\Service\Kiosk\KioskTerminalService;
use OCA\ArbeitszeitCheck\Service\LicenseService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TooManyRequestsResponse;
use OCP\IRequest;
use OCP\L10N\IFactory;
use OCP\Security\Bruteforce\MaxDelayReached;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class KioskLicenseMiddlewareJsonTest extends TestCase
{
	private function middleware(IRequest $request): KioskLicenseMiddleware
	{
		$l10n = $this->createMock(\OCP\IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $t) => $t);
		$factory = $this->createMock(IFactory::class);
		$factory->method('get')->willReturn($l10n);

		$messages = new KioskErrorMessages($l10n);

		return new KioskLicenseMiddleware(
			$request,
			$this->createMock(KioskSettingsService::class),
			$this->createMock(LicenseService::class),
			$this->createMock(KioskTerminalService::class),
			$factory,
			$this->createMock(LoggerInterface::class),
			$messages,
		);
	}

	private function kioskRequest(): IRequest
	{
		$request = $this->createMock(IRequest::class);
		$request->method('getPathInfo')->willReturn('/apps/arbeitszeitcheck/api/kiosk/identify');
		return $request;
	}

	public function testAfterControllerRewritesHtml429ToJson(): void
	{
		$mw = $this->middleware($this->kioskRequest());
		$controller = $this->createMock(\OCP\AppFramework\Controller::class);
		$html = new TooManyRequestsResponse();

		$out = $mw->afterController($controller, 'identify', $html);
		self::assertInstanceOf(JSONResponse::class, $out);
		self::assertSame(Http::STATUS_TOO_MANY_REQUESTS, $out->getStatus());
		$data = $out->getData();
		self::assertSame('KIOSK_RATE_LIMITED', $data['error']);
		self::assertNotSame('', $data['message']);
		self::assertNotSame('KIOSK_RATE_LIMITED', $data['message']);
	}

	public function testAfterExceptionMapsMaxDelayReachedToJson(): void
	{
		$mw = $this->middleware($this->kioskRequest());
		$controller = $this->createMock(\OCP\AppFramework\Controller::class);

		$out = $mw->afterException($controller, 'identify', new MaxDelayReached());
		self::assertInstanceOf(JSONResponse::class, $out);
		self::assertSame(Http::STATUS_TOO_MANY_REQUESTS, $out->getStatus());
		self::assertSame('KIOSK_RATE_LIMITED', $out->getData()['error']);
	}

	public function testAfterControllerLeavesJson429Alone(): void
	{
		$mw = $this->middleware($this->kioskRequest());
		$controller = $this->createMock(\OCP\AppFramework\Controller::class);
		$json = new JSONResponse(['success' => false, 'error' => 'KIOSK_RATE_LIMITED'], Http::STATUS_TOO_MANY_REQUESTS);

		$out = $mw->afterController($controller, 'identify', $json);
		self::assertSame($json, $out);
	}
}
