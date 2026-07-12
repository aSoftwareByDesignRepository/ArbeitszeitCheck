<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Controller;

use OCA\ArbeitszeitCheck\Config\VendorPublicKey;
use OCA\ArbeitszeitCheck\Service\Kiosk\KioskActionService;
use OCA\ArbeitszeitCheck\Service\Kiosk\KioskAuthService;
use OCA\ArbeitszeitCheck\Service\Kiosk\KioskEnrollmentService;
use OCA\ArbeitszeitCheck\Service\Kiosk\KioskException;
use OCA\ArbeitszeitCheck\Service\Kiosk\KioskTerminalService;
use OCA\ArbeitszeitCheck\Service\LicenseService;
use OCA\ArbeitszeitCheck\Service\TerminalDeviceService;
use OCA\ArbeitszeitCheck\Service\TimeZoneService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\BruteForceProtection;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IL10N;
use OCP\IRequest;

class KioskController extends Controller
{
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly IL10N $l10n,
		private readonly KioskTerminalService $terminalService,
		private readonly KioskAuthService $authService,
		private readonly KioskActionService $actionService,
		private readonly KioskEnrollmentService $enrollmentService,
		private readonly LicenseService $licenseService,
		private readonly TerminalDeviceService $terminalDeviceService,
		private readonly TimeZoneService $timeZoneService,
	) {
		parent::__construct($appName, $request);
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[BruteForceProtection(action: 'arbeitszeitcheck_kiosk_pair')]
	public function pair(string $pairingCode = '', string $label = ''): JSONResponse
	{
		try {
			$result = $this->terminalService->pair($pairingCode, $label);
			return new JSONResponse([
				'success' => true,
				'data' => $result,
			], Http::STATUS_CREATED);
		} catch (KioskException $e) {
			return $this->kioskError($e);
		}
	}

	#[PublicPage]
	#[NoCSRFRequired]
	public function config(): JSONResponse
	{
		$terminal = $this->requireTerminal();
		$this->terminalService->recordHeartbeat($terminal);

		$enrollment = $this->enrollmentService->getConfigEnrollment($terminal->getTerminalId());
		$envelope = $this->licenseService->buildEnvelope();
		$state = $this->licenseService->getLicenseSummary() ?? [];

		$data = [
			'serverNow' => $this->timeZoneService->nowAsIso(),
			'serverTimezone' => $this->timeZoneService->storageTimeZoneName(),
			'label' => $terminal->getLabel(),
			'licensing' => [
				'terminal' => [
					'planActive' => $this->licenseService->isTerminalPlanActive(),
					'devices' => (int)($state['terminalDevices'] ?? 0),
					'devicesRegistered' => $this->terminalDeviceService->getActiveCount(),
					'expiresAt' => $state['validUntil'] ?? null,
				],
				'envelope' => $envelope,
				'vendorPublicKeyB64' => VendorPublicKey::publicKeyB64(),
			],
		];
		if ($enrollment !== null) {
			$data['enrollment'] = $enrollment;
		}

		return new JSONResponse(['success' => true, 'data' => $data]);
	}

	#[PublicPage]
	#[NoCSRFRequired]
	public function users(): JSONResponse
	{
		$this->requireTerminal();
		return new JSONResponse([
			'success' => true,
			'data' => ['users' => $this->authService->listPinUsers()],
		]);
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[BruteForceProtection(action: 'arbeitszeitcheck_kiosk_identify')]
	public function identify(string $method = '', ?string $rfidUid = null, ?string $userId = null, ?string $pin = null): JSONResponse
	{
		$terminal = $this->requireTerminal();
		try {
			$data = $this->authService->identify($terminal, $method, $rfidUid, $userId, $pin);
			return new JSONResponse(['success' => true, 'data' => $data]);
		} catch (KioskException $e) {
			return $this->kioskError($e);
		}
	}

	#[PublicPage]
	#[NoCSRFRequired]
	public function action(string $sessionToken = '', string $action = ''): JSONResponse
	{
		$terminal = $this->requireTerminal();
		try {
			$data = $this->actionService->performAction($terminal, $sessionToken, $action);
			return new JSONResponse(['success' => true, 'data' => $data]);
		} catch (KioskException $e) {
			return $this->kioskError($e);
		}
	}

	#[PublicPage]
	#[NoCSRFRequired]
	public function heartbeat(): JSONResponse
	{
		$terminal = $this->requireTerminal();
		$this->terminalService->recordHeartbeat($terminal);
		return new JSONResponse(['success' => true]);
	}

	#[PublicPage]
	#[NoCSRFRequired]
	public function enrollScan(?string $rfidUid = null): JSONResponse
	{
		$terminal = $this->requireTerminal();
		try {
			$data = $this->enrollmentService->completeScan($terminal->getTerminalId(), $rfidUid ?? '');
			return new JSONResponse(['success' => true, 'data' => $data], Http::STATUS_CREATED);
		} catch (KioskException $e) {
			return $this->kioskError($e);
		}
	}

	private function requireTerminal(): \OCA\ArbeitszeitCheck\Db\KioskTerminal
	{
		$terminalId = (string)$this->request->getHeader('X-Kiosk-Terminal-Id');
		$token = (string)$this->request->getHeader('X-Kiosk-Token');
		$terminal = $this->terminalService->validateTerminalToken($terminalId, $token);
		if ($terminal === null) {
			throw new \OCA\ArbeitszeitCheck\Middleware\KioskUnauthorizedException();
		}
		return $terminal;
	}

	private function kioskError(KioskException $e): JSONResponse
	{
		$code = $e->getErrorCode();
		$status = match ($code) {
			'TERMINAL_LICENSE_REQUIRED' => Http::STATUS_PAYMENT_REQUIRED,
			'TERMINAL_DEVICE_LIMIT_REACHED', 'KIOSK_USER_NOT_ALLOWED' => Http::STATUS_FORBIDDEN,
			'KIOSK_RFID_ALREADY_ASSIGNED' => Http::STATUS_CONFLICT,
			'ENROLLMENT_NOT_ACTIVE', 'KIOSK_CREDENTIAL_NOT_FOUND', 'KIOSK_TERMINAL_NOT_FOUND', 'PAIRING_CODE_INVALID', 'KIOSK_CREDENTIAL_UNKNOWN' => Http::STATUS_NOT_FOUND,
			'PIN_INVALID', 'PIN_LOCKED', 'KIOSK_SESSION_INVALID', 'KIOSK_TERMINAL_UNAUTHORIZED' => Http::STATUS_UNAUTHORIZED,
			default => Http::STATUS_BAD_REQUEST,
		};
		return new JSONResponse([
			'success' => false,
			'error' => $code,
			'message' => $this->translateKioskError($code),
		], $status);
	}

	private function translateKioskError(string $code): string
	{
		return match ($code) {
			'TERMINAL_LICENSE_REQUIRED' => $this->l10n->t('Terminal license required. Contact your administrator.'),
			'TERMINAL_DEVICE_LIMIT_REACHED' => $this->l10n->t('All terminal device slots are in use.'),
			'PAIRING_CODE_INVALID' => $this->l10n->t('Pairing code is invalid or expired.'),
			'KIOSK_USER_NOT_ALLOWED' => $this->l10n->t('This employee is not allowed to use the kiosk.'),
			'PIN_INVALID' => $this->l10n->t('PIN is incorrect.'),
			'PIN_LOCKED' => $this->l10n->t('PIN is temporarily locked. Try again later.'),
			'KIOSK_SESSION_INVALID' => $this->l10n->t('Session expired. Identify again.'),
			'KIOSK_ACTION_INVALID' => $this->l10n->t('This action is not allowed in the current state.'),
			'MONTH_FINALIZED' => $this->l10n->t('This month is finalized. Contact your administrator.'),
			'KIOSK_DISABLED' => $this->l10n->t('Kiosk mode is disabled.'),
			default => $this->l10n->t('Request could not be completed.'),
		};
	}
}
