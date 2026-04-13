<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Middleware;

use OCA\ArbeitszeitCheck\Controller\AdminController;
use OCA\ArbeitszeitCheck\Exception\NotAppAdminException;
use OCA\ArbeitszeitCheck\Service\PermissionService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Middleware;
use OCP\IL10N;
use OCP\IUserSession;

final class AppAdminMiddleware extends Middleware
{
	public function __construct(
		private readonly IUserSession $userSession,
		private readonly PermissionService $permissionService,
		private readonly IL10N $l10n,
	) {
	}

	public function beforeController($controller, $methodName): void
	{
		if (!$controller instanceof AdminController) {
			return;
		}

		$user = $this->userSession->getUser();
		if ($user === null || !$this->permissionService->isAdmin($user->getUID())) {
			throw new NotAppAdminException($this->l10n->t('Access denied. You are not an ArbeitszeitCheck app administrator.'));
		}
	}

	public function afterException($controller, $methodName, \Exception $exception): TemplateResponse
	{
		if (!$exception instanceof NotAppAdminException) {
			throw $exception;
		}

		$response = new TemplateResponse('core', '403', ['message' => $exception->getMessage()], 'guest');
		$response->setStatus(Http::STATUS_FORBIDDEN);
		return $response;
	}
}
