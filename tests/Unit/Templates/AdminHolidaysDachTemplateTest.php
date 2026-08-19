<?php

declare(strict_types=1);

/**
 * Contract: Holidays admin page exposes the full DACH Country & Region card (§5.1/§5.3).
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\Templates;

use PHPUnit\Framework\TestCase;

class AdminHolidaysDachTemplateTest extends TestCase
{
	private function templateSource(): string
	{
		$path = dirname(__DIR__, 3) . '/templates/admin-holidays.php';
		$contents = file_get_contents($path);
		$this->assertNotFalse($contents);
		return $contents;
	}

	public function testHolidaysPageUsesRegionRegistryForCountryAndRegions(): void
	{
		$src = $this->templateSource();
		$this->assertStringContainsString('use OCA\\ArbeitszeitCheck\\Support\\RegionRegistry;', $src);
		$this->assertStringContainsString('RegionRegistry::regionsForCountry', $src);
		$this->assertStringContainsString('RegionRegistry::supportedCountries', $src);
		$this->assertStringContainsString('RegionRegistry::defaultRegionForCountry', $src);
		$this->assertStringNotContainsString("'BW', 'BY', 'BE'", $src);
	}

	public function testHolidaysPageRendersCountryRadiosAndLiveRegion(): void
	{
		$src = $this->templateSource();
		$this->assertStringContainsString('holiday-country-region-title', $src);
		$this->assertStringContainsString('name="holidayCountry"', $src);
		$this->assertStringContainsString('holiday-country-<?php p(strtolower($countryCode)); ?>', $src);
		$this->assertStringContainsString('azc-country-card__radio', $src);
		$this->assertStringContainsString('holiday-country-region-live', $src);
		$this->assertStringContainsString('aria-live="polite"', $src);
		$this->assertStringContainsString('azc-holidays-region-data', $src);
		$this->assertStringContainsString('holiday-default-state', $src);
		$this->assertStringContainsString('azc-country-grid', $src);
		$this->assertStringContainsString('COUNTRY_DE', $src);
		$this->assertStringContainsString('COUNTRY_AT', $src);
		$this->assertStringContainsString('COUNTRY_CH', $src);
	}

	public function testCalendarViewerKeepsCrossBorderOptgroups(): void
	{
		$src = $this->templateSource();
		$this->assertStringContainsString('<optgroup label=', $src);
		$this->assertStringContainsString('holiday-state-select', $src);
		$this->assertStringContainsString('holiday-suggestions-section', $src);
		$this->assertStringContainsString('holiday-good-friday-note', $src);
	}

	public function testHolidaysPageLinksToCalendarSubscriptionSettings(): void
	{
		$src = $this->templateSource();
		$this->assertStringContainsString('calendarSubscriptionUrl', $src);
		$this->assertStringContainsString('holiday-calendar-subscription-link', $src);
		$this->assertStringContainsString('Open calendar subscription settings', $src);
		$this->assertStringContainsString('Calendar subscription', $src);
	}

	public function testParameterizedRegionLiveStringProvidesPlaceholderArg(): void
	{
		$src = $this->templateSource();
		$this->assertStringContainsString(
			"\$l->t('Region list updated. Default region: %s', ['%s'])",
			$src,
			'PHP $l->t() with %s must pass a placeholder arg or the holidays page fatals (vsprintf).'
		);
	}
}
