<?php

declare(strict_types=1);

/**
 * Absence controller for the arbeitszeitcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Controller;

use OCA\ArbeitszeitCheck\Service\AbsenceService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * AbsenceController
 */
class AbsenceController extends Controller
{
	private AbsenceService $absenceService;
	private IUserSession $userSession;

	public function __construct(
		string $appName,
		IRequest $request,
		AbsenceService $absenceService,
		IUserSession $userSession
	) {
		parent::__construct($appName, $request);
		$this->absenceService = $absenceService;
		$this->userSession = $userSession;
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

	/**
	 * Get absences endpoint
	 *
	 * @NoAdminRequired
	 * @param string|null $status Filter by status
	 * @param string|null $type Filter by type
	 * @param int|null $limit
	 * @param int|null $offset
	 * @return JSONResponse
	 */
	public function index(?string $status = null, ?string $type = null, ?int $limit = 25, ?int $offset = 0): JSONResponse
	{
		try {
			$userId = $this->getUserId();
			$filters = [];

			if ($status) {
				$filters['status'] = $status;
			}
			if ($type) {
				$filters['type'] = $type;
			}

			$absences = $this->absenceService->getAbsencesByUser($userId, $filters, $limit, $offset);

			// Safely map absences to summaries, handling any potential null DateTime issues
			$absenceSummaries = [];
			foreach ($absences as $absence) {
				try {
					$absenceSummaries[] = $absence->getSummary();
				} catch (\Throwable $e) {
					\OCP\Log\logger('arbeitszeitcheck')->error('Error getting summary for absence ' . $absence->getId() . ': ' . $e->getMessage(), ["exception" => $e]);
					continue;
				}
			}

			return new JSONResponse([
				'success' => true,
				'absences' => $absenceSummaries
			]);
		} catch (\Throwable $e) {
			return new JSONResponse([
				'success' => false,
				'error' => $e->getMessage()
			], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Get absence by ID endpoint
	 *
	 * @NoAdminRequired
	 * @param int $id Absence ID
	 * @return JSONResponse
	 */
	public function show(int $id): JSONResponse
	{
		try {
			$userId = $this->getUserId();
			$absence = $this->absenceService->getAbsence($id, $userId);

			if (!$absence) {
				return new JSONResponse([
					'success' => false,
					'error' => 'Absence not found'
				], Http::STATUS_NOT_FOUND);
			}

			return new JSONResponse([
				'success' => true,
				'absence' => $absence->getSummary()
			]);
		} catch (\Throwable $e) {
			return new JSONResponse([
				'success' => false,
				'error' => $e->getMessage()
			], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Create absence endpoint
	 *
	 * @NoAdminRequired
	 * @return JSONResponse
	 */
	public function store(): JSONResponse
	{
		try {
			$userId = $this->getUserId();
			
			// Get data from request body
			$params = $this->request->getParams();
			
			// Ensure type is a string (handle case where it might be an array)
			$type = $params['type'] ?? '';
			if (is_array($type)) {
				$type = !empty($type) ? (string)reset($type) : '';
			} else {
				$type = (string)$type;
			}
			
			$data = [
				'type' => $type,
				'start_date' => is_array($params['start_date'] ?? '') ? (string)reset($params['start_date']) : (string)($params['start_date'] ?? ''),
				'end_date' => is_array($params['end_date'] ?? '') ? (string)reset($params['end_date']) : (string)($params['end_date'] ?? ''),
				'reason' => is_array($params['reason'] ?? null) ? (string)reset($params['reason']) : ($params['reason'] ?? null)
			];

			// Validate required fields
			if (empty($data['type']) || empty($data['start_date']) || empty($data['end_date'])) {
				return new JSONResponse([
					'success' => false,
					'error' => 'Type, start_date, and end_date are required'
				], Http::STATUS_BAD_REQUEST);
			}

			$absence = $this->absenceService->createAbsence($data, $userId);

			return new JSONResponse([
				'success' => true,
				'absence' => $absence->getSummary()
			], Http::STATUS_CREATED);
		} catch (\Throwable $e) {
			return new JSONResponse([
				'success' => false,
				'error' => $e->getMessage()
			], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Update absence endpoint
	 *
	 * @NoAdminRequired
	 * @param int $id Absence ID
	 * @param string|null $start_date New start date
	 * @param string|null $end_date New end date
	 * @param string|null $reason New reason
	 * @return JSONResponse
	 */
	public function update(int $id, ?string $start_date = null, ?string $end_date = null, ?string $reason = null): JSONResponse
	{
		try {
			$userId = $this->getUserId();
			$data = [];

			if ($start_date) {
				$data['start_date'] = $start_date;
			}
			if ($end_date) {
				$data['end_date'] = $end_date;
			}
			if ($reason !== null) {
				$data['reason'] = $reason;
			}

			$absence = $this->absenceService->updateAbsence($id, $data, $userId);

			return new JSONResponse([
				'success' => true,
				'absence' => $absence->getSummary()
			]);
		} catch (\Throwable $e) {
			return new JSONResponse([
				'success' => false,
				'error' => $e->getMessage()
			], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Delete absence endpoint
	 *
	 * @NoAdminRequired
	 * @param int $id Absence ID
	 * @return JSONResponse
	 */
	public function delete(int $id): JSONResponse
	{
		try {
			$userId = $this->getUserId();
			$this->absenceService->deleteAbsence($id, $userId);

			return new JSONResponse([
				'success' => true
			]);
		} catch (\Throwable $e) {
			return new JSONResponse([
				'success' => false,
				'error' => $e->getMessage()
			], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Approve absence endpoint
	 *
	 * @NoAdminRequired
	 * @param int $id Absence ID
	 * @param string|null $comment Approval comment
	 * @return JSONResponse
	 */
	public function approve(int $id, ?string $comment = null): JSONResponse
	{
		try {
			$userId = $this->getUserId();
			$absence = $this->absenceService->approveAbsence($id, $userId, $comment);

			return new JSONResponse([
				'success' => true,
				'absence' => $absence->getSummary()
			]);
		} catch (\Throwable $e) {
			return new JSONResponse([
				'success' => false,
				'error' => $e->getMessage()
			], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Reject absence endpoint
	 *
	 * @NoAdminRequired
	 * @param int $id Absence ID
	 * @param string|null $comment Rejection comment
	 * @return JSONResponse
	 */
	public function reject(int $id, ?string $comment = null): JSONResponse
	{
		try {
			$userId = $this->getUserId();
			$absence = $this->absenceService->rejectAbsence($id, $userId, $comment);

			return new JSONResponse([
				'success' => true,
				'absence' => $absence->getSummary()
			]);
		} catch (\Throwable $e) {
			return new JSONResponse([
				'success' => false,
				'error' => $e->getMessage()
			], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Get absence statistics endpoint
	 *
	 * @NoAdminRequired
	 * @param int|null $year Year for statistics (defaults to current year)
	 * @return JSONResponse
	 */
	public function stats(?int $year = null): JSONResponse
	{
		try {
			$userId = $this->getUserId();
			if ($year === null) {
				$year = (int)date('Y');
			}

			$stats = $this->absenceService->getVacationStats($userId, $year);

			return new JSONResponse([
				'success' => true,
				'vacationStats' => [
					'used' => $stats['used'],
					'total' => $stats['entitlement'],
					'remaining' => $stats['remaining']
				],
				'sickLeaveStats' => [
					'days' => $stats['sick_days']
				]
			]);
		} catch (\Throwable $e) {
			\OCP\Log\logger('arbeitszeitcheck')->error('Error in AbsenceController::stats: ' . $e->getMessage(), ['exception' => $e]);
			return new JSONResponse([
				'success' => false,
				'error' => $e->getMessage()
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}