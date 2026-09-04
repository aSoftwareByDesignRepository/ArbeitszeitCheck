<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Controller;

use OCA\ArbeitszeitCheck\Config\VendorPublicKey;
use OCA\ArbeitszeitCheck\Capabilities;
use OCA\ArbeitszeitCheck\Service\DashboardWidgetDataService;
use OCA\ArbeitszeitCheck\Service\LicenseService;
use OCA\ArbeitszeitCheck\Service\MobileSeatService;
use OCA\ArbeitszeitCheck\Service\MonthClosureFeature;
use OCA\ArbeitszeitCheck\Service\OvertimeBankService;
use OCA\ArbeitszeitCheck\Service\PermissionService;
use OCP\App\IAppManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Services\IAppConfig;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\L10N\IFactory as L10NFactory;

/**
 * Single cold-start payload for the proprietary mobile app (Basic auth, no CSRF).
 */
class MobileBootstrapController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly IUserSession $userSession,
		private readonly DashboardWidgetDataService $widgetDataService,
		private readonly PermissionService $permissionService,
		private readonly OvertimeBankService $overtimeBankService,
		private readonly IAppManager $appManager,
		private readonly IConfig $config,
		private readonly IAppConfig $appConfig,
		private readonly L10NFactory $l10nFactory,
		private readonly Capabilities $capabilities,
		private readonly LicenseService $licenseService,
		private readonly MobileSeatService $mobileSeatService,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function bootstrap(): JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse([
				'success' => false,
				'error' => 'User not authenticated',
			], Http::STATUS_UNAUTHORIZED);
		}

		$userId = $user->getUID();
		$canManage = $this->permissionService->canAccessManagerDashboard($userId);
		$isAdmin = $this->permissionService->isAdmin($userId);

		$pushAvailable = $this->appManager->isEnabledForUser('notifications', $user);

		$locale = $this->l10nFactory->findLanguage('arbeitszeitcheck', $userId);

		$planActive = $this->licenseService->isMobilePlanActive();
		$validUntil = $this->licenseService->getValidUntil();
		$enabledForUser = $planActive && $this->mobileSeatService->isUserAllowed($userId);
		// Envelope is always returned when a mobile plan exists so clients can verify
		// the org license locally and show LicenseGate (no seat) vs UnofficialServer.
		$envelope = $planActive ? $this->licenseService->buildEnvelope() : null;
		$unitAware = $this->isVacationUnitAwareClient();

		return new JSONResponse([
			'success' => true,
			'data' => [
				'userId' => $userId,
				'displayName' => $user->getDisplayName(),
				'locale' => $locale,
				'canManage' => $canManage,
				'isAdmin' => $isAdmin,
				'pushAvailable' => $pushAvailable,
				'employee' => $this->widgetDataService->getEmployeeWidgetData($userId, $unitAware),
				'capabilities' => $this->capabilities->getCapabilities()['arbeitszeitcheck'] ?? [],
				'features' => [
					'monthClosure' => MonthClosureFeature::isEnabledFromIConfig($this->config),
					'overtimeBank' => $this->overtimeBankService->isEnabled(),
					'timeCapture' => $this->capabilities->getCapabilities()['arbeitszeitcheck']['mobile']['timeCapture'] ?? [
						'clockStampingEnabled' => true,
						'manualTimeEntryEnabled' => true,
					],
					'requireSubstituteTypes' => $this->requireSubstituteTypes(),
				],
				'licensing' => [
					'mobile' => [
						'planActive' => $planActive,
						'enabledForUser' => $enabledForUser,
						'seats' => $this->licenseService->getMobileSeatLimit(),
						'seatsAssigned' => $this->mobileSeatService->getAssignedCount(),
						'expiresAt' => $validUntil?->format('Y-m-d'),
						'source' => $planActive ? 'org_license' : 'none',
					],
					'envelope' => $envelope,
					'vendorPublicKeyB64' => VendorPublicKey::publicKeyB64(),
				],
			],
		]);
	}

	/**
	 * Full employee home payload for the proprietary mobile app.
	 *
	 * Do NOT use /api/dashboard-widget/employee here — that route returns the lean
	 * desklet summary (no vacation/week/balance fields) and will crash mobile UI
	 * that formats those numbers.
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function dashboard(): JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse([
				'success' => false,
				'error' => 'User not authenticated',
			], Http::STATUS_UNAUTHORIZED);
		}

		return new JSONResponse([
			'success' => true,
			'data' => $this->widgetDataService->getEmployeeWidgetData(
				$user->getUID(),
				$this->isVacationUnitAwareClient()
			),
		]);
	}

	/**
	 * @return list<string>
	 */
	private function requireSubstituteTypes(): array
	{
		$raw = $this->config->getAppValue('arbeitszeitcheck', 'require_substitute_types', '[]');
		$decoded = json_decode($raw, true);
		if (!is_array($decoded)) {
			return [];
		}
		$out = [];
		foreach ($decoded as $type) {
			if (is_string($type) && $type !== '') {
				$out[] = $type;
			}
		}
		return array_values(array_unique($out));
	}

	/**
	 * Employee companion declares hours-unit support via X-AZC-Vacation-Unit-Aware: 1.
	 * Absent/unknown → treat as unaware (Q8 fail-closed for NN-08).
	 */
	private function isVacationUnitAwareClient(): bool
	{
		$raw = trim((string)$this->request->getHeader('X-AZC-Vacation-Unit-Aware'));
		return $raw === '1' || strcasecmp($raw, 'true') === 0;
	}
}
