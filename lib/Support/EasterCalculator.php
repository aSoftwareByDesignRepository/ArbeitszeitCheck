<?php

declare(strict_types=1);

/**
 * Easter Sunday calculation shared by all statutory holiday catalogs.
 *
 * Extracted from GermanStatutoryHolidayCatalog so the Austrian catalog can use
 * the identical algorithm. Output is byte-identical to the previous inline
 * implementation (guarded by the German golden-catalog test).
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Support;

final class EasterCalculator
{
	public static function easterDate(int $year): \DateTimeImmutable
	{
		$easterDays = \function_exists('easter_days') ? \easter_days($year) : self::easterDaysGauss($year);
		$march21 = new \DateTimeImmutable($year . '-03-21');

		return $march21->modify('+' . $easterDays . ' days');
	}

	/**
	 * Gauss algorithm for Easter (fallback when ext/calendar easter_days() is unavailable).
	 */
	private static function easterDaysGauss(int $year): int
	{
		$a = $year % 19;
		$b = (int)($year / 100);
		$c = $year % 100;
		$d = (int)($b / 4);
		$e = $b % 4;
		$f = (int)(($b + 8) / 25);
		$g = (int)(($b - $f + 1) / 3);
		$h = (19 * $a + $b - $d - $g + 15) % 30;
		$i = (int)($c / 4);
		$k = $c % 4;
		$l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
		$m = (int)(($a + 11 * $h + 22 * $l) / 451);
		$month = (int)(($h + $l - 7 * $m + 114) / 31);
		$day = (($h + $l - 7 * $m + 114) % 31) + 1;

		$march21 = new \DateTimeImmutable($year . '-03-21');
		$easterDate = new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));

		return (int)$march21->diff($easterDate)->days;
	}
}
