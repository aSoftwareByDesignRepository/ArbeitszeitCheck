<?php

declare(strict_types=1);

/**
 * Admin settings for the arbeitszeitcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Settings;

use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Service\FrontEndAssetService;
use OCA\ArbeitszeitCheck\Support\LaborLawProfileFactory;
use OCA\ArbeitszeitCheck\Support\RegionRegistry;
use OCP\App\IAppManager;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IAppConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\ISettings;
use OCP\Util;

class AdminSettings implements ISettings
{
	private IAppConfig $appConfig;
	private IL10N $l10n;
	private IGroupManager $groupManager;
	private IAppManager $appManager;
	private IURLGenerator $urlGenerator;

	public function __construct(
		IAppConfig $appConfig,
		IL10N $l10n,
		IGroupManager $groupManager,
		IAppManager $appManager,
		IURLGenerator $urlGenerator,
	) {
		$this->appConfig = $appConfig;
		$this->l10n = $l10n;
		$this->groupManager = $groupManager;
		$this->appManager = $appManager;
		$this->urlGenerator = $urlGenerator;
	}

	/**
	 * Render the admin settings form that appears in the global
	 * Nextcloud “Administration → ArbeitszeitCheck” area.
	 *
	 * This uses the same template and data structure as the in-app
	 * admin settings route so admins see a single, consistent place
	 * for all core configuration (including country, region, and holidays).
	 *
	 * Parity contract: every field the in-app settings page posts
	 * (country, germanState, weeklyAbsoluteMaxHours, vacationDaysSuggestion,
	 * break auto-fallback, time-entry approval flags, …)
	 * MUST be present here — otherwise a save from NC Admin silently resets
	 * real config (e.g. Swiss 50h → 45h, break fallback off → on).
	 */
	public function getForm(): TemplateResponse
	{
		FrontEndAssetService::registerPage(
			'admin-settings',
			'admin-settings',
			['common/projectcheck', 'common/country-region'],
		);

		$requireSubstituteJson = $this->appConfig->getAppValueString('require_substitute_types', '[]');
		$requireSubstituteTypes = json_decode($requireSubstituteJson, true);
		if (!is_array($requireSubstituteTypes)) {
			$requireSubstituteTypes = [];
		}

		$country = $this->readConfiguredCountry();
		$settings = [
			'autoComplianceCheck' => $this->appConfig->getAppValueString('auto_compliance_check', '1') === '1',
			'realtimeComplianceCheck' => $this->appConfig->getAppValueString('realtime_compliance_check', '1') === '1',
			'complianceStrictMode' => $this->appConfig->getAppValueString('compliance_strict_mode', '0') === '1',
			'enableViolationNotifications' => $this->appConfig->getAppValueString('enable_violation_notifications', '1') === '1',
			'breakAutoFallbackEnabled' => $this->appConfig->getAppValueString('break_auto_fallback_enabled', '1') === '1',
			'breakAutoFallbackMinutes' => max(15, min(720, (int)$this->appConfig->getAppValueString('break_auto_fallback_minutes', '180'))),
			'breakAutoFallbackFlexWindowStart' => max(0, min(23, (int)$this->appConfig->getAppValueString('break_auto_fallback_flex_window_start', '11'))),
			'breakAutoFallbackFlexWindowEnd' => max(1, min(24, (int)$this->appConfig->getAppValueString('break_auto_fallback_flex_window_end', '16'))),
			'exportMidnightSplitEnabled' => $this->appConfig->getAppValueString('export_midnight_split_enabled', '1') === '1',
			'monthClosureEnabled' => $this->appConfig->getAppValueString(Constants::CONFIG_MONTH_CLOSURE_ENABLED, '0') === '1',
			'monthClosureGraceDaysAfterEom' => max(0, min(90, (int)$this->appConfig->getAppValueString(Constants::CONFIG_MONTH_CLOSURE_GRACE_DAYS_AFTER_EOM, '0'))),
			'requireSubstituteTypes' => $requireSubstituteTypes,
			'sendIcalApprovedAbsences' => $this->appConfig->getAppValueString('send_ical_approved_absences', '1') === '1',
			'sendIcalToSubstitute' => $this->appConfig->getAppValueString('send_ical_to_substitute', '0') === '1',
			'sendIcalToManagers' => $this->appConfig->getAppValueString('send_ical_to_managers', '0') === '1',
			'sendEmailSubstitutionRequest' => $this->appConfig->getAppValueString('send_email_substitution_request', '1') === '1',
			'sendEmailSubstituteApprovedToEmployee' => $this->appConfig->getAppValueString('send_email_substitute_approved_to_employee', '1') === '1',
			'sendEmailSubstituteApprovedToManager' => $this->appConfig->getAppValueString('send_email_substitute_approved_to_manager', '1') === '1',
			'maxDailyHours' => (float)$this->appConfig->getAppValueString('max_daily_hours', $this->profileMaxDailyHoursDefault()),
			'minRestPeriod' => (float)$this->appConfig->getAppValueString('min_rest_period', $this->profileMinRestHoursDefault()),
			'country' => $country,
			'germanState' => $this->readConfiguredDefaultRegion(),
			'weeklyAbsoluteMaxHours' => $this->readConfiguredSwissWeeklyAbsoluteMax(),
			'vacationDaysSuggestion' => LaborLawProfileFactory::profileForCountry($country)->vacationDaysSuggestion,
			'statutoryAutoReseed' => $this->appConfig->getAppValueString('statutory_auto_reseed', '1') === '1',
			'retentionPeriod' => (int)$this->appConfig->getAppValueString('retention_period', '2'),
			'defaultWorkingHours' => (float)$this->appConfig->getAppValueString('default_working_hours', '8'),
			'timeEntryChangesRequireApproval' => $this->appConfig->getAppValueString(Constants::CONFIG_TIME_ENTRY_CHANGES_REQUIRE_APPROVAL, '0') === '1',
			'manualTimeEntriesRequireApproval' => $this->appConfig->getAppValueString(Constants::CONFIG_MANUAL_TIME_ENTRIES_REQUIRE_APPROVAL, '0') === '1',
			'clockStampingEnabled' => $this->appConfig->getAppValueString(Constants::CONFIG_CLOCK_STAMPING_ENABLED, '1') === '1',
			'manualTimeEntryEnabled' => $this->appConfig->getAppValueString(Constants::CONFIG_MANUAL_TIME_ENTRY_ENABLED, '1') === '1',
			'accessAllowedGroups' => $this->readAccessAllowedGroups(),
			'appAdminUserIds' => $this->readConfiguredAppAdminUserIds(),
			'projectCheckIntegrationEnabled' => $this->appManager->isEnabledForUser('projectcheck')
				&& $this->appConfig->getAppValueString(Constants::CONFIG_PROJECTCHECK_INTEGRATION_ENABLED, Constants::CONFIG_PROJECTCHECK_INTEGRATION_DEFAULT) === '1',
		];

		$projectCheckAvailable = $this->appManager->isEnabledForUser('projectcheck');

		return new TemplateResponse('arbeitszeitcheck', 'admin-settings', [
			'settings' => $settings,
			'availableGroups' => $this->getAvailableGroups(),
			'availableAppAdmins' => $this->getAvailableAppAdmins(),
			'l' => $this->l10n,
			'urlGenerator' => $this->urlGenerator,
			'settingsShell' => 'nextcloud',
			'inAppAdminSettingsUrl' => $this->urlGenerator->linkToRoute('arbeitszeitcheck.admin.settings'),
			'supportUsUrl' => $this->urlGenerator->linkToRoute('arbeitszeitcheck.admin.supportUs'),
			'projectCheckAvailable' => $projectCheckAvailable,
			'requesttoken' => Util::callRegister(),
		]);
	}

	private function readConfiguredCountry(): string
	{
		$country = strtoupper(trim($this->appConfig->getAppValueString('country', RegionRegistry::COUNTRY_DE)));

		return RegionRegistry::isSupportedCountry($country) ? $country : RegionRegistry::COUNTRY_DE;
	}

	/**
	 * Swiss ArG Art. 9: only 45 or 50 are valid; anything else → 45.
	 * Must match AdminController::getConfiguredSwissWeeklyAbsoluteMax().
	 */
	private function readConfiguredSwissWeeklyAbsoluteMax(): int
	{
		$raw = (int)$this->appConfig->getAppValueString(
			LaborLawProfileFactory::CONFIG_KEY_WEEKLY_ABSOLUTE_MAX,
			'45'
		);

		return $raw === 50 ? 50 : 45;
	}

	private function profileMaxDailyHoursDefault(): string
	{
		return (string)LaborLawProfileFactory::profileForCountry($this->readConfiguredCountry())->dailyMaxHoursDefault;
	}

	private function profileMinRestHoursDefault(): string
	{
		return (string)LaborLawProfileFactory::profileForCountry($this->readConfiguredCountry())->minRestHoursDefault;
	}

	private function readConfiguredDefaultRegion(): string
	{
		$country = $this->readConfiguredCountry();
		$stored = $this->appConfig->getAppValueString(
			'german_state',
			RegionRegistry::defaultRegionForCountry($country)
		);

		return RegionRegistry::resolveDefaultRegionForCountry($country, $stored);
	}

	public function getSection(): string
	{
		return 'arbeitszeitcheck';
	}

	public function getPriority(): int
	{
		return 50;
	}

	/**
	 * @return list<string>
	 */
	private function readAccessAllowedGroups(): array
	{
		$decoded = $this->appManager->getAppRestriction('arbeitszeitcheck');
		$out = [];
		foreach ($decoded as $groupId) {
			$candidate = trim((string)$groupId);
			if ($candidate === '') {
				continue;
			}
			$out[$candidate] = true;
		}
		return array_keys($out);
	}

	/**
	 * @return list<array{id: string, displayName: string}>
	 */
	private function getAvailableGroups(): array
	{
		$out = [];
		foreach ($this->groupManager->search('') as $group) {
			$gid = trim((string)$group->getGID());
			if ($gid === '') {
				continue;
			}
			$displayName = trim((string)$group->getDisplayName());
			$out[] = ['id' => $gid, 'displayName' => $displayName !== '' ? $displayName : $gid];
		}
		usort($out, static fn (array $a, array $b): int => strcasecmp($a['displayName'], $b['displayName']));
		return $out;
	}

	/**
	 * @return list<string>
	 */
	private function readConfiguredAppAdminUserIds(): array
	{
		$raw = $this->appConfig->getAppValueString(Constants::CONFIG_APP_ADMIN_USER_IDS, '[]');
		$decoded = json_decode($raw, true);
		if (!is_array($decoded)) {
			return [];
		}

		$unique = [];
		foreach ($decoded as $candidate) {
			$userId = trim((string)$candidate);
			if ($userId === '' || isset($unique[$userId])) {
				continue;
			}
			if (!$this->groupManager->isAdmin($userId)) {
				continue;
			}
			$unique[$userId] = true;
		}

		return array_keys($unique);
	}

	/**
	 * @return list<array{id: string, displayName: string}>
	 */
	private function getAvailableAppAdmins(): array
	{
		$out = [];
		$adminGroup = $this->groupManager->get('admin');
		if ($adminGroup === null) {
			return [];
		}

		foreach ($adminGroup->getUsers() as $adminUser) {
			$userId = trim((string)$adminUser->getUID());
			if ($userId === '') {
				continue;
			}
			$displayName = trim((string)$adminUser->getDisplayName());
			$out[] = [
				'id' => $userId,
				'displayName' => $displayName !== '' ? $displayName : $userId,
			];
		}

		usort($out, static fn (array $a, array $b): int => strcasecmp($a['displayName'], $b['displayName']));
		return $out;
	}
}