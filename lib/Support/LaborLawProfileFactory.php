<?php

declare(strict_types=1);

/**
 * Resolves the instance-wide LaborLawProfile from the app-config key
 * 'country' (default 'DE' — a missing key means the historical German
 * behaviour). The profile is instance-wide by design in v1 (documented
 * limitation E-9): per-user regions may cross the border for holidays,
 * but working-time law follows the instance country.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Support;

use OCP\IConfig;

class LaborLawProfileFactory
{
	public const CONFIG_KEY_COUNTRY = 'country';

	private ?LaborLawProfile $cached = null;

	public function __construct(
		private readonly IConfig $config,
	) {
	}

	public function getConfiguredCountry(): string
	{
		$country = strtoupper(trim(
			$this->config->getAppValue('arbeitszeitcheck', self::CONFIG_KEY_COUNTRY, RegionRegistry::COUNTRY_DE)
		));

		return RegionRegistry::isSupportedCountry($country) ? $country : RegionRegistry::COUNTRY_DE;
	}

	/**
	 * Profile for the configured instance country (request-cached).
	 */
	public function getProfile(): LaborLawProfile
	{
		if ($this->cached === null) {
			$this->cached = self::profileForCountry($this->getConfiguredCountry());
		}

		return $this->cached;
	}

	/**
	 * Drop the request cache (used after the admin saves a new country).
	 */
	public function clearCache(): void
	{
		$this->cached = null;
	}

	public static function profileForCountry(string $country): LaborLawProfile
	{
		return match (strtoupper(trim($country))) {
			RegionRegistry::COUNTRY_AT => self::austrianProfile(),
			RegionRegistry::COUNTRY_CH => self::swissProfile(),
			default => self::germanProfile(),
		};
	}

	/**
	 * German ArbZG — every value equals the pre-profile hard-coded literal
	 * (behaviour-neutral by construction, see snapshot tests).
	 */
	private static function germanProfile(): LaborLawProfile
	{
		return new LaborLawProfile(
			country: RegionRegistry::COUNTRY_DE,
			dailyMaxHoursDefault: 10.0,
			minRestHoursDefault: 11.0,
			breakTiers: [
				['afterHours' => 9.0, 'breakMinutes' => 45],
				['afterHours' => 6.0, 'breakMinutes' => 30],
			],
			weeklyAvgMaxHours: 48.0,
			avgWindowWeeks: 26,
			weeklyAbsoluteMaxHours: null,
			dailyAvgMaxHours: 8.0,
			nightWindowStartHour: 23,
			nightWindowEndHour: 6,
			lawShortLabels: [
				'daily' => 'ArbZG §3',
				'breaks' => 'ArbZG §4',
				'rest' => 'ArbZG §5',
				'weekly' => 'ArbZG §3',
				'night' => 'ArbZG §6',
				'sundayHoliday' => 'ArbZG §9',
				'dailyAvg' => 'ArbZG §3',
			],
			vacationDaysSuggestion: 25,
			minBreakMinutes: 15,
		);
	}

	/**
	 * Austrian AZG/ARG. Deliberately conservative where the AZG offers
	 * sector-specific extensions (v1 models the general regime):
	 *  - 12 h daily / 60 h weekly absolute maximum (AZG §9),
	 *  - 48 h weekly average over a 17-week window (AZG §9 Abs. 4),
	 *  - 30 min break after more than 6 h (AZG §11) — break divisibility
	 *    (2×15 / 3×10) is not modelled in v1; the total is enforced,
	 *  - 11 h daily rest (AZG §12),
	 *  - night window 22:00–05:00 (AZG §12b, informational),
	 *  - weekend/holiday rest per ARG §3.
	 */
	private static function austrianProfile(): LaborLawProfile
	{
		return new LaborLawProfile(
			country: RegionRegistry::COUNTRY_AT,
			dailyMaxHoursDefault: 12.0,
			minRestHoursDefault: 11.0,
			breakTiers: [
				['afterHours' => 6.0, 'breakMinutes' => 30],
			],
			weeklyAvgMaxHours: 48.0,
			avgWindowWeeks: 17,
			weeklyAbsoluteMaxHours: 60.0,
			dailyAvgMaxHours: null,
			nightWindowStartHour: 22,
			nightWindowEndHour: 5,
			lawShortLabels: [
				'daily' => 'AZG §9',
				'breaks' => 'AZG §11',
				'rest' => 'AZG §12',
				'weekly' => 'AZG §9',
				'night' => 'AZG §12b',
				'sundayHoliday' => 'ARG §3',
				'dailyAvg' => 'AZG §9',
			],
			vacationDaysSuggestion: 25,
			minBreakMinutes: 15,
		);
	}

	/**
	 * Swiss ArG (Arbeitsgesetz). Conservative general regime:
	 *  - 10 h daily maximum (ArG Art. 9 — weekly 45/50 is the primary cap),
	 *  - absolute weekly maximum 45 h (office/industrial default; 50 h is the
	 *    ArG exception for other sectors — admins may raise max via config),
	 *  - no averaging-window daily/weekly average rule in v1 (unlike ArbZG),
	 *  - break tiers ArG Art. 15: 15 min after 5.5 h, 30 after 7 h, 60 after 9 h,
	 *  - 11 h daily rest (ArG Art. 15a),
	 *  - night window 23:00–06:00 (ArG Art. 16 informational),
	 *  - Sunday/holiday rest ArG Art. 18,
	 *  - vacation suggestion 20 days (OrG Art. 329a — profile suggestion only).
	 */
	private static function swissProfile(): LaborLawProfile
	{
		return new LaborLawProfile(
			country: RegionRegistry::COUNTRY_CH,
			dailyMaxHoursDefault: 10.0,
			minRestHoursDefault: 11.0,
			breakTiers: [
				['afterHours' => 9.0, 'breakMinutes' => 60],
				['afterHours' => 7.0, 'breakMinutes' => 30],
				['afterHours' => 5.5, 'breakMinutes' => 15],
			],
			weeklyAvgMaxHours: null,
			avgWindowWeeks: 0,
			weeklyAbsoluteMaxHours: 45.0,
			dailyAvgMaxHours: null,
			nightWindowStartHour: 23,
			nightWindowEndHour: 6,
			lawShortLabels: [
				'daily' => 'ArG Art. 9',
				'breaks' => 'ArG Art. 15',
				'rest' => 'ArG Art. 15a',
				'weekly' => 'ArG Art. 9',
				'night' => 'ArG Art. 16',
				'sundayHoliday' => 'ArG Art. 18',
				'dailyAvg' => 'ArG Art. 9',
			],
			vacationDaysSuggestion: 20,
			minBreakMinutes: 15,
		);
	}
}
