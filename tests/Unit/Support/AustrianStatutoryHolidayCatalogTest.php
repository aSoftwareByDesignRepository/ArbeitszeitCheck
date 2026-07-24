<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Support;

use OCA\ArbeitszeitCheck\Support\AustrianStatutoryHolidayCatalog;
use OCA\ArbeitszeitCheck\Support\RegionRegistry;
use PHPUnit\Framework\TestCase;

class AustrianStatutoryHolidayCatalogTest extends TestCase
{
	/**
	 * ARG §7: exactly 13 nationwide statutory holidays, identical for all nine
	 * Bundesländer, across a span of years.
	 */
	public function testThirteenIdenticalStatutoryDaysForAllRegionsAndYears(): void
	{
		$regions = array_keys(RegionRegistry::regionsForCountry('AT'));
		$this->assertCount(9, $regions);

		for ($year = 2024; $year <= 2032; $year++) {
			$reference = AustrianStatutoryHolidayCatalog::getStatutoryHolidaysForRegionAndYear('AT-W', $year);
			$this->assertCount(13, $reference, "AT statutory count mismatch in $year");
			foreach ($regions as $region) {
				$this->assertSame(
					$reference,
					AustrianStatutoryHolidayCatalog::getStatutoryHolidaysForRegionAndYear($region, $year),
					"AT statutory holidays must be identical for $region in $year"
				);
			}
		}
	}

	public function testStatutoryDays2026(): void
	{
		// Easter Sunday 2026 = 5 April.
		$expected = [
			'2026-01-01' => 'New Year',
			'2026-01-06' => 'Epiphany',
			'2026-04-06' => 'Easter Monday',
			'2026-05-01' => 'Labour Day (State Holiday)',
			'2026-05-14' => 'Ascension',
			'2026-05-25' => 'Whit Monday',
			'2026-06-04' => 'Corpus Christi',
			'2026-08-15' => 'Assumption Day',
			'2026-10-26' => 'Austrian National Day',
			'2026-11-01' => 'All Saints',
			'2026-12-08' => 'Immaculate Conception',
			'2026-12-25' => 'Christmas',
			'2026-12-26' => 'St. Stephen\'s Day',
		];

		$this->assertSame(
			$expected,
			AustrianStatutoryHolidayCatalog::getStatutoryHolidaysForRegionAndYear('AT-OOE', 2026)
		);
	}

	/**
	 * E-1: Good Friday has not been statutory since 2019 (ARG §7a) — it must
	 * never appear in the statutory set, only in the suggestions.
	 */
	public function testGoodFridayIsNeverStatutoryButAlwaysSuggested(): void
	{
		$goodFridays = [
			2024 => '2024-03-29',
			2025 => '2025-04-18',
			2026 => '2026-04-03',
			2027 => '2027-03-26',
		];
		foreach ($goodFridays as $year => $date) {
			foreach (array_keys(RegionRegistry::regionsForCountry('AT')) as $region) {
				$statutory = AustrianStatutoryHolidayCatalog::getStatutoryHolidaysForRegionAndYear($region, $year);
				$this->assertArrayNotHasKey($date, $statutory, "Good Friday must not be statutory ($region $year)");

				$suggestions = AustrianStatutoryHolidayCatalog::getSuggestedCompanyHolidaysForRegionAndYear($region, $year);
				$this->assertArrayHasKey($date, $suggestions, "Good Friday must be suggested ($region $year)");
				$this->assertSame('Good Friday', $suggestions[$date]);
			}
		}
	}

	/**
	 * E-2: patron saint days are suggestions only, per region.
	 */
	public function testRegionalPatronSaintSuggestions2026(): void
	{
		$cases = [
			'AT-B' => ['2026-11-11' => 'St. Martin\'s Day'],
			'AT-K' => ['2026-03-19' => 'St. Joseph\'s Day', '2026-10-10' => 'Carinthian Plebiscite Day'],
			'AT-NOE' => ['2026-11-15' => 'St. Leopold\'s Day'],
			'AT-OOE' => ['2026-05-04' => 'St. Florian\'s Day'],
			'AT-S' => ['2026-09-24' => 'St. Rupert\'s Day'],
			'AT-ST' => ['2026-03-19' => 'St. Joseph\'s Day'],
			'AT-T' => ['2026-03-19' => 'St. Joseph\'s Day'],
			'AT-V' => ['2026-03-19' => 'St. Joseph\'s Day'],
			'AT-W' => ['2026-11-15' => 'St. Leopold\'s Day'],
		];

		foreach ($cases as $region => $expectedDays) {
			$suggestions = AustrianStatutoryHolidayCatalog::getSuggestedCompanyHolidaysForRegionAndYear($region, 2026);
			foreach ($expectedDays as $date => $name) {
				$this->assertArrayHasKey($date, $suggestions, "$region must suggest $name");
				$this->assertSame($name, $suggestions[$date]);
			}
			// Good Friday + patron day(s) + 24/31 December.
			$this->assertCount(3 + count($expectedDays), $suggestions, "$region suggestion count");
			$this->assertSame('Christmas Eve', $suggestions['2026-12-24']);
			$this->assertSame('New Year\'s Eve', $suggestions['2026-12-31']);
		}
	}

	/**
	 * Invariant: statutory ⇔ gesetzlich — the suggestion set never overlaps
	 * the statutory set.
	 */
	public function testSuggestionsNeverOverlapStatutory(): void
	{
		foreach (array_keys(RegionRegistry::regionsForCountry('AT')) as $region) {
			for ($year = 2024; $year <= 2032; $year++) {
				$statutory = AustrianStatutoryHolidayCatalog::getStatutoryHolidaysForRegionAndYear($region, $year);
				$suggested = AustrianStatutoryHolidayCatalog::getSuggestedCompanyHolidaysForRegionAndYear($region, $year);
				$this->assertSame(
					[],
					array_intersect_key($statutory, $suggested),
					"Statutory and suggested days must not overlap ($region $year)"
				);
			}
		}
	}

	public function testAllDatesAreWellFormedAndSorted(): void
	{
		foreach ([2024, 2026, 2030] as $year) {
			$statutory = AustrianStatutoryHolidayCatalog::getStatutoryHolidaysForRegionAndYear('AT-K', $year);
			$dates = array_keys($statutory);
			$sorted = $dates;
			sort($sorted);
			$this->assertSame($sorted, $dates, 'Statutory dates must be sorted ascending');
			foreach ($dates as $date) {
				$this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $date);
				$this->assertStringStartsWith((string)$year, $date);
			}
		}
	}
}
