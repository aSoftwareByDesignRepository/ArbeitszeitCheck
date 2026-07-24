<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Support;

use OCA\ArbeitszeitCheck\Support\EasterCalculator;
use PHPUnit\Framework\TestCase;

class EasterCalculatorTest extends TestCase
{
	/**
	 * Known Easter Sunday dates (Gregorian, Western), including the special
	 * Gauss correction years and the latest possible date (25 April, 2038).
	 */
	public function testKnownEasterDates(): void
	{
		$expected = [
			2024 => '2024-03-31',
			2025 => '2025-04-20',
			2026 => '2026-04-05',
			2027 => '2027-03-28',
			2028 => '2028-04-16',
			2029 => '2029-04-01',
			2030 => '2030-04-21',
			2031 => '2031-04-13',
			2032 => '2032-03-28',
			2038 => '2038-04-25', // latest possible Easter
			2049 => '2049-04-18', // Gauss exception year
			2076 => '2076-04-19', // Gauss exception year
			2106 => '2106-04-18',
		];

		foreach ($expected as $year => $date) {
			$this->assertSame(
				$date,
				EasterCalculator::easterDate($year)->format('Y-m-d'),
				"Easter $year mismatch"
			);
		}
	}

	public function testEasterIsAlwaysASundayInWindow(): void
	{
		for ($year = 2020; $year <= 2120; $year++) {
			$easter = EasterCalculator::easterDate($year);
			$this->assertSame(7, (int)$easter->format('N'), "Easter $year must be a Sunday");
			$monthDay = $easter->format('m-d');
			$this->assertGreaterThanOrEqual('03-22', $monthDay, "Easter $year before 22 March");
			$this->assertLessThanOrEqual('04-25', $monthDay, "Easter $year after 25 April");
		}
	}

	public function testResultIsMidnightImmutable(): void
	{
		$easter = EasterCalculator::easterDate(2026);
		$this->assertInstanceOf(\DateTimeImmutable::class, $easter);
		$this->assertSame('00:00:00', $easter->format('H:i:s'));
	}

	/**
	 * The Gauss fallback must agree with ext/calendar easter_days() so servers
	 * without the extension behave identically.
	 */
	public function testGaussFallbackMatchesExtCalendar(): void
	{
		if (!\function_exists('easter_days')) {
			$this->markTestSkipped('ext/calendar not available — fallback is the only path anyway.');
		}

		$gauss = new \ReflectionMethod(EasterCalculator::class, 'easterDaysGauss');
		for ($year = 1970; $year <= 2150; $year++) {
			$this->assertSame(
				\easter_days($year),
				$gauss->invoke(null, $year),
				"Gauss fallback diverges from easter_days() in $year"
			);
		}
	}
}
