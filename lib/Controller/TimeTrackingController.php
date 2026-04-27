<?php

declare(strict_types=1);

/**
 * TimeTracking controller for the arbeitszeitcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Controller;

use OCA\ArbeitszeitCheck\Service\TimeTrackingService;
use OCA\ArbeitszeitCheck\Exception\MonthFinalizedException;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\IL10N;

/**
 * TimeTrackingController
 */
class TimeTrackingController extends Controller
{
	private TimeTrackingService $timeTrackingService;
	private IUserSession $userSession;
	private IL10N $l10n;

	public function __construct(
		string $appName,
		IRequest $request,
		TimeTrackingService $timeTrackingService,
		IUserSession $userSession,
		IL10N $l10n
	) {
		parent::__construct($appName, $request);
		$this->timeTrackingService = $timeTrackingService;
		$this->userSession = $userSession;
		$this->l10n = $l10n;
	}

	/**
	 * Get current user ID
	 *
	 * @return string
	 */
	private function getUserId(): string
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			throw new \Exception('User not authenticated');
		}
		return $user->getUID();
	}

	private function buildSafeErrorResponse(\Throwable $e): JSONResponse
	{
		$rawMessage = $e->getMessage();
		if (strpos($rawMessage, 'User not authenticated') !== false) {
			return new JSONResponse([
				'success' => false,
				'error' => $this->l10n->t('User not authenticated')
			], Http::STATUS_UNAUTHORIZED);
		}

		$businessRules = [
			'User is already clocked in',
			'Already clocked in',
			'User is currently on break. End break first.',
			'Project ID must not exceed',
			'Selected project does not exist',
			'Cannot clock in: Maximum daily working hours',
			'User is not currently clocked in',
			'No active time entry',
			'Break is already started',
			'User is not currently on break',
			'No active break',
			'Minimum rest period',
		];
		foreach ($businessRules as $needle) {
			if (strpos($rawMessage, $needle) !== false) {
				return new JSONResponse([
					'success' => false,
					'error' => $this->l10n->t($rawMessage),
				], Http::STATUS_BAD_REQUEST);
			}
		}

		return new JSONResponse([
			'success' => false,
			'error' => $this->l10n->t('An unexpected error occurred. Please try again. If the problem continues, contact your administrator.')
		], Http::STATUS_INTERNAL_SERVER_ERROR);
	}

	/**
	 * Clock in endpoint (called via AJAX with JSON)
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function clockIn(?string $projectCheckProjectId = null, ?string $description = null): JSONResponse
	{
		try {
			$userId = $this->getUserId();
			$timeEntry = $this->timeTrackingService->clockIn($userId, $projectCheckProjectId, $description);

			try {
				$summary = $timeEntry->getSummary();
			} catch (\Throwable $e) {
				\OCP\Log\logger('arbeitszeitcheck')->error('Error getting summary in clockIn: ' . $e->getMessage(), ["exception" => $e]);
				$summary = ['id' => $timeEntry->getId(), 'userId' => $userId, 'status' => $timeEntry->getStatus()];
			}

			return new JSONResponse([
				'success' => true,
				'timeEntry' => $summary
			]);
		} catch (MonthFinalizedException $e) {
			return new JSONResponse([
				'success' => false,
				'error' => $this->l10n->t('This calendar month is finalized. Contact an administrator if a correction must be made.'),
			], Http::STATUS_CONFLICT);
		} catch (\Throwable $e) {
			\OCP\Log\logger('arbeitszeitcheck')->error('Error in TimeTrackingController: ' . $e->getMessage(), ["exception" => $e]);
			return $this->buildSafeErrorResponse($e);
		}
	}

	/**
	 * Clock out endpoint (called via AJAX with JSON)
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function clockOut(): JSONResponse
	{
		try {
			$userId = $this->getUserId();
			$timeEntry = $this->timeTrackingService->clockOut($userId);

			try {
				$summary = $timeEntry->getSummary();
			} catch (\Throwable $e) {
				\OCP\Log\logger('arbeitszeitcheck')->error('Error getting summary in clockOut: ' . $e->getMessage(), ["exception" => $e]);
				$summary = ['id' => $timeEntry->getId(), 'userId' => $userId, 'status' => $timeEntry->getStatus()];
			}

			return new JSONResponse([
				'success' => true,
				'timeEntry' => $summary
			]);
		} catch (MonthFinalizedException $e) {
			return new JSONResponse([
				'success' => false,
				'error' => $this->l10n->t('This calendar month is finalized. Contact an administrator if a correction must be made.'),
			], Http::STATUS_CONFLICT);
		} catch (\Throwable $e) {
			\OCP\Log\logger('arbeitszeitcheck')->error('Error in TimeTrackingController: ' . $e->getMessage(), ["exception" => $e]);
			return $this->buildSafeErrorResponse($e);
		}
	}

	/**
	 * Get current status endpoint
	 */
	#[NoAdminRequired]
	public function getStatus(): JSONResponse
	{
		try {
			$userId = $this->getUserId();
			$status = $this->timeTrackingService->getStatus($userId);

			return new JSONResponse([
				'success' => true,
				'status' => $status
			]);
		} catch (\Throwable $e) {
			\OCP\Log\logger('arbeitszeitcheck')->error('Error in TimeTrackingController::getStatus: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString(), ["exception" => $e]);
			return $this->buildSafeErrorResponse($e);
		}
	}

	/**
	 * Start break endpoint (called via AJAX with JSON)
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function startBreak(): JSONResponse
	{
		try {
			$userId = $this->getUserId();
			$timeEntry = $this->timeTrackingService->startBreak($userId);

			try {
				$summary = $timeEntry->getSummary();
			} catch (\Throwable $e) {
				\OCP\Log\logger('arbeitszeitcheck')->error('Error getting summary in startBreak: ' . $e->getMessage(), ["exception" => $e]);
				$summary = ['id' => $timeEntry->getId(), 'userId' => $userId, 'status' => $timeEntry->getStatus()];
			}

			return new JSONResponse([
				'success' => true,
				'timeEntry' => $summary
			]);
		} catch (MonthFinalizedException $e) {
			return new JSONResponse([
				'success' => false,
				'error' => $this->l10n->t('This calendar month is finalized. Contact an administrator if a correction must be made.'),
			], Http::STATUS_CONFLICT);
		} catch (\Throwable $e) {
			\OCP\Log\logger('arbeitszeitcheck')->error('Error in TimeTrackingController: ' . $e->getMessage(), ["exception" => $e]);
			return $this->buildSafeErrorResponse($e);
		}
	}

	/**
	 * End break endpoint (called via AJAX with JSON)
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function endBreak(): JSONResponse
	{
		try {
			$userId = $this->getUserId();
			$timeEntry = $this->timeTrackingService->endBreak($userId);

			try {
				$summary = $timeEntry->getSummary();
			} catch (\Throwable $e) {
				\OCP\Log\logger('arbeitszeitcheck')->error('Error getting summary in endBreak: ' . $e->getMessage(), ["exception" => $e]);
				$summary = ['id' => $timeEntry->getId(), 'userId' => $userId, 'status' => $timeEntry->getStatus()];
			}

			return new JSONResponse([
				'success' => true,
				'timeEntry' => $summary
			]);
		} catch (MonthFinalizedException $e) {
			return new JSONResponse([
				'success' => false,
				'error' => $this->l10n->t('This calendar month is finalized. Contact an administrator if a correction must be made.'),
			], Http::STATUS_CONFLICT);
		} catch (\Throwable $e) {
			\OCP\Log\logger('arbeitszeitcheck')->error('Error in TimeTrackingController: ' . $e->getMessage(), ["exception" => $e]);
			return $this->buildSafeErrorResponse($e);
		}
	}

	/**
	 * Get break status endpoint
	 */
	#[NoAdminRequired]
	public function getBreakStatus(): JSONResponse
	{
		try {
			$userId = $this->getUserId();
			$breakStatus = $this->timeTrackingService->getBreakStatus($userId);

			return new JSONResponse([
				'success' => true,
				'breakStatus' => $breakStatus
			]);
		} catch (\Throwable $e) {
			\OCP\Log\logger('arbeitszeitcheck')->error('Error in TimeTrackingController::getBreakStatus: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString(), ["exception" => $e]);
			return $this->buildSafeErrorResponse($e);
		}
	}
}