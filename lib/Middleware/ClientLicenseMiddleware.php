<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Middleware;

use OCA\ArbeitszeitCheck\AppInfo\Application;
use OCA\ArbeitszeitCheck\Service\LicenseService;
use OCA\ArbeitszeitCheck\Service\MobileSeatService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Middleware;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use Psr\Log\LoggerInterface;

/**
 * Returns HTTP 402 for mobile API requests via app password (Basic auth).
 * Web UI session requests are not gated — OSS browser stamping stays free.
 */
class ClientLicenseMiddleware extends Middleware
{
	private const BOOTSTRAP_PATH = '/api/mobile/bootstrap';

	public function __construct(
		private readonly IRequest $request,
		private readonly IUserSession $userSession,
		private readonly LicenseService $licenseService,
		private readonly MobileSeatService $mobileSeatService,
		private readonly IFactory $l10nFactory,
		private readonly LoggerInterface $logger,
	) {
	}

	public function beforeController($controller, $methodName): void
	{
		if (!$this->usesBasicAppPassword()) {
			return;
		}

		$path = $this->normalizeApiPath((string)$this->request->getPathInfo());
		if (!$this->shouldGateMobileApiPath($path)) {
			return;
		}

		$user = $this->userSession->getUser();
		if ($user === null) {
			return;
		}

		$userId = $user->getUID();

		if (!$this->licenseService->isMobilePlanActive()) {
			$this->logger->info('Mobile license gate: no active mobile plan', [
				'userId' => $userId,
				'path' => $path,
				'method' => $this->request->getMethod(),
			]);
			throw new ClientLicenseRequiredException('no_plan');
		}

		if (!$this->mobileSeatService->isUserAllowed($userId)) {
			$this->logger->info('Mobile license gate: user has no seat', [
				'userId' => $userId,
				'path' => $path,
				'method' => $this->request->getMethod(),
			]);
			throw new ClientLicenseRequiredException('no_seat');
		}
	}

	public function afterException($controller, $methodName, \Exception $exception)
	{
		if (!$exception instanceof ClientLicenseRequiredException) {
			throw $exception;
		}

		$l = $this->l10nFactory->get(Application::APP_ID);
		$message = $exception->getReason() === 'no_plan'
			? $l->t('ArbeitszeitCheck Mobile is not licensed for this organisation.')
			: $l->t('ArbeitszeitCheck Mobile is not licensed for this user.');
		$adminHint = $l->t('Ask your administrator to assign a mobile seat or add an organisation license.');

		return new JSONResponse([
			'success' => false,
			'error' => $message,
			'message' => $message,
			'code' => 'LICENSE_REQUIRED',
			'error_code' => 'LICENSE_REQUIRED',
			'licensing' => [
				'licenseRenewMailto' => 'mailto:info@software-by-design.de?subject=' . rawurlencode('ArbeitszeitCheck License'),
				'productsUrl' => 'https://nextcloud.software-by-design.de/',
				'adminHint' => $adminHint,
			],
		], Http::STATUS_PAYMENT_REQUIRED);
	}

	private function shouldGateMobileApiPath(string $path): bool
	{
		if (!str_starts_with($path, '/api/')) {
			return false;
		}
		if ($path === self::BOOTSTRAP_PATH) {
			return false;
		}
		if (str_starts_with($path, '/api/kiosk')) {
			return false;
		}
		if (str_starts_with($path, '/api/admin')) {
			return false;
		}
		return true;
	}

	/**
	 * Mobile and other API clients authenticate with a dedicated app password (Basic).
	 * Browser web UI uses session cookies without Basic — those requests are not gated.
	 */
	private function usesBasicAppPassword(): bool
	{
		$auth = (string)$this->request->getHeader('Authorization');
		return str_starts_with(strtolower($auth), 'basic ');
	}

	private function normalizeApiPath(string $pathInfo): string
	{
		$path = $pathInfo;
		$prefix = '/apps/arbeitszeitcheck';
		if (str_starts_with($path, $prefix)) {
			$path = substr($path, strlen($prefix));
		}
		if ($path === '') {
			return '/';
		}
		return $path;
	}
}
