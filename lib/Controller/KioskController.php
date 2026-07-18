<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Controller;

use OCA\ArbeitszeitCheck\Config\VendorPublicKey;
use OCA\ArbeitszeitCheck\Service\Kiosk\KioskActionService;
use OCA\ArbeitszeitCheck\Service\Kiosk\KioskAuthService;
use OCA\ArbeitszeitCheck\Service\Kiosk\KioskEnrollmentService;
use OCA\ArbeitszeitCheck\Service\Kiosk\KioskErrorMessages;
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
		private readonly KioskErrorMessages $kioskErrorMessages,
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
			'KIOSK_RFID_ALREADY_ASSIGNED', 'ENROLLMENT_ACTIVE', 'KIOSK_BUSY' => Http::STATUS_CONFLICT,
			'ENROLLMENT_NOT_ACTIVE', 'KIOSK_CREDENTIAL_NOT_FOUND', 'KIOSK_TERMINAL_NOT_FOUND', 'PAIRING_CODE_INVALID', 'KIOSK_CREDENTIAL_UNKNOWN' => Http::STATUS_NOT_FOUND,
			'PIN_INVALID', 'PIN_LOCKED', 'KIOSK_SESSION_INVALID', 'KIOSK_TERMINAL_UNAUTHORIZED' => Http::STATUS_UNAUTHORIZED,
			default => Http::STATUS_BAD_REQUEST,
		};
		return new JSONResponse([
			'success' => false,
			'error' => $code,
			'message' => $this->kioskErrorMessages->message($code),
		], $status);
	}
}
