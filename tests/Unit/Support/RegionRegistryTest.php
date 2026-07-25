<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Support;

use OCA\ArbeitszeitCheck\Support\RegionRegistry;
use PHPUnit\Framework\TestCase;

class RegionRegistryTest extends TestCase
{
	public function testSupportedCountriesAreDACH(): void
	{
		$this->assertSame(['DE', 'AT', 'CH'], RegionRegistry::supportedCountries());
		$this->assertTrue(RegionRegistry::isSupportedCountry('DE'));
		$this->assertTrue(RegionRegistry::isSupportedCountry('at'));
		$this->assertTrue(RegionRegistry::isSupportedCountry('CH'));
		$this->assertTrue(RegionRegistry::isSupportedCountry('ch'));
		$this->assertFalse(RegionRegistry::isSupportedCountry(''));
		$this->assertFalse(RegionRegistry::isSupportedCountry('XX'));
		$this->assertFalse(RegionRegistry::isSupportedCountry('FR'));
	}

	public function testRegionCountsPerCountry(): void
	{
		$this->assertCount(16, RegionRegistry::regionsForCountry('DE'));
		$this->assertCount(9, RegionRegistry::regionsForCountry('AT'));
		$this->assertCount(26, RegionRegistry::regionsForCountry('CH'));
		$this->assertSame([], RegionRegistry::regionsForCountry('FR'));
		$this->assertCount(51, RegionRegistry::allRegions());
	}

	public function testCountryOfFollowsLegacyDashRule(): void
	{
		$this->assertSame('DE', RegionRegistry::countryOf('NW'));
		$this->assertSame('DE', RegionRegistry::countryOf('bw'));
		$this->assertSame('AT', RegionRegistry::countryOf('AT-W'));
		$this->assertSame('AT', RegionRegistry::countryOf('at-noe'));
		$this->assertSame('CH', RegionRegistry::countryOf('CH-ZH'));
		$this->assertSame('CH', RegionRegistry::countryOf('ch-ge'));
	}

	public function testEveryRegionCodeBelongsToItsOwnCountryList(): void
	{
		foreach (RegionRegistry::supportedCountries() as $country) {
			foreach (array_keys(RegionRegistry::regionsForCountry($country)) as $code) {
				$this->assertTrue(RegionRegistry::isValidRegion($code), "$code must be valid");
				$this->assertSame($country, RegionRegistry::countryOf($code), "$code must map to $country");
			}
		}
	}

	public function testRegionCodesFitTheStateColumn(): void
	{
		// at_holidays.state is VARCHAR(8) — every code must fit without schema change.
		foreach (RegionRegistry::allRegionCodes() as $code) {
			$this->assertLessThanOrEqual(8, strlen($code), "$code exceeds VARCHAR(8)");
			$this->assertSame(strtoupper($code), $code, "$code must be uppercase");
		}
	}

	public function testDefaultRegionsPreserveHistoricBehaviour(): void
	{
		$this->assertSame('NW', RegionRegistry::defaultRegionForCountry('DE'));
		$this->assertSame('AT-W', RegionRegistry::defaultRegionForCountry('AT'));
		$this->assertSame('CH-ZH', RegionRegistry::defaultRegionForCountry('CH'));
		// Unknown country falls back to the German default (E-7 safe fallback).
		$this->assertSame('NW', RegionRegistry::defaultRegionForCountry('XX'));

		foreach (RegionRegistry::supportedCountries() as $country) {
			$default = RegionRegistry::defaultRegionForCountry($country);
			$this->assertTrue(RegionRegistry::isValidRegion($default));
			$this->assertSame($country, RegionRegistry::countryOf($default));
		}
	}

	public function testInvalidRegionCodesAreRejected(): void
	{
		foreach (['', 'XX', 'AT', 'AT-', 'AT-X', 'DE-NW', 'CH', 'CH-XX'] as $invalid) {
			$this->assertFalse(RegionRegistry::isValidRegion($invalid), "$invalid must be invalid");
		}
		// Normalization: lowercase and padded input is accepted.
		$this->assertTrue(RegionRegistry::isValidRegion(' nw '));
		$this->assertTrue(RegionRegistry::isValidRegion('at-w'));
		$this->assertTrue(RegionRegistry::isValidRegion('ch-zh'));
	}

	public function testLabelsAreNonEmptyAndUnique(): void
	{
		$labels = RegionRegistry::allRegions();
		$this->assertSame(count($labels), count(array_unique($labels)), 'Region labels must be unique');
		foreach ($labels as $code => $label) {
			$this->assertNotSame('', trim($label), "Label for $code must not be empty");
		}
		$this->assertSame('Nordrhein‑Westfalen', RegionRegistry::regionLabel('NW'));
		$this->assertSame('Wien', RegionRegistry::regionLabel('AT-W'));
		$this->assertSame('Zurich', RegionRegistry::regionLabel('CH-ZH'));
		// Unknown codes echo back the input (display fallback, never crashes).
		$this->assertSame('XX', RegionRegistry::regionLabel('XX'));
	}

	public function testCountryLabelsPresentForAllSupportedCountries(): void
	{
		$labels = RegionRegistry::countryLabels();
		foreach (RegionRegistry::supportedCountries() as $country) {
			$this->assertArrayHasKey($country, $labels);
			$this->assertNotSame('', trim($labels[$country]));
		}
		$this->assertSame('Switzerland', $labels['CH']);
	}

	public function testResolveDefaultRegionForCountryRejectsCrossBorderOrphans(): void
	{
		$this->assertSame('AT-W', RegionRegistry::resolveDefaultRegionForCountry('AT', 'NW'));
		$this->assertSame('AT-W', RegionRegistry::resolveDefaultRegionForCountry('AT', ''));
		$this->assertSame('AT-W', RegionRegistry::resolveDefaultRegionForCountry('AT', 'XX'));
		$this->assertSame('AT-OOE', RegionRegistry::resolveDefaultRegionForCountry('AT', 'AT-OOE'));
		$this->assertSame('CH-ZH', RegionRegistry::resolveDefaultRegionForCountry('CH', 'NW'));
		$this->assertSame('CH-BE', RegionRegistry::resolveDefaultRegionForCountry('CH', 'CH-BE'));
		$this->assertSame('NW', RegionRegistry::resolveDefaultRegionForCountry('DE', 'NW'));
		$this->assertSame('BY', RegionRegistry::resolveDefaultRegionForCountry('DE', 'BY'));
		$this->assertSame('NW', RegionRegistry::resolveDefaultRegionForCountry('DE', 'AT-W'));
		// Unknown country → DE fallback chain.
		$this->assertSame('NW', RegionRegistry::resolveDefaultRegionForCountry('FR', 'AT-W'));
	}
}
