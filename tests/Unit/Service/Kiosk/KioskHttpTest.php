<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service\Kiosk;

use OCA\ArbeitszeitCheck\Service\Kiosk\KioskHttp;
use OCP\AppFramework\Http;
use PHPUnit\Framework\TestCase;

final class KioskHttpTest extends TestCase
{
	public function testClockStampingDisabledIsForbidden(): void
	{
		self::assertSame(
			Http::STATUS_FORBIDDEN,
			KioskHttp::statusForCode('KIOSK_CLOCK_STAMPING_DISABLED'),
		);
	}

	public function testRateLimitedIsTooManyRequests(): void
	{
		self::assertSame(
			Http::STATUS_TOO_MANY_REQUESTS,
			KioskHttp::statusForCode('KIOSK_RATE_LIMITED'),
		);
	}

	public function testBruteForceCodes(): void
	{
		self::assertTrue(KioskHttp::shouldRegisterBruteForceAttempt('PIN_INVALID'));
		self::assertTrue(KioskHttp::shouldRegisterBruteForceAttempt('KIOSK_CREDENTIAL_UNKNOWN'));
		self::assertFalse(KioskHttp::shouldRegisterBruteForceAttempt('KIOSK_CLOCK_STAMPING_DISABLED'));
		self::assertFalse(KioskHttp::shouldRegisterBruteForceAttempt('KIOSK_INTERNAL_ERROR'));
	}
}
