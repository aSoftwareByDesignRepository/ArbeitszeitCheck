<?php

declare(strict_types=1);

/**
 * Contract for country statutory holiday reference catalogs.
 *
 * Catalogs are used only to seed at_holidays — never as a runtime overlay for
 * working-day math (see HolidayService header). Names are English msgids that
 * are translated with IL10N at seed time.
 *
 * Invariant: getStatutoryHolidaysForRegionAndYear() may only contain days that
 * are statutory (gesetzlich) for the given region. Days that are merely common
 * practice (collective agreements, half days, patron saints) belong into
 * getSuggestedCompanyHolidaysForRegionAndYear() and are never auto-seeded.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Support;

interface HolidayCatalogInterface
{
	/**
	 * @return array<string,string> date (Y-m-d) => English name (l10n msgid)
	 */
	public static function getStatutoryHolidaysForRegionAndYear(string $region, int $year): array;

	/**
	 * Non-statutory suggestions (e.g. Good Friday in Austria, patron saints,
	 * 24/31 December). Never seeded automatically — offered to admins in the
	 * holidays UI to add as company holidays with one click.
	 *
	 * @return array<string,string> date (Y-m-d) => English name (l10n msgid)
	 */
	public static function getSuggestedCompanyHolidaysForRegionAndYear(string $region, int $year): array;
}
