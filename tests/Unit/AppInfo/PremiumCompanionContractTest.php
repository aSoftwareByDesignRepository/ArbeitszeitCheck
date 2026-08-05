<?php

declare(strict_types=1);

/**
 * Companion / additive-API contract for BANSS Phase D premiums.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\AppInfo;

use OCA\ArbeitszeitCheck\Support\PremiumPolicy;
use PHPUnit\Framework\TestCase;

class PremiumCompanionContractTest extends TestCase
{
	public function testDefaultStackingIsMaxSingleRate(): void
	{
		$preset = PremiumPolicy::atStarterPreset();
		$this->assertSame(PremiumPolicy::STACKING_MAX_SINGLE, $preset['stacking']);
		$this->assertSame('hours_only', $preset['currency_mode']);
		$policy = PremiumPolicy::fromValidated($preset);
		$this->assertSame(PremiumPolicy::STACKING_MAX_SINGLE, $policy->getStacking());
	}

	public function testWidgetServiceExposesAdditivePremiumSummaryKey(): void
	{
		$src = file_get_contents(dirname(__DIR__, 3) . '/lib/Service/DashboardWidgetDataService.php');
		$this->assertNotFalse($src);
		$this->assertStringContainsString("'premiumSummary'", $src);
		$this->assertStringContainsString('buildPremiumSummaryForWidget', $src);
		// Disabled path must be null-tolerant for old clients (additive).
		$this->assertStringContainsString('return null;', $src);
	}

	public function testCapabilitiesDoNotBumpCompanionMinForPremiums(): void
	{
		$path = dirname(__DIR__, 3) . '/lib/Capabilities.php';
		$this->assertFileExists($path);
		$src = file_get_contents($path);
		$this->assertNotFalse($src);
		// companion.min must stay exact "1" for additive-only Phase D.
		$this->assertStringContainsString("'arbeitszeitcheck.companion.min' => 1", $src);
		$this->assertStringContainsString("'vacationUnitAware' => true", $src);
		$this->assertStringNotContainsString('premium_surcharges', $src);
	}

	public function testPremiumReportRouteIsRegistered(): void
	{
		$routes = file_get_contents(dirname(__DIR__, 3) . '/appinfo/routes.php');
		$this->assertNotFalse($routes);
		$this->assertStringContainsString("report#premium", $routes);
		$this->assertStringContainsString('/api/reports/premium', $routes);
	}

	public function testDeNightWindowDiffersFromAtStarter(): void
	{
		$at = PremiumPolicy::atStarterPreset();
		$de = PremiumPolicy::deTariffStarterPreset();
		$atNight = null;
		$deNight = null;
		foreach ($at['categories'] as $c) {
			if (($c['id'] ?? '') === 'night') {
				$atNight = $c;
			}
		}
		foreach ($de['categories'] as $c) {
			if (($c['id'] ?? '') === 'night') {
				$deNight = $c;
			}
		}
		$this->assertNotNull($atNight);
		$this->assertNotNull($deNight);
		$this->assertSame('22:00', $atNight['window_start']);
		$this->assertSame('23:00', $deNight['window_start']);
	}
}
