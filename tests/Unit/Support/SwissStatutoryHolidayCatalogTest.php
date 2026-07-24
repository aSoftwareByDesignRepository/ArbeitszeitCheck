<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Support;

use OCA\ArbeitszeitCheck\Support\HolidayCatalogResolver;
use OCA\ArbeitszeitCheck\Support\RegionRegistry;
use OCA\ArbeitszeitCheck\Support\SwissStatutoryHolidayCatalog;
use PHPUnit\Framework\TestCase;

class SwissStatutoryHolidayCatalogTest extends TestCase
{
	public function testAllTwentySixCantonsHaveFederalCore(): void
	{
		$cantons = array_keys(RegionRegistry::regionsForCountry('CH'));
		$this->assertCount(26, $cantons);

		for ($year = 2024; $year <= 2030; $year++) {
			foreach ($cantons as $canton) {
				$holidays = SwissStatutoryHolidayCatalog::getStatutoryHolidaysForRegionAndYear($canton, $year);
				$this->assertArrayHasKey(sprintf('%04d-01-01', $year), $holidays, "$canton New Year");
				$this->assertArrayHasKey(sprintf('%04d-08-01', $year), $holidays, "$canton National Day");
				$this->assertArrayHasKey(sprintf('%04d-12-25', $year), $holidays, "$canton Christmas");
				$this->assertGreaterThanOrEqual(3, count($holidays), "$canton $year");
			}
		}
	}

	public function testZurich2026IncludesHalfDayCityHolidays(): void
	{
		// Easter Sunday 2026 = 5 April → Good Friday 3 Apr, Easter Monday 6 Apr,
		// Ascension 14 May, Whit Monday 25 May.
		// Sechseläuten 2026 = third Monday of April = 20 Apr.
		// Knabenschiessen 2026 = second Monday of September = 14 Sep.
		$entries = SwissStatutoryHolidayCatalog::getStatutoryHolidayEntriesForRegionAndYear('CH-ZH', 2026);

		$this->assertSame('half', $entries['2026-04-20']['kind'] ?? null, 'Sechseläuten must be half');
		$this->assertSame('Sechseläuten', $entries['2026-04-20']['name'] ?? null);
		$this->assertSame('half', $entries['2026-09-14']['kind'] ?? null, 'Knabenschiessen must be half');
		$this->assertSame('Knabenschiessen', $entries['2026-09-14']['name'] ?? null);

		$this->assertSame('full', $entries['2026-01-01']['kind']);
		$this->assertArrayHasKey('2026-01-02', $entries, 'Berchtoldstag in ZH');
		$this->assertArrayHasKey('2026-05-01', $entries, 'Labour Day in ZH');
		$this->assertArrayHasKey('2026-04-03', $entries, 'Good Friday in ZH');

		$half = SwissStatutoryHolidayCatalog::getStatutoryHalfDayHolidaysForRegionAndYear('CH-ZH', 2026);
		$this->assertCount(2, $half);
		$this->assertArrayHasKey('2026-04-20', $half);
		$this->assertArrayHasKey('2026-09-14', $half);
	}

	public function testGenevaJeuneGenevois2026(): void
	{
		// First Sunday of Sep 2026 = 6 Sep → Thursday = 10 Sep.
		$entries = SwissStatutoryHolidayCatalog::getStatutoryHolidayEntriesForRegionAndYear('CH-GE', 2026);
		$this->assertArrayHasKey('2026-09-10', $entries);
		$this->assertSame('Jeûne genevois', $entries['2026-09-10']['name']);
		$this->assertSame('full', $entries['2026-09-10']['kind']);
		$this->assertArrayNotHasKey('2026-04-20', $entries, 'Sechseläuten is Zurich-only');
	}

	public function testVaudLundiDuJeune2026(): void
	{
		// Third Sunday of Sep 2026 = 20 Sep → Monday = 21 Sep.
		$entries = SwissStatutoryHolidayCatalog::getStatutoryHolidayEntriesForRegionAndYear('CH-VD', 2026);
		$this->assertArrayHasKey('2026-09-21', $entries);
		$this->assertSame('Lundi du Jeûne', $entries['2026-09-21']['name']);
	}

	public function testTicinoHasNoGoodFridayButHasEpiphany(): void
	{
		$holidays = SwissStatutoryHolidayCatalog::getStatutoryHolidaysForRegionAndYear('CH-TI', 2026);
		$this->assertArrayNotHasKey('2026-04-03', $holidays, 'Good Friday not statutory in TI');
		$this->assertArrayHasKey('2026-01-06', $holidays, 'Epiphany in TI');
		$this->assertArrayHasKey('2026-04-06', $holidays, 'Easter Monday in TI');
	}

	public function testSuggestionsAreNeverInStatutorySet(): void
	{
		foreach (['CH-ZH', 'CH-GE', 'CH-TI'] as $canton) {
			$statutory = SwissStatutoryHolidayCatalog::getStatutoryHolidaysForRegionAndYear($canton, 2026);
			$suggestions = SwissStatutoryHolidayCatalog::getSuggestedCompanyHolidaysForRegionAndYear($canton, 2026);
			$this->assertArrayHasKey('2026-12-24', $suggestions);
			$this->assertArrayHasKey('2026-12-31', $suggestions);
			$this->assertArrayNotHasKey('2026-12-24', $statutory);
			$this->assertArrayNotHasKey('2026-12-31', $statutory);
		}
	}

	public function testResolverDispatchesSwissCatalogAndKinds(): void
	{
		$this->assertSame(
			SwissStatutoryHolidayCatalog::class,
			HolidayCatalogResolver::catalogClassForRegion('CH-ZH')
		);
		$kinds = HolidayCatalogResolver::statutoryHolidayKindsForRegionAndYear('CH-ZH', 2026);
		$this->assertSame('half', $kinds['2026-04-20']);
		$this->assertSame('full', $kinds['2026-01-01']);

		$deKinds = HolidayCatalogResolver::statutoryHolidayKindsForRegionAndYear('NW', 2026);
		foreach ($deKinds as $kind) {
			$this->assertSame('full', $kind, 'DE statutory must always be full');
		}
	}

	public function testUnknownRegionYieldsEmptyCatalog(): void
	{
		$this->assertSame([], SwissStatutoryHolidayCatalog::getStatutoryHolidaysForRegionAndYear('CH-XX', 2026));
		$this->assertSame([], SwissStatutoryHolidayCatalog::getSuggestedCompanyHolidaysForRegionAndYear('NW', 2026));
	}
}
