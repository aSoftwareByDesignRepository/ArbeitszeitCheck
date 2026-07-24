<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Support;

use OCA\ArbeitszeitCheck\Support\LaborLawProfile;
use OCA\ArbeitszeitCheck\Support\LaborLawProfileFactory;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;

class LaborLawProfileFactoryTest extends TestCase
{
	private function factoryWithCountry(string $configuredValue): LaborLawProfileFactory
	{
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')
			->with('arbeitszeitcheck', 'country', 'DE')
			->willReturn($configuredValue);

		return new LaborLawProfileFactory($config);
	}

	/**
	 * DE profile must equal the pre-profile hard-coded literals
	 * (Phase 0 behaviour-neutrality contract).
	 */
	public function testGermanProfileMatchesHistoricalLiterals(): void
	{
		$profile = LaborLawProfileFactory::profileForCountry('DE');

		$this->assertSame('DE', $profile->country);
		$this->assertSame(10.0, $profile->dailyMaxHoursDefault);
		$this->assertSame(11.0, $profile->minRestHoursDefault);
		$this->assertSame(48.0, $profile->weeklyAvgMaxHours);
		$this->assertSame(26, $profile->avgWindowWeeks);
		$this->assertSame(130, $profile->approximateWorkingDays());
		$this->assertNull($profile->weeklyAbsoluteMaxHours, 'DE has no absolute weekly cap');
		$this->assertSame(8.0, $profile->dailyAvgMaxHours);
		$this->assertSame(23, $profile->nightWindowStartHour);
		$this->assertSame(6, $profile->nightWindowEndHour);
		$this->assertSame(15, $profile->minBreakMinutes);

		$this->assertSame(0, $profile->requiredBreakMinutes(5.99));
		$this->assertSame(30, $profile->requiredBreakMinutes(6.0));
		$this->assertSame(30, $profile->requiredBreakMinutes(8.99));
		$this->assertSame(45, $profile->requiredBreakMinutes(9.0));
		$this->assertSame(45, $profile->requiredBreakMinutes(14.0));

		$this->assertSame('ArbZG §3', $profile->lawLabel('daily'));
		$this->assertSame('ArbZG §4', $profile->lawLabel('breaks'));
		$this->assertSame('ArbZG §5', $profile->lawLabel('rest'));
		$this->assertSame('ArbZG §6', $profile->lawLabel('night'));
		$this->assertSame('ArbZG §9', $profile->lawLabel('sundayHoliday'));
	}

	public function testAustrianProfileMatchesAzgArg(): void
	{
		$profile = LaborLawProfileFactory::profileForCountry('AT');

		$this->assertSame('AT', $profile->country);
		$this->assertSame(12.0, $profile->dailyMaxHoursDefault);
		$this->assertSame(11.0, $profile->minRestHoursDefault);
		$this->assertSame(48.0, $profile->weeklyAvgMaxHours);
		$this->assertSame(17, $profile->avgWindowWeeks);
		$this->assertSame(85, $profile->approximateWorkingDays());
		$this->assertSame(60.0, $profile->weeklyAbsoluteMaxHours);
		$this->assertNull($profile->dailyAvgMaxHours, 'AT has no 8h daily average rule');
		$this->assertSame(22, $profile->nightWindowStartHour);
		$this->assertSame(5, $profile->nightWindowEndHour);
		$this->assertSame(10, $profile->minBreakMinutes, 'AZG §11 portions may be 10 minutes');
		$this->assertSame([[15, 15], [10, 10, 10]], $profile->allowedBreakSplitPatterns);

		$this->assertSame(0, $profile->requiredBreakMinutes(5.5));
		$this->assertSame(30, $profile->requiredBreakMinutes(6.0));
		$this->assertSame(30, $profile->requiredBreakMinutes(12.0));

		$this->assertSame('AZG §9', $profile->lawLabel('daily'));
		$this->assertSame('AZG §11', $profile->lawLabel('breaks'));
		$this->assertSame('AZG §12', $profile->lawLabel('rest'));
		$this->assertSame('AZG §12b', $profile->lawLabel('night'));
		$this->assertSame('ARG §3', $profile->lawLabel('sundayHoliday'));
	}

	public function testSwissProfileMatchesArg(): void
	{
		$profile = LaborLawProfileFactory::profileForCountry('CH');

		$this->assertSame('CH', $profile->country);
		$this->assertSame(10.0, $profile->dailyMaxHoursDefault);
		$this->assertSame(11.0, $profile->minRestHoursDefault);
		$this->assertNull($profile->weeklyAvgMaxHours, 'CH has no ArbZG-style weekly average');
		$this->assertSame(0, $profile->avgWindowWeeks);
		$this->assertSame(0, $profile->approximateWorkingDays());
		$this->assertSame(45.0, $profile->weeklyAbsoluteMaxHours);
		$this->assertNull($profile->dailyAvgMaxHours);
		$this->assertSame(23, $profile->nightWindowStartHour);
		$this->assertSame(6, $profile->nightWindowEndHour);
		$this->assertSame(20, $profile->vacationDaysSuggestion);
		$this->assertSame(15, $profile->minBreakMinutes);
		$this->assertNull($profile->allowedBreakSplitPatterns);

		$this->assertSame(0, $profile->requiredBreakMinutes(5.49));
		$this->assertSame(15, $profile->requiredBreakMinutes(5.5));
		$this->assertSame(15, $profile->requiredBreakMinutes(6.9));
		$this->assertSame(30, $profile->requiredBreakMinutes(7.0));
		$this->assertSame(30, $profile->requiredBreakMinutes(8.9));
		$this->assertSame(60, $profile->requiredBreakMinutes(9.0));

		$this->assertSame('ArG Art. 9', $profile->lawLabel('daily'));
		$this->assertSame('ArG Art. 15', $profile->lawLabel('breaks'));
		$this->assertSame('ArG Art. 15a', $profile->lawLabel('rest'));
		$this->assertSame('ArG Art. 18', $profile->lawLabel('sundayHoliday'));
	}

	public function testSwissWeeklyAbsoluteFiftyHourOverride(): void
	{
		$profile = LaborLawProfileFactory::profileForCountry('CH', 50.0);
		$this->assertSame(50.0, $profile->weeklyAbsoluteMaxHours);

		$invalid = LaborLawProfileFactory::profileForCountry('CH', 48.0);
		$this->assertSame(45.0, $invalid->weeklyAbsoluteMaxHours, 'Non 45/50 values fall back to 45');
	}

	public function testResolveSwissWeeklyAbsoluteMaxFromConfig(): void
	{
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static function (string $app, string $key, $default = '') {
				if ($app === 'arbeitszeitcheck' && $key === 'country') {
					return 'CH';
				}
				if ($app === 'arbeitszeitcheck' && $key === LaborLawProfileFactory::CONFIG_KEY_WEEKLY_ABSOLUTE_MAX) {
					return '50';
				}
				return $default;
			}
		);
		$factory = new LaborLawProfileFactory($config);
		$this->assertSame(50.0, $factory->resolveSwissWeeklyAbsoluteMax());
		$this->assertSame(50.0, $factory->getProfile()->weeklyAbsoluteMaxHours);
	}

	public function testResolveSwissWeeklyAbsoluteMaxDefaultsToFortyFive(): void
	{
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static function (string $app, string $key, $default = '') {
				if ($app === 'arbeitszeitcheck' && $key === 'country') {
					return 'CH';
				}
				if ($app === 'arbeitszeitcheck' && $key === LaborLawProfileFactory::CONFIG_KEY_WEEKLY_ABSOLUTE_MAX) {
					return '45';
				}
				return $default;
			}
		);
		$factory = new LaborLawProfileFactory($config);
		$this->assertSame(45.0, $factory->resolveSwissWeeklyAbsoluteMax());
		$this->assertSame(45.0, $factory->getProfile()->weeklyAbsoluteMaxHours);

		$bogus = $this->createMock(IConfig::class);
		$bogus->method('getAppValue')->willReturnCallback(
			static function (string $app, string $key, $default = '') {
				if ($app === 'arbeitszeitcheck' && $key === LaborLawProfileFactory::CONFIG_KEY_WEEKLY_ABSOLUTE_MAX) {
					return '47';
				}
				if ($app === 'arbeitszeitcheck' && $key === 'country') {
					return 'CH';
				}
				return $default;
			}
		);
		$this->assertSame(45.0, (new LaborLawProfileFactory($bogus))->resolveSwissWeeklyAbsoluteMax());
	}

	public function testLawLabelFallsBackToDailyForUnknownRule(): void
	{
		$profile = LaborLawProfileFactory::profileForCountry('DE');
		$this->assertSame('ArbZG §3', $profile->lawLabel('nonexistent-rule'));
	}

	public function testBreakTiersAscendingIsSortedWithoutMutatingProfile(): void
	{
		$profile = LaborLawProfileFactory::profileForCountry('DE');
		$ascending = $profile->breakTiersAscending();

		$this->assertSame(6.0, $ascending[0]['afterHours']);
		$this->assertSame(9.0, $ascending[1]['afterHours']);
		// Original stays descending (first-match-wins evaluation order).
		$this->assertSame(9.0, $profile->breakTiers[0]['afterHours']);
	}

	public function testConfiguredCountryResolution(): void
	{
		$this->assertSame('DE', $this->factoryWithCountry('DE')->getConfiguredCountry());
		$this->assertSame('AT', $this->factoryWithCountry('AT')->getConfiguredCountry());
		$this->assertSame('AT', $this->factoryWithCountry(' at ')->getConfiguredCountry());
		$this->assertSame('CH', $this->factoryWithCountry('CH')->getConfiguredCountry());
		$this->assertSame('CH', $this->factoryWithCountry(' ch ')->getConfiguredCountry());
		// Missing / invalid values fall back to the historical German behaviour.
		$this->assertSame('DE', $this->factoryWithCountry('')->getConfiguredCountry());
		$this->assertSame('DE', $this->factoryWithCountry('FR')->getConfiguredCountry());
		$this->assertSame('DE', $this->factoryWithCountry('nonsense')->getConfiguredCountry());
	}

	public function testUnknownCountryYieldsGermanProfile(): void
	{
		$this->assertSame('CH', LaborLawProfileFactory::profileForCountry('CH')->country);
		$this->assertSame('DE', LaborLawProfileFactory::profileForCountry('FR')->country);
		$this->assertSame('DE', LaborLawProfileFactory::profileForCountry('')->country);
	}

	public function testProfileIsRequestCachedAndCacheClearable(): void
	{
		$config = $this->createMock(IConfig::class);
		$config->expects($this->exactly(2))
			->method('getAppValue')
			->with('arbeitszeitcheck', 'country', 'DE')
			->willReturn('AT');
		$factory = new LaborLawProfileFactory($config);

		$first = $factory->getProfile();
		$this->assertSame($first, $factory->getProfile(), 'Second call must hit the cache');

		$factory->clearCache();
		$second = $factory->getProfile();
		$this->assertInstanceOf(LaborLawProfile::class, $second);
		$this->assertSame('AT', $second->country);
	}

	public function testPerUserLaborLawCountryOverride(): void
	{
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static function (string $app, string $key, $default = '') {
				if ($app === 'arbeitszeitcheck' && $key === 'country') {
					return 'DE';
				}
				return $default;
			}
		);
		$mapper = $this->createMock(\OCA\ArbeitszeitCheck\Db\UserSettingsMapper::class);
		$mapper->method('getStringSetting')->willReturnCallback(
			static function (string $userId, string $key, string $default = '') {
				if ($userId === 'commuter' && $key === LaborLawProfileFactory::USER_SETTING_LABOR_LAW_COUNTRY) {
					return 'AT';
				}
				if ($userId === 'bogus' && $key === LaborLawProfileFactory::USER_SETTING_LABOR_LAW_COUNTRY) {
					return 'FR';
				}
				return $default;
			}
		);
		$factory = new LaborLawProfileFactory($config, $mapper);

		$this->assertSame('DE', $factory->getEffectiveCountry());
		$this->assertSame('DE', $factory->getEffectiveCountry(''));
		$this->assertSame('AT', $factory->getEffectiveCountry('commuter'));
		$this->assertSame('DE', $factory->getEffectiveCountry('bogus'), 'Invalid override falls back to instance');
		$this->assertSame('DE', $factory->getEffectiveCountry('nobody'));

		$this->assertSame('AT', $factory->getProfile('commuter')->country);
		$this->assertSame(10, $factory->getProfile('commuter')->minBreakMinutes);
		$this->assertSame('DE', $factory->getProfile('nobody')->country);
	}

	public function testWithoutUserSettingsMapperOverrideIsIgnored(): void
	{
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturn('DE');
		$factory = new LaborLawProfileFactory($config, null);

		$this->assertSame('DE', $factory->getEffectiveCountry('anyone'));
	}
}
