<?php

declare(strict_types=1);

/**
 * Kiosk identify required-key snapshot (T-MOB-06 / AC-P10).
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service\Kiosk;

use PHPUnit\Framework\TestCase;

class KioskIdentifyPayloadContractTest extends TestCase
{
	private const REQUIRED = [
		'sessionToken',
		'userId',
		'displayName',
		'status',
		'workedSecondsToday',
		'allowedActions',
	];

	private const FORBIDDEN = [
		'vacationRemaining',
		'vacationEntitlement',
		'vacationUnit',
		'displayBalance',
		'cumulativeBalance',
		'premiumSummary',
		'weekHoursRequired',
		'impliedDailyHours',
		'trafficLightState',
	];

	public function testIdentifyReturnShapeDocumentsRequiredKeys(): void
	{
		$src = file_get_contents(dirname(__DIR__, 4) . '/lib/Service/Kiosk/KioskAuthService.php');
		$this->assertNotFalse($src);
		foreach (self::REQUIRED as $key) {
			$this->assertStringContainsString("'$key'", $src, "identify payload must include $key");
		}
		foreach (self::FORBIDDEN as $key) {
			$this->assertStringNotContainsString("'$key'", $src, "identify must not include $key");
		}
	}

	public function testPhpdocReturnTypeListsExactRequiredKeys(): void
	{
		$src = file_get_contents(dirname(__DIR__, 4) . '/lib/Service/Kiosk/KioskAuthService.php');
		$this->assertNotFalse($src);
		$this->assertMatchesRegularExpression(
			'/@return array\{sessionToken: string, userId: string, displayName: string, status: string, workedSecondsToday: int, allowedActions: list<string>\}/',
			$src,
			'identify @return must stay the frozen companion contract'
		);
	}

	public function testCompanionGoldenFixtureMirrorsRequiredKeys(): void
	{
		$fixture = dirname(__DIR__, 4) . '/../../../mobile/arbeitszeitcheck-kiosk/src/test/fixtures/kioskIdentifyGolden.ts';
		if (!is_file($fixture)) {
			// Workspace layout without mobile tree (CI subset) — skip softly.
			$this->assertTrue(true);
			return;
		}
		$ts = file_get_contents($fixture);
		$this->assertNotFalse($ts);
		foreach (self::REQUIRED as $key) {
			$this->assertStringContainsString($key, $ts);
		}
		foreach (self::FORBIDDEN as $key) {
			$this->assertStringNotContainsString($key . ':', $ts);
		}
	}
}
