<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Middleware;

use OCA\ArbeitszeitCheck\AppInfo\Application;
use OCA\ArbeitszeitCheck\Service\Kiosk\KioskSettingsService;
use OCA\ArbeitszeitCheck\Service\Kiosk\KioskTerminalService;
use OCA\ArbeitszeitCheck\Service\LicenseService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Middleware;
use OCP\IRequest;
use OCP\L10N\IFactory;
use Psr\Log\LoggerInterface;

/**
 * Gates /api/kiosk/* — feature flag, terminal license, terminal token validation.
 * POST /api/kiosk/pair is exempt from token checks (pairing code only).
 */
class KioskLicenseMiddleware extends Middleware
{
	private const PAIR_PATH = '/api/kiosk/pair';

	public function __construct(
		private readonly IRequest $request,
		private readonly KioskSettingsService $kioskSettingsService,
		private readonly LicenseService $licenseService,
		private readonly KioskTerminalService $terminalService,
		private readonly IFactory $l10nFactory,
		private readonly LoggerInterface $logger,
	) {
	}

	public function beforeController($controller, $methodName): void
	{
		if (!$this->isKioskApiRequest()) {
			return;
		}

		if (!$this->kioskSettingsService->isKioskEnabled()) {
			throw new KioskDisabledException();
		}

		$path = $this->normalizeApiPath((string)$this->request->getPathInfo());
		if ($path === self::PAIR_PATH) {
			return;
		}

		if (!$this->licenseService->isTerminalPlanActive()) {
			$this->logger->info('Kiosk license gate: no active terminal plan', ['path' => $path]);
			throw new KioskTerminalLicenseRequiredException();
		}

		$terminalId = (string)$this->request->getHeader('X-Kiosk-Terminal-Id');
		$token = (string)$this->request->getHeader('X-Kiosk-Token');
		if ($this->terminalService->validateTerminalToken($terminalId, $token) === null) {
			throw new KioskUnauthorizedException();
		}
	}

	public function afterException($controller, $methodName, \Exception $exception)
	{
		$l = $this->l10nFactory->get(Application::APP_ID);

		if ($exception instanceof KioskDisabledException) {
			$message = $l->t('Kiosk mode is not enabled on this server.');
			return new JSONResponse([
				'success' => false,
				'error' => $message,
				'message' => $message,
				'code' => 'KIOSK_DISABLED',
			], Http::STATUS_NOT_FOUND);
		}

		if ($exception instanceof KioskTerminalLicenseRequiredException) {
			$message = $l->t('ArbeitszeitCheck Terminal is not licensed for this organisation.');
			$adminHint = $l->t('Ask your administrator to add a Terminal license key.');
			return new JSONResponse([
				'success' => false,
				'error' => 'TERMINAL_LICENSE_REQUIRED',
				'message' => $message,
				'code' => 'TERMINAL_LICENSE_REQUIRED',
				'licensing' => [
					'licenseRenewMailto' => 'mailto:info@software-by-design.de?subject=' . rawurlencode('ArbeitszeitCheck License'),
					'productsUrl' => 'https://nextcloud.software-by-design.de/',
					'adminHint' => $adminHint,
				],
			], Http::STATUS_PAYMENT_REQUIRED);
		}

		if ($exception instanceof KioskUnauthorizedException) {
			$message = $l->t('Terminal token invalid or revoked.');
			return new JSONResponse([
				'success' => false,
				'error' => $message,
				'message' => $message,
				'code' => 'KIOSK_TERMINAL_UNAUTHORIZED',
			], Http::STATUS_UNAUTHORIZED);
		}

		throw $exception;
	}

	private function isKioskApiRequest(): bool
	{
		$path = $this->normalizeApiPath((string)$this->request->getPathInfo());
		return str_starts_with($path, '/api/kiosk');
	}

	private function normalizeApiPath(string $pathInfo): string
	{
		$path = $pathInfo;
		$prefix = '/apps/arbeitszeitcheck';
		if (str_starts_with($path, $prefix)) {
			$path = substr($path, strlen($prefix));
		}
		return $path === '' ? '/' : $path;
	}
}
