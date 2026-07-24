<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Support;

use OCA\ArbeitszeitCheck\Support\AustrianStatutoryHolidayCatalog;
use OCA\ArbeitszeitCheck\Support\GermanStatutoryHolidayCatalog;
use OCA\ArbeitszeitCheck\Support\HolidayCatalogResolver;
use OCA\ArbeitszeitCheck\Support\RegionRegistry;
use OCA\ArbeitszeitCheck\Support\SwissStatutoryHolidayCatalog;
use PHPUnit\Framework\TestCase;

class HolidayCatalogResolverTest extends TestCase
{
	public function testDispatchesGermanLegacyCodesToGermanCatalog(): void
	{
		foreach (array_keys(RegionRegistry::regionsForCountry('DE')) as $region) {
			$this->assertSame(
				GermanStatutoryHolidayCatalog::class,
				HolidayCatalogResolver::catalogClassForRegion($region),
				"$region must resolve to the German catalog"
			);
		}
	}

	public function testDispatchesAustrianCodesToAustrianCatalog(): void
	{
		foreach (array_keys(RegionRegistry::regionsForCountry('AT')) as $region) {
			$this->assertSame(
				AustrianStatutoryHolidayCatalog::class,
				HolidayCatalogResolver::catalogClassForRegion($region),
				"$region must resolve to the Austrian catalog"
			);
		}
	}

	public function testDispatchesSwissCodesToSwissCatalog(): void
	{
		foreach (array_keys(RegionRegistry::regionsForCountry('CH')) as $region) {
			$this->assertSame(
				SwissStatutoryHolidayCatalog::class,
				HolidayCatalogResolver::catalogClassForRegion($region),
				"$region must resolve to the Swiss catalog"
			);
		}
	}

	/**
	 * Unknown / malformed codes fall back to the German catalog — this mirrors
	 * RegionRegistry::countryOf() and preserves the legacy behaviour for any
	 * stale values that might still sit in user preferences.
	 */
	public function testUnknownRegionFallsBackToGermanCatalog(): void
	{
		foreach (['', 'XX', 'FR-75'] as $code) {
			$this->assertSame(
				GermanStatutoryHolidayCatalog::class,
				HolidayCatalogResolver::catalogClassForRegion($code),
				"'$code' must fall back to the German catalog (legacy rule)"
			);
		}

		// Prefixed but unknown cantons still resolve to the Swiss catalog class
		// (empty entries); countryOf() is prefix-based by design.
		$this->assertSame(
			SwissStatutoryHolidayCatalog::class,
			HolidayCatalogResolver::catalogClassForRegion('CH-XX')
		);
		$this->assertSame(
			[],
			SwissStatutoryHolidayCatalog::getStatutoryHolidaysForRegionAndYear('CH-XX', 2026)
		);

		// countryOf() normalises case, so a lowercase Austrian/Swiss code still
		// dispatches correctly (validation happens at the write boundary).
		$this->assertSame(
			AustrianStatutoryHolidayCatalog::class,
			HolidayCatalogResolver::catalogClassForRegion('at-w')
		);
		$this->assertSame(
			SwissStatutoryHolidayCatalog::class,
			HolidayCatalogResolver::catalogClassForRegion('ch-zh')
		);
	}

	public function testStatutoryPassthroughMatchesCatalogOutput(): void
	{
		$this->assertSame(
			GermanStatutoryHolidayCatalog::getStatutoryHolidaysForRegionAndYear('BY', 2026),
			HolidayCatalogResolver::statutoryHolidaysForRegionAndYear('BY', 2026)
		);
		$this->assertSame(
			AustrianStatutoryHolidayCatalog::getStatutoryHolidaysForRegionAndYear('AT-W', 2026),
			HolidayCatalogResolver::statutoryHolidaysForRegionAndYear('AT-W', 2026)
		);
		$this->assertSame(
			SwissStatutoryHolidayCatalog::getStatutoryHolidaysForRegionAndYear('CH-ZH', 2026),
			HolidayCatalogResolver::statutoryHolidaysForRegionAndYear('CH-ZH', 2026)
		);
	}

	public function testSuggestionsPassthrough(): void
	{
		$this->assertSame(
			[],
			HolidayCatalogResolver::suggestedCompanyHolidaysForRegionAndYear('NW', 2026),
			'Germany deliberately ships no curated suggestions'
		);
		$this->assertSame(
			AustrianStatutoryHolidayCatalog::getSuggestedCompanyHolidaysForRegionAndYear('AT-K', 2026),
			HolidayCatalogResolver::suggestedCompanyHolidaysForRegionAndYear('AT-K', 2026)
		);
		$this->assertSame(
			SwissStatutoryHolidayCatalog::getSuggestedCompanyHolidaysForRegionAndYear('CH-ZH', 2026),
			HolidayCatalogResolver::suggestedCompanyHolidaysForRegionAndYear('CH-ZH', 2026)
		);
	}

	/**
	 * Every registry region must produce a non-empty statutory set through the
	 * resolver — a new region without catalog coverage would fail here.
	 * Federal Swiss floor is 3 days; DE/AT regions always have ≥9.
	 */
	public function testEveryRegisteredRegionHasStatutoryCoverage(): void
	{
		foreach (RegionRegistry::allRegionCodes() as $region) {
			$holidays = HolidayCatalogResolver::statutoryHolidaysForRegionAndYear($region, 2026);
			$min = RegionRegistry::countryOf($region) === RegionRegistry::COUNTRY_CH ? 3 : 9;
			$this->assertGreaterThanOrEqual($min, count($holidays), "$region must have statutory holidays");
		}
	}
}
