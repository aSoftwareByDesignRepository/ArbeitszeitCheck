<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Templates;

use OCA\ArbeitszeitCheck\Support\LaborLawProfileFactory;
use OCA\ArbeitszeitCheck\Support\RegionRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Web bootstrap must expose allowedBreakSplitPatterns (parity with mobile Capabilities).
 */
final class ComplianceParamsBootstrapContractTest extends TestCase
{
	public function testAustrianProfileExposesSplitPatternsForWebBootstrap(): void
	{
		$profile = LaborLawProfileFactory::profileForCountry(RegionRegistry::COUNTRY_AT);
		$params = [
			'country' => $profile->country,
			'breakTiers' => $profile->breakTiersAscending(),
			'minBreakMinutes' => $profile->minBreakMinutes,
			'maxDailyHoursDefault' => $profile->dailyMaxHoursDefault,
			'allowedBreakSplitPatterns' => $profile->allowedBreakSplitPatterns,
		];
		$this->assertSame('AT', $params['country']);
		$this->assertSame([[15, 15], [10, 10, 10]], $params['allowedBreakSplitPatterns']);
		$this->assertSame(10, $params['minBreakMinutes']);
	}

	public function testGermanAndSwissProfilesAreSumOnly(): void
	{
		foreach ([RegionRegistry::COUNTRY_DE, RegionRegistry::COUNTRY_CH] as $country) {
			$profile = LaborLawProfileFactory::profileForCountry($country);
			$this->assertNull(
				$profile->allowedBreakSplitPatterns,
				$country . ' must remain sum-only on the web bootstrap'
			);
		}
	}
}
