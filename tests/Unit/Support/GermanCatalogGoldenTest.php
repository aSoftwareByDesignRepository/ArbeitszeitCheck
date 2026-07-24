<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Support;

use OCA\ArbeitszeitCheck\Support\GermanStatutoryHolidayCatalog;
use PHPUnit\Framework\TestCase;

/**
 * Golden snapshot of the German statutory catalog, 16 Bundesländer × 2024–2032.
 *
 * Pinned BEFORE the EasterCalculator extraction / HolidayCatalogInterface
 * adoption (DACH Phase 0) so any refactoring of the catalog internals that
 * changes a single date or name fails loudly. Individual dates are verified
 * against public sources in GermanStatutoryHolidayCatalogTest — this test
 * guards the full surface at once.
 *
 * If this test fails after an INTENTIONAL catalog correction: verify the new
 * output against official sources, regenerate the hash with the snippet in
 * the failure message, and document the change in CHANGELOG.md.
 */
class GermanCatalogGoldenTest extends TestCase
{
	private const GOLDEN_SHA256 = 'dd709d6c771cac4d065a5424fbbb127c434a25940202d0271ba6d8e684c9f8c3';
	private const GOLDEN_TOTAL_ROWS = 1548;
	private const STATES = ['BW', 'BY', 'BE', 'BB', 'HB', 'HH', 'HE', 'MV', 'NI', 'NW', 'RP', 'SL', 'SN', 'ST', 'SH', 'TH'];

	private static function buildSnapshot(): array
	{
		$snapshot = [];
		for ($year = 2024; $year <= 2032; $year++) {
			foreach (self::STATES as $state) {
				$snapshot[$year][$state] = GermanStatutoryHolidayCatalog::getStatutoryHolidaysForStateAndYear($state, $year);
			}
		}

		return $snapshot;
	}

	public function testGoldenSnapshotHashUnchanged(): void
	{
		$snapshot = self::buildSnapshot();
		$json = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		$this->assertSame(
			self::GOLDEN_TOTAL_ROWS,
			array_sum(array_map(static fn (array $y): int => array_sum(array_map('count', $y)), $snapshot)),
			'Total statutory-day count changed'
		);
		$this->assertSame(
			self::GOLDEN_SHA256,
			hash('sha256', $json),
			"German catalog output diverged from the golden snapshot.\n"
			. "If the change is an intentional, source-verified correction, regenerate with:\n"
			. "  hash('sha256', json_encode(\$snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))\n"
			. 'and update GOLDEN_SHA256 + GOLDEN_TOTAL_ROWS.'
		);
	}

	/**
	 * Both entry points (legacy state method and the HolidayCatalogInterface
	 * adapter) must return byte-identical data.
	 */
	public function testInterfaceAdapterIsByteIdenticalToLegacyMethod(): void
	{
		foreach (self::STATES as $state) {
			for ($year = 2024; $year <= 2032; $year++) {
				$this->assertSame(
					GermanStatutoryHolidayCatalog::getStatutoryHolidaysForStateAndYear($state, $year),
					GermanStatutoryHolidayCatalog::getStatutoryHolidaysForRegionAndYear($state, $year)
				);
			}
		}
	}
}
