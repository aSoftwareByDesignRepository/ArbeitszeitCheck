<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Service;

use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Db\AuditLogMapper;
use OCA\ArbeitszeitCheck\Db\UserSettingsMapper;
use OCA\ArbeitszeitCheck\Exception\BusinessRuleException;
use OCA\ArbeitszeitCheck\Exception\TimeCaptureForbiddenException;
use OCP\IConfig;
use OCP\IL10N;

/**
 * Organisation-wide and per-employee control over clock stamping vs manual time entry.
 *
 * Organisation defaults apply to every employee. Per-employee settings can only
 * restrict further (they cannot re-enable a method the organisation disabled).
 * Both methods are enabled by default at every layer. At least one effective method
 * must remain available for each employee and for the organisation as a whole.
 */
class TimeCaptureMethodService
{
	public function __construct(
		private readonly UserSettingsMapper $userSettingsMapper,
		private readonly AuditLogMapper $auditLogMapper,
		private readonly IConfig $config,
		private readonly IL10N $l10n,
	) {
	}

	/**
	 * @return array{clockStampingEnabled: bool, manualTimeEntryEnabled: bool}
	 */
	public function getOrganizationDefaults(): array
	{
		return [
			'clockStampingEnabled' => $this->isOrganizationClockStampingEnabled(),
			'manualTimeEntryEnabled' => $this->isOrganizationManualTimeEntryEnabled(),
		];
	}

	/**
	 * @param array{clockStampingEnabled?: bool, manualTimeEntryEnabled?: bool} $settings
	 * @return array{clockStampingEnabled: bool, manualTimeEntryEnabled: bool}
	 */
	public function setOrganizationDefaults(array $settings, string $actorUserId): array
	{
		$before = $this->getOrganizationDefaults();
		$clockEnabled = array_key_exists('clockStampingEnabled', $settings)
			? (bool)$settings['clockStampingEnabled']
			: $before['clockStampingEnabled'];
		$manualEnabled = array_key_exists('manualTimeEntryEnabled', $settings)
			? (bool)$settings['manualTimeEntryEnabled']
			: $before['manualTimeEntryEnabled'];

		if (!$clockEnabled && !$manualEnabled) {
			throw new BusinessRuleException(
				$this->l10n->t('At least one time recording method must stay enabled for the organisation.')
			);
		}

		$this->persistOrganizationBoolean(Constants::CONFIG_CLOCK_STAMPING_ENABLED, $clockEnabled);
		$this->persistOrganizationBoolean(Constants::CONFIG_MANUAL_TIME_ENTRY_ENABLED, $manualEnabled);
		$after = $this->getOrganizationDefaults();

		if ($before !== $after) {
			$this->auditLogMapper->logAction(
				$actorUserId,
				'organization_time_capture_methods_updated',
				'app_settings',
				null,
				$before,
				$after,
				$actorUserId
			);
		}

		return $after;
	}

	public function isOrganizationClockStampingEnabled(): bool
	{
		return $this->config->getAppValue('arbeitszeitcheck', Constants::CONFIG_CLOCK_STAMPING_ENABLED, '1') === '1';
	}

	public function isOrganizationManualTimeEntryEnabled(): bool
	{
		return $this->config->getAppValue('arbeitszeitcheck', Constants::CONFIG_MANUAL_TIME_ENTRY_ENABLED, '1') === '1';
	}

	/**
	 * Per-employee preference without organisation ceiling (for admin edit forms).
	 *
	 * @return array{clockStampingEnabled: bool, manualTimeEntryEnabled: bool}
	 */
	public function getUserPreferences(string $userId): array
	{
		return [
			'clockStampingEnabled' => $this->getUserClockPreference($userId),
			'manualTimeEntryEnabled' => $this->getUserManualPreference($userId),
		];
	}

	public function isClockStampingEnabled(string $userId): bool
	{
		return $this->isOrganizationClockStampingEnabled()
			&& $this->getUserClockPreference($userId);
	}

	public function isManualTimeEntryEnabled(string $userId): bool
	{
		return $this->isOrganizationManualTimeEntryEnabled()
			&& $this->getUserManualPreference($userId);
	}

	/**
	 * Effective settings after organisation and per-employee rules.
	 *
	 * @return array{clockStampingEnabled: bool, manualTimeEntryEnabled: bool, manualTimeEntriesRequireApproval: bool}
	 */
	public function getSettings(string $userId): array
	{
		return [
			'clockStampingEnabled' => $this->isClockStampingEnabled($userId),
			'manualTimeEntryEnabled' => $this->isManualTimeEntryEnabled($userId),
			'manualTimeEntriesRequireApproval' => $this->config->getAppValue(
				'arbeitszeitcheck',
				Constants::CONFIG_MANUAL_TIME_ENTRIES_REQUIRE_APPROVAL,
				'0'
			) === '1',
		];
	}

	/**
	 * @param array{clockStampingEnabled?: bool, manualTimeEntryEnabled?: bool} $settings
	 * @return array{clockStampingEnabled: bool, manualTimeEntryEnabled: bool}
	 */
	public function setSettings(string $userId, array $settings, string $actorUserId): array
	{
		$clockPreference = array_key_exists('clockStampingEnabled', $settings)
			? (bool)$settings['clockStampingEnabled']
			: $this->getUserClockPreference($userId);
		$manualPreference = array_key_exists('manualTimeEntryEnabled', $settings)
			? (bool)$settings['manualTimeEntryEnabled']
			: $this->getUserManualPreference($userId);

		if (!$this->wouldHaveEffectiveMethod($clockPreference, $manualPreference)) {
			throw new BusinessRuleException(
				$this->l10n->t('At least one time recording method must stay enabled for each employee.')
			);
		}

		$before = $this->getSettings($userId);
		$this->persistBooleanSetting($userId, Constants::SETTING_CLOCK_STAMPING_ENABLED, $clockPreference);
		$this->persistBooleanSetting($userId, Constants::SETTING_MANUAL_TIME_ENTRY_ENABLED, $manualPreference);
		$after = $this->getSettings($userId);

		if ($before !== $after) {
			$this->auditLogMapper->logAction(
				$userId,
				'user_time_capture_methods_updated',
				'user',
				null,
				$before,
				$after,
				$actorUserId
			);
		}

		return $after;
	}

	public function validateUserPreferences(string $userId, ?bool $clockPreference = null, ?bool $manualPreference = null): void
	{
		$clock = $clockPreference ?? $this->getUserClockPreference($userId);
		$manual = $manualPreference ?? $this->getUserManualPreference($userId);
		if (!$this->wouldHaveEffectiveMethod($clock, $manual)) {
			throw new BusinessRuleException(
				$this->l10n->t('At least one time recording method must stay enabled for each employee.')
			);
		}
	}

	public function assertClockStampingAllowed(string $userId): void
	{
		if ($this->isClockStampingEnabled($userId)) {
			return;
		}

		throw new TimeCaptureForbiddenException(
			$this->l10n->t('Clock in/out (stamping) is not enabled for your account. Please contact your administrator.'),
			TimeCaptureForbiddenException::CODE_CLOCK_STAMPING_DISABLED,
		);
	}

	public function assertManualTimeEntryAllowed(string $userId): void
	{
		if ($this->isManualTimeEntryEnabled($userId)) {
			return;
		}

		throw new TimeCaptureForbiddenException(
			$this->l10n->t('Manual time entries are not enabled for your account. Please contact your administrator.'),
			TimeCaptureForbiddenException::CODE_MANUAL_TIME_ENTRY_DISABLED,
		);
	}

	private function getUserClockPreference(string $userId): bool
	{
		return $this->userSettingsMapper->getBooleanSetting(
			$userId,
			Constants::SETTING_CLOCK_STAMPING_ENABLED,
			true
		);
	}

	private function getUserManualPreference(string $userId): bool
	{
		return $this->userSettingsMapper->getBooleanSetting(
			$userId,
			Constants::SETTING_MANUAL_TIME_ENTRY_ENABLED,
			true
		);
	}

	private function wouldHaveEffectiveMethod(bool $clockPreference, bool $manualPreference): bool
	{
		return ($this->isOrganizationClockStampingEnabled() && $clockPreference)
			|| ($this->isOrganizationManualTimeEntryEnabled() && $manualPreference);
	}

	private function persistBooleanSetting(string $userId, string $key, bool $enabled): void
	{
		if ($enabled) {
			// Default-on: remove explicit row so legacy installs stay enabled.
			$this->userSettingsMapper->deleteSetting($userId, $key);
			return;
		}

		$this->userSettingsMapper->setSetting($userId, $key, '0');
	}

	private function persistOrganizationBoolean(string $configKey, bool $enabled): void
	{
		$this->config->setAppValue('arbeitszeitcheck', $configKey, $enabled ? '1' : '0');
	}
}
