<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Middleware;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Middleware;
use OCP\AppFramework\Utility\IControllerMethodReflector;
use OCP\IL10N;
use OCP\IRequest;
use ReflectionMethod;

/**
 * Re-assert CSRF for browser cookie sessions on state-changing requests.
 *
 * Many mobile/API endpoints intentionally use {@see \OCP\AppFramework\Http\Attribute\NoCSRFRequired}
 * so Basic-auth app passwords work without a request token. That attribute also
 * disables CSRF for cookie sessions — which would allow cross-site POST forgery
 * against a logged-in browser user (clock in/out, absences, etc.).
 *
 * Rule:
 *  - Mutating methods only (POST/PUT/PATCH/DELETE)
 *  - Skip when no Nextcloud session cookie is present (mobile Basic clients)
 *  - Skip {@see PublicPage} (kiosk / health use their own auth)
 *  - When a session cookie is present, always require {@see IRequest::passesCSRFCheck()}
 *    — even if a forged `Authorization: Basic …` header is attached
 */
final class SessionCsrfGuardMiddleware extends Middleware
{
	/** @var list<string> */
	private const SESSION_COOKIE_NAMES = [
		'nc_session_id',
		'__Host-nc_session_id',
		'__Secure-nc_session_id',
		'oc_sessionPassphrase',
	];

	public function __construct(
		private readonly IRequest $request,
		private readonly IControllerMethodReflector $reflector,
		private readonly IL10N $l10n,
	) {
	}

	public function beforeController($controller, $methodName): void
	{
		$method = strtoupper((string)$this->request->getMethod());
		if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
			return;
		}

		if (!$this->hasBrowserSessionCookie()) {
			return;
		}

		try {
			$reflection = new ReflectionMethod($controller, $methodName);
		} catch (\ReflectionException) {
			return;
		}

		if ($this->isPublicPage($reflection)) {
			return;
		}

		if ($this->request->passesCSRFCheck()) {
			return;
		}

		throw new SessionCsrfRequiredException(
			$this->l10n->t('Security check failed. Reload the page and try again.')
		);
	}

	public function afterException($controller, $methodName, \Exception $exception): Response
	{
		if (!$exception instanceof SessionCsrfRequiredException) {
			throw $exception;
		}

		$path = '';
		$accept = '';
		$contentType = '';
		$xRequestedWith = '';
		try {
			$path = (string)($this->request->getPathInfo() ?? '');
			$accept = strtolower((string)$this->request->getHeader('Accept'));
			$contentType = strtolower((string)$this->request->getHeader('Content-Type'));
			$xRequestedWith = strtolower((string)$this->request->getHeader('X-Requested-With'));
		} catch (\Throwable) {
			// Fall through to HTML 412 for page contexts.
		}

		$wantsJson = str_contains($path, '/api/')
			|| str_contains($accept, 'application/json')
			|| str_contains($contentType, 'application/json')
			|| $xRequestedWith === 'xmlhttprequest';

		if ($wantsJson) {
			return new JSONResponse([
				'success' => false,
				'error' => $exception->getMessage(),
				'error_code' => 'csrf_failed',
			], Http::STATUS_PRECONDITION_FAILED);
		}

		$response = new TemplateResponse('core', '403', [
			'message' => $exception->getMessage(),
		], 'guest');
		$response->setStatus(Http::STATUS_PRECONDITION_FAILED);
		return $response;
	}

	/**
	 * True when the request carries a Nextcloud browser session cookie.
	 * Pure Basic-auth clients (mobile) do not send these and keep NoCSRFRequired.
	 */
	private function hasBrowserSessionCookie(): bool
	{
		foreach (self::SESSION_COOKIE_NAMES as $name) {
			$value = $this->request->getCookie($name);
			if (is_string($value) && $value !== '') {
				return true;
			}
		}
		return false;
	}

	private function isPublicPage(ReflectionMethod $reflection): bool
	{
		if (!empty($reflection->getAttributes(PublicPage::class))) {
			return true;
		}
		return $this->reflector->hasAnnotation('PublicPage');
	}
}
