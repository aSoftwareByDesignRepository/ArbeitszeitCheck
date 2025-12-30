<?php

declare(strict_types=1);

/**
 * Settings controller for the arbeitszeitcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Controller;

use OCA\ArbeitszeitCheck\Db\UserSettingsMapper;
use OCA\ArbeitszeitCheck\Db\AuditLogMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\DB\Exception as DBException;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\IL10N;
use OCP\Util;

/**
 * SettingsController
 */
class SettingsController extends Controller
{
	private IUserSession $userSession;
	private UserSettingsMapper $userSettingsMapper;
	private AuditLogMapper $auditLogMapper;
	private IL10N $l10n;

	public function __construct(
		string $appName,
		IRequest $request,
		IUserSession $userSession,
		UserSettingsMapper $userSettingsMapper,
		AuditLogMapper $auditLogMapper,
		IL10N $l10n
	) {
		parent::__construct($appName, $request);
		$this->userSession = $userSession;
		$this->userSettingsMapper = $userSettingsMapper;
		$this->auditLogMapper = $auditLogMapper;
		$this->l10n = $l10n;
	}

	/**
	 * Personal settings page
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function index(): TemplateResponse
	{
		Util::addTranslations('arbeitszeitcheck');
		Util::addScript('arbeitszeitcheck', 'settings');
		return new TemplateResponse('arbeitszeitcheck', 'personal-settings');
	}

	/**
	 * Update personal settings
	 *
	 * @NoAdminRequired
	 */
	public function update(): JSONResponse
	{
		try {
			$user = $this->userSession->getUser();
			if (!$user) {
				throw new \Exception('User not authenticated');
			}

			$userId = $user->getUID();
			$params = $this->request->getParams();
			
			// List of allowed settings keys
			$allowedKeys = [
				'vacation_days_per_year',
				'notifications_enabled',
				'break_reminders_enabled',
				'default_working_hours_per_day',
				'auto_break_calculation'
			];

			$updatedSettings = [];
			$oldValues = [];

			// Update each setting if provided
			foreach ($allowedKeys as $key) {
				if (isset($params[$key])) {
					// Get old value for audit log
					$oldSetting = $this->userSettingsMapper->getSetting($userId, $key);
					$oldValues[$key] = $oldSetting ? $oldSetting->getSettingValue() : null;

					// Update setting
					$value = $params[$key];
					
					// Validate value based on key type
					if ($key === 'vacation_days_per_year' || $key === 'default_working_hours_per_day') {
						$value = (string)max(0, (int)$value);
					} elseif ($key === 'notifications_enabled' || $key === 'break_reminders_enabled' || $key === 'auto_break_calculation') {
						$value = $value === true || $value === 'true' || $value === '1' ? '1' : '0';
					} else {
						$value = (string)$value;
					}

					$this->userSettingsMapper->setSetting($userId, $key, $value);
					$updatedSettings[$key] = $value;
				}
			}

			if (empty($updatedSettings)) {
				return new JSONResponse([
					'success' => false,
					'error' => $this->l10n->t('No valid settings provided')
				], Http::STATUS_BAD_REQUEST);
			}

			// Create audit log entry
			$this->auditLogMapper->logAction(
				$userId,
				'settings_updated',
				'user_settings',
				null,
				$oldValues,
				$updatedSettings
			);

			return new JSONResponse([
				'success' => true,
				'message' => $this->l10n->t('Settings updated successfully'),
				'settings' => $updatedSettings
			]);
		} catch (\Throwable $e) {
			return new JSONResponse([
				'success' => false,
				'error' => $e->getMessage()
			], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Check if user has completed onboarding tour
	 *
	 * @NoAdminRequired
	 * @return JSONResponse
	 */
	public function getOnboardingCompleted(): JSONResponse
	{
		try {
			$user = $this->userSession->getUser();
			if (!$user) {
				return new JSONResponse([
					'success' => false,
					'error' => 'User not authenticated'
				], Http::STATUS_UNAUTHORIZED);
			}

			$userId = $user->getUID();
			
			// Try to get the setting, but handle table not existing gracefully
			try {
				$setting = $this->userSettingsMapper->getSetting($userId, 'onboarding_completed');
				$completed = $setting && $setting->getSettingValue() === '1';
			} catch (DBException $e) {
				// Table doesn't exist yet - return default
				\OCP\Log\logger('arbeitszeitcheck')->warning('Settings table not found, returning default', ['exception' => $e]);
				$completed = false;
			} catch (\Throwable $e) {
				// Any other error (including PDO exceptions) - return default
				\OCP\Log\logger('arbeitszeitcheck')->warning('Error getting onboarding setting: ' . $e->getMessage() . ' | Class: ' . get_class($e), ["exception" => $e]);
				$completed = false;
			}

			return new JSONResponse([
				'success' => true,
				'completed' => $completed
			]);
		} catch (\Throwable $e) {
			// Log error but return a safe default response
			\OCP\Log\logger('arbeitszeitcheck')->error('Error in getOnboardingCompleted: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString(), ["exception" => $e]);
			return new JSONResponse([
				'success' => true,
				'completed' => false // Default to false if there's an error
			]);
		}
	}

	/**
	 * Mark onboarding tour as completed
	 *
	 * @NoAdminRequired
	 * @return JSONResponse
	 */
	public function setOnboardingCompleted(): JSONResponse
	{
		try {
			$user = $this->userSession->getUser();
			if (!$user) {
				return new JSONResponse([
					'success' => false,
					'error' => 'User not authenticated'
				], Http::STATUS_UNAUTHORIZED);
			}

			$userId = $user->getUID();
			$completed = $this->request->getParam('completed', true);
			
			// Try to set the setting, but handle table not existing gracefully
			try {
				$this->userSettingsMapper->setSetting($userId, 'onboarding_completed', $completed ? '1' : '0');
			} catch (DBException $e) {
				// Table doesn't exist yet - just return success (setting will be saved when table is created)
				\OCP\Log\logger('arbeitszeitcheck')->warning('Settings table not found, cannot save setting', ['exception' => $e]);
				return new JSONResponse([
					'success' => true,
					'message' => $this->l10n->t('Onboarding status will be saved after database migration')
				]);
			} catch (\Throwable $e) {
				// Any other error (including PDO exceptions) - log and return success to avoid breaking the UI
				\OCP\Log\logger('arbeitszeitcheck')->warning('Error setting onboarding setting: ' . $e->getMessage() . ' | Class: ' . get_class($e), ["exception" => $e]);
				return new JSONResponse([
					'success' => true,
					'message' => $this->l10n->t('Onboarding status will be saved after database migration')
				]);
			}

			// Create audit log entry (only if mapper is available)
			try {
				$this->auditLogMapper->logAction(
					$userId,
					'onboarding_completed',
					'user_settings',
					null,
					null,
					['onboarding_completed' => $completed ? '1' : '0']
				);
			} catch (\Throwable $e) {
				// Log audit error but don't fail the request
				\OCP\Log\logger('arbeitszeitcheck')->warning('Error logging onboarding action: ' . $e->getMessage(), ["exception" => $e]);
			}

			return new JSONResponse([
				'success' => true,
				'message' => $this->l10n->t('Onboarding status updated')
			]);
		} catch (\Throwable $e) {
			\OCP\Log\logger('arbeitszeitcheck')->error('Error in setOnboardingCompleted: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString(), ["exception" => $e]);
			return new JSONResponse([
				'success' => false,
				'error' => $this->l10n->t('Failed to update onboarding status')
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}