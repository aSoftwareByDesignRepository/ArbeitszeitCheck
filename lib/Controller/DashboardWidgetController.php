<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Controller;

use OCA\ArbeitszeitCheck\Exception\MonthFinalizedException;
use OCA\ArbeitszeitCheck\Service\DashboardWidgetDataService;
use OCA\ArbeitszeitCheck\Service\PermissionService;
use OCA\ArbeitszeitCheck\Service\TimeTrackingService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Util;

class DashboardWidgetController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly IUserSession $userSession,
		private readonly IL10N $l10n,
		private readonly IURLGenerator $urlGenerator,
		private readonly DashboardWidgetDataService $widgetDataService,
		private readonly TimeTrackingService $timeTrackingService,
		private readonly PermissionService $permissionService,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function workspace(): TemplateResponse {
		Util::addTranslations('arbeitszeitcheck');

		$userId = $this->getUserId();
		$params = [
			'isManager' => $this->permissionService->canAccessManagerDashboard($userId),
			'isAdmin' => $this->permissionService->isAdmin($userId),
			'urlGenerator' => $this->urlGenerator,
			'l' => $this->l10n,
		];

		return new TemplateResponse('arbeitszeitcheck', 'dashboard-widget-workspace', $params);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function employeeData(): JSONResponse {
		$userId = $this->getUserId();
		return new JSONResponse([
			'success' => true,
			'data' => $this->widgetDataService->getEmployeeWidgetData($userId),
		]);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function managerData(?int $limit = 7): JSONResponse {
		$userId = $this->getUserId();
		$data = $this->widgetDataService->getManagerWidgetData($userId, $this->normalizeLimit($limit, 7, 50));
		if (!(bool)$data['authorized']) {
			return new JSONResponse([
				'success' => false,
				'error' => $this->l10n->t('Access denied'),
			], Http::STATUS_FORBIDDEN);
		}
		return new JSONResponse(['success' => true, 'data' => $data]);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function adminData(?int $limit = 10): JSONResponse {
		$userId = $this->getUserId();
		$data = $this->widgetDataService->getAdminWidgetData($userId, $this->normalizeLimit($limit, 10, 50));
		if (!(bool)$data['authorized']) {
			return new JSONResponse([
				'success' => false,
				'error' => $this->l10n->t('Access denied'),
			], Http::STATUS_FORBIDDEN);
		}
		return new JSONResponse(['success' => true, 'data' => $data]);
	}

	#[NoAdminRequired]
	public function clockIn(): JSONResponse {
		return $this->handleAction(fn (string $userId) => $this->timeTrackingService->clockIn($userId));
	}

	#[NoAdminRequired]
	public function startBreak(): JSONResponse {
		return $this->handleAction(fn (string $userId) => $this->timeTrackingService->startBreak($userId));
	}

	#[NoAdminRequired]
	public function endBreak(): JSONResponse {
		return $this->handleAction(fn (string $userId) => $this->timeTrackingService->endBreak($userId));
	}

	#[NoAdminRequired]
	public function clockOut(): JSONResponse {
		return $this->handleAction(fn (string $userId) => $this->timeTrackingService->clockOut($userId));
	}

	private function handleAction(callable $action): JSONResponse {
		try {
			$userId = $this->getUserId();
			$entry = $action($userId);
			return new JSONResponse([
				'success' => true,
				'timeEntry' => $entry->getSummary(),
				'status' => $this->timeTrackingService->getStatus($userId),
			]);
		} catch (MonthFinalizedException $e) {
			return new JSONResponse([
				'success' => false,
				'error' => $this->l10n->t('This calendar month is finalized. Contact an administrator if a correction must be made.'),
			], Http::STATUS_CONFLICT);
		} catch (\Throwable $e) {
			\OCP\Log\logger('arbeitszeitcheck')->warning('Dashboard widget action failed', [
				'exception' => $e,
			]);
			return new JSONResponse([
				'success' => false,
				'error' => $this->l10n->t('Action failed'),
			], Http::STATUS_BAD_REQUEST);
		}
	}

	private function normalizeLimit(?int $limit, int $default, int $max): int {
		if ($limit === null) {
			return $default;
		}
		return max(1, min($max, $limit));
	}

	private function getUserId(): string {
		$user = $this->userSession->getUser();
		if ($user === null) {
			throw new \RuntimeException('User not authenticated');
		}
		return $user->getUID();
	}
}
