<?php

declare(strict_types=1);

/**
 * TimeEntry controller for the arbeitszeitcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Controller;

use OCA\ArbeitszeitCheck\Db\TimeEntry;
use OCA\ArbeitszeitCheck\Db\TimeEntryMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * TimeEntryController
 */
class TimeEntryController extends Controller
{
	private TimeEntryMapper $timeEntryMapper;
	private IUserSession $userSession;

	public function __construct(
		string $appName,
		IRequest $request,
		TimeEntryMapper $timeEntryMapper,
		IUserSession $userSession
	) {
		parent::__construct($appName, $request);
		$this->timeEntryMapper = $timeEntryMapper;
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
	 * Parse date string - supports both ISO (yyyy-mm-dd) and German format (dd.mm.yyyy)
	 *
	 * @param string $dateString Date string in either format
	 * @return \DateTime
	 * @throws \Exception if date cannot be parsed
	 */
	private function parseDate(string $dateString): \DateTime
	{
		// Try German format first (dd.mm.yyyy)
		if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $dateString, $matches)) {
			$day = (int)$matches[1];
			$month = (int)$matches[2];
			$year = (int)$matches[3];
			
			// Validate date
			if (!checkdate($month, $day, $year)) {
				throw new \Exception('Invalid date: ' . $dateString);
			}
			
			return new \DateTime(sprintf('%04d-%02d-%02d', $year, $month, $day));
		}
		
		// Try ISO format (yyyy-mm-dd)
		try {
			return new \DateTime($dateString);
		} catch (\Throwable $e) {
			throw new \Exception('Invalid date format. Expected yyyy-mm-dd or dd.mm.yyyy: ' . $dateString);
		}
	}

	/**
	 * Get time entries endpoint
	 *
	 * @NoAdminRequired
	 * @param string|null $start_date Start date filter (Y-m-d)
	 * @param string|null $end_date End date filter (Y-m-d)
	 * @param string|null $status Status filter
	 * @param int|null $limit
	 * @param int|null $offset
	 * @return JSONResponse
	 */
	public function index(?string $start_date = null, ?string $end_date = null, ?string $status = null, ?int $limit = 25, ?int $offset = 0): JSONResponse
	{
		try {
			$userId = $this->getUserId();
			$filters = [];

			if ($start_date) {
				$filters['start_date'] = $start_date;
			}
			if ($end_date) {
				$filters['end_date'] = $end_date;
			}
			if ($status) {
				$filters['status'] = $status;
			}
			if ($limit) {
				$filters['limit'] = $limit;
			}
			if ($offset) {
				$filters['offset'] = $offset;
			}

			// Build filters array for mapper count method (uses database filtering)
			$countFilters = ['user_id' => $userId];
			if ($start_date) {
				try {
					$countFilters['start_date'] = new \DateTime($start_date);
				} catch (\Throwable $e) {
					\OCP\Log\logger('arbeitszeitcheck')->error('Invalid start_date format: ' . $start_date, ['exception' => $e]);
					return new JSONResponse([
						'success' => false,
						'error' => 'Invalid start date format'
					], Http::STATUS_BAD_REQUEST);
				}
			}
			if ($end_date) {
				try {
					$countFilters['end_date'] = new \DateTime($end_date);
				} catch (\Throwable $e) {
					\OCP\Log\logger('arbeitszeitcheck')->error('Invalid end_date format: ' . $end_date, ['exception' => $e]);
					return new JSONResponse([
						'success' => false,
						'error' => 'Invalid end date format'
					], Http::STATUS_BAD_REQUEST);
				}
			}
			if ($status) {
				$countFilters['status'] = $status;
			}

			// Get total count for pagination using mapper's count method (efficient database query)
			try {
				$totalCount = $this->timeEntryMapper->count($countFilters);
			} catch (\Throwable $e) {
				\OCP\Log\logger('arbeitszeitcheck')->error('Error counting time entries: ' . $e->getMessage(), ['exception' => $e]);
				$totalCount = 0;
			}

			// Use findByUserAndDateRange when date filters are provided, otherwise use findByUser
			// Wrap in try-catch to handle any entity mapping errors
			try {
				if ($start_date || $end_date) {
					try {
						$startDateTime = $start_date ? new \DateTime($start_date) : new \DateTime('1970-01-01');
						$startDateTime->setTime(0, 0, 0);
					} catch (\Throwable $e) {
						\OCP\Log\logger('arbeitszeitcheck')->error('Invalid start_date format: ' . $start_date, ['exception' => $e]);
						return new JSONResponse([
							'success' => false,
							'error' => 'Invalid start date format'
						], Http::STATUS_BAD_REQUEST);
					}
					try {
						$endDateTime = $end_date ? new \DateTime($end_date) : new \DateTime('2099-12-31');
						$endDateTime->setTime(23, 59, 59);
					} catch (\Throwable $e) {
						\OCP\Log\logger('arbeitszeitcheck')->error('Invalid end_date format: ' . $end_date, ['exception' => $e]);
						return new JSONResponse([
							'success' => false,
							'error' => 'Invalid end date format'
						], Http::STATUS_BAD_REQUEST);
					}
					$allEntries = $this->timeEntryMapper->findByUserAndDateRange($userId, $startDateTime, $endDateTime);
				} else {
					$allEntries = $this->timeEntryMapper->findByUser($userId);
				}
			} catch (\Throwable $e) {
				\OCP\Log\logger('arbeitszeitcheck')->error('Error loading time entries for user ' . $userId, ['exception' => $e]);
				// Return empty array instead of failing completely
				$allEntries = [];
			}

			// Apply status filter if provided (date filters already applied via findByUserAndDateRange)
			if ($status) {
				$allEntries = array_filter($allEntries, function($entry) use ($status) {
					return $entry->getStatus() === $status;
				});
			}

			// Apply pagination to filtered entries
			$entries = array_slice($allEntries, $offset ?? 0, $limit ?? 25);

			// Safely map entries to summaries, handling any potential null DateTime issues
			$entrySummaries = [];
			foreach ($entries as $entry) {
				try {
					$entrySummaries[] = $entry->getSummary();
				} catch (\Throwable $e) {
					// Log the error but continue processing other entries
					\OCP\Log\logger('arbeitszeitcheck')->error('Error getting summary for entry ' . $entry->getId(), ['exception' => $e]);
					// Skip this entry
					continue;
				}
			}

			return new JSONResponse([
				'success' => true,
				'entries' => $entrySummaries,
				'total' => $totalCount
			]);
		} catch (\Throwable $e) {
			\OCP\Log\logger('arbeitszeitcheck')->error('Error in TimeEntryController: ' . $e->getMessage(), ['exception' => $e]);
			return new JSONResponse([
				'success' => false,
				'error' => $e->getMessage()
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get time entry by ID endpoint
	 *
	 * @NoAdminRequired
	 * @param int $id Time entry ID
	 * @return JSONResponse
	 */
	public function show(int $id): JSONResponse
	{
		try {
			$userId = $this->getUserId();
			$entry = $this->timeEntryMapper->find($id);

			// Check ownership
			if ($entry->getUserId() !== $userId) {
				return new JSONResponse([
					'success' => false,
					'error' => 'Access denied'
				], Http::STATUS_FORBIDDEN);
			}

			return new JSONResponse([
				'success' => true,
				'entry' => $entry->getSummary()
			]);
		} catch (\Throwable $e) {
			\OCP\Log\logger('arbeitszeitcheck')->error('Error in TimeEntryController: ' . $e->getMessage(), ['exception' => $e]);
			return new JSONResponse([
				'success' => false,
				'error' => $e->getMessage()
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Create time entry endpoint (manual entry)
	 *
	 * @NoAdminRequired
	 * @param string $date Date (Y-m-d)
	 * @param float $hours Hours worked
	 * @param string|null $description Description
	 * @param string|null $project_check_project_id Project ID
	 * @return JSONResponse
	 */
	public function store(string $date, float $hours, ?string $description = null, ?string $project_check_project_id = null): JSONResponse
	{
		try {
			$userId = $this->getUserId();

			$timeEntry = new TimeEntry();
			$timeEntry->setUserId($userId);
			// Parse date - supports both ISO (yyyy-mm-dd) and German format (dd.mm.yyyy)
			$startDateTime = $this->parseDate($date);
			$startDateTime->setTime(9, 0, 0); // Default start time 9:00
			$timeEntry->setStartTime($startDateTime);
			
			// Calculate end time based on hours
			$endDateTime = clone $startDateTime;
			$endDateTime->modify('+' . round($hours * 3600) . ' seconds');
			$timeEntry->setEndTime($endDateTime);
			$timeEntry->setDescription($description);
			$timeEntry->setProjectCheckProjectId($project_check_project_id);
			$timeEntry->setStatus(TimeEntry::STATUS_COMPLETED);
			$timeEntry->setIsManualEntry(true);
			$timeEntry->setJustification('Manual entry created via employee portal');
			$timeEntry->setCreatedAt(new \DateTime());
			$timeEntry->setUpdatedAt(new \DateTime());

			$savedEntry = $this->timeEntryMapper->insert($timeEntry);

			return new JSONResponse([
				'success' => true,
				'entry' => $savedEntry->getSummary()
			], Http::STATUS_CREATED);
		} catch (\Throwable $e) {
			\OCP\Log\logger('arbeitszeitcheck')->error('Error in TimeEntryController: ' . $e->getMessage(), ['exception' => $e]);
			return new JSONResponse([
				'success' => false,
				'error' => $e->getMessage()
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Update time entry endpoint
	 *
	 * @NoAdminRequired
	 * @param int $id Time entry ID
	 * @param string|null $date New date
	 * @param float|null $hours New hours
	 * @param string|null $description New description
	 * @param string|null $project_check_project_id New project ID
	 * @return JSONResponse
	 */
	public function update(int $id, ?string $date = null, ?float $hours = null, ?string $description = null, ?string $project_check_project_id = null): JSONResponse
	{
		try {
			$userId = $this->getUserId();
			$entry = $this->timeEntryMapper->find($id);

			// Check ownership
			if ($entry->getUserId() !== $userId) {
				return new JSONResponse([
					'success' => false,
					'error' => 'Access denied'
				], Http::STATUS_FORBIDDEN);
			}

			// Check if entry can be edited (only manual entries or pending approval)
			if (!$entry->getIsManualEntry() && $entry->getStatus() !== TimeEntry::STATUS_PENDING_APPROVAL) {
				return new JSONResponse([
					'success' => false,
					'error' => 'Cannot edit automatic time entries'
				], Http::STATUS_BAD_REQUEST);
			}

			if ($date) {
				$entry->setStartTime(new \DateTime($date));
			}
			if ($hours !== null) {
				// Calculate end time based on hours from start time
				if ($entry->getStartTime()) {
					$startTime = clone $entry->getStartTime();
					$endTime = clone $startTime;
					$endTime->modify('+' . round($hours * 3600) . ' seconds');
					$entry->setEndTime($endTime);
				}
			}
			if ($description !== null) {
				$entry->setDescription($description);
			}
			if ($project_check_project_id !== null) {
				$entry->setProjectCheckProjectId($project_check_project_id);
			}

			$entry->setUpdatedAt(new \DateTime());
			$updatedEntry = $this->timeEntryMapper->update($entry);

			return new JSONResponse([
				'success' => true,
				'entry' => $updatedEntry->getSummary()
			]);
		} catch (\Throwable $e) {
			\OCP\Log\logger('arbeitszeitcheck')->error('Error in TimeEntryController: ' . $e->getMessage(), ['exception' => $e]);
			return new JSONResponse([
				'success' => false,
				'error' => $e->getMessage()
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Request correction for a time entry
	 * Changes entry status to pending_approval and sends notification to manager
	 *
	 * @NoAdminRequired
	 * @param int $id Time entry ID
	 * @param string|null $justification Reason for correction request
	 * @param string|null $newDate Proposed new date (Y-m-d format)
	 * @param float|null $newHours Proposed new hours
	 * @param string|null $newDescription Proposed new description
	 * @return JSONResponse
	 */
	public function requestCorrection(int $id): JSONResponse
	{
		try {
			$userId = $this->getUserId();
			$entry = $this->timeEntryMapper->find($id);

			// Check ownership
			if ($entry->getUserId() !== $userId) {
				return new JSONResponse([
					'success' => false,
					'error' => 'Access denied'
				], Http::STATUS_FORBIDDEN);
			}

			// Check if entry can be corrected (not already pending)
			$currentStatus = $entry->getStatus();
			if ($currentStatus === TimeEntry::STATUS_PENDING_APPROVAL) {
				return new JSONResponse([
					'success' => false,
					'error' => 'Correction request already pending'
				], Http::STATUS_BAD_REQUEST);
			}

			// Get data from request body
			$params = $this->request->getParams();
			$justification = $params['justification'] ?? null;
			$newDate = $params['newDate'] ?? null;
			$newHours = isset($params['newHours']) ? (float)$params['newHours'] : null;
			$newDescription = $params['newDescription'] ?? null;

			// Require justification for correction request
			if (empty($justification)) {
				return new JSONResponse([
					'success' => false,
					'error' => 'Justification is required for correction requests'
				], Http::STATUS_BAD_REQUEST);
			}

			// Store proposed changes in justification field (format: JSON with original and proposed values)
			$startTime = $entry->getStartTime();
			if (!$startTime) {
				return new JSONResponse([
					'success' => false,
					'error' => 'Time entry has no start time'
				], Http::STATUS_BAD_REQUEST);
			}
			$originalData = [
				'date' => $startTime->format('Y-m-d'),
				'hours' => $entry->getDurationHours(),
				'description' => $entry->getDescription()
			];

			$proposedData = [];
			if ($newDate) {
				$proposedData['date'] = $newDate;
			}
			if ($newHours !== null) {
				$proposedData['hours'] = $newHours;
			}
			if ($newDescription !== null) {
				$proposedData['description'] = $newDescription;
			}

			$correctionData = [
				'justification' => $justification,
				'original' => $originalData,
				'proposed' => $proposedData,
				'requested_at' => date('c')
			];

			// Update entry with correction request
			$entry->setJustification(json_encode($correctionData));
			$entry->setStatus(TimeEntry::STATUS_PENDING_APPROVAL);
			$entry->setUpdatedAt(new \DateTime());

			// Apply proposed changes temporarily (will be finalized on approval)
			if ($newDate) {
				$entry->setStartTime($this->parseDate($newDate));
				if ($entry->getEndTime() && $newHours !== null) {
					$endTime = clone $entry->getStartTime();
					$endTime->modify('+' . round($newHours * 3600) . ' seconds');
					$entry->setEndTime($endTime);
				}
			} elseif ($newHours !== null && $entry->getStartTime()) {
				$endTime = clone $entry->getStartTime();
				$endTime->modify('+' . round($newHours * 3600) . ' seconds');
				$entry->setEndTime($endTime);
			}
			if ($newDescription !== null) {
				$entry->setDescription($newDescription);
			}

			$updatedEntry = $this->timeEntryMapper->update($entry);

			// Create audit log
			$auditLogMapper = \OCP\Server::get(\OCA\ArbeitszeitCheck\Db\AuditLogMapper::class);
			$auditLogMapper->logAction(
				$userId,
				'time_entry_correction_requested',
				'time_entry',
				$id, // entityId
				$originalData, // oldValues
				[
					'original_status' => $currentStatus,
					'justification' => $justification,
					'proposed_changes' => $proposedData
				] // newValues
			);

			// Send notification to manager (if manager exists)
			try {
				$notificationService = \OCP\Server::get(\OCA\ArbeitszeitCheck\Service\NotificationService::class);
				$notificationService->notifyTimeEntryCorrectionRequested(
					$userId,
					$updatedEntry->getSummary(),
					$justification
				);
			} catch (\Throwable $e) {
				// Notification failure shouldn't block the correction request
				\OCP\Log\logger('arbeitszeitcheck')->warning('Failed to send correction request notification', ['exception' => $e]);
			}

			return new JSONResponse([
				'success' => true,
				'entry' => $updatedEntry->getSummary(),
				'message' => 'Correction request submitted successfully'
			]);
		} catch (\Throwable $e) {
			\OCP\Log\logger('arbeitszeitcheck')->error('Error in TimeEntryController: ' . $e->getMessage(), ['exception' => $e]);
			return new JSONResponse([
				'success' => false,
				'error' => $e->getMessage()
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Delete time entry endpoint
	 *
	 * @NoAdminRequired
	 * @param int $id Time entry ID
	 * @return JSONResponse
	 */
	public function delete(int $id): JSONResponse
	{
		try {
			$userId = $this->getUserId();
			$entry = $this->timeEntryMapper->find($id);

			// Check ownership
			if ($entry->getUserId() !== $userId) {
				return new JSONResponse([
					'success' => false,
					'error' => 'Access denied'
				], Http::STATUS_FORBIDDEN);
			}

			// Check if entry can be deleted (only manual entries)
			if (!$entry->getIsManualEntry()) {
				return new JSONResponse([
					'success' => false,
					'error' => 'Cannot delete automatic time entries'
				], Http::STATUS_BAD_REQUEST);
			}

			$this->timeEntryMapper->delete($entry);

			return new JSONResponse([
				'success' => true
			]);
		} catch (\Throwable $e) {
			\OCP\Log\logger('arbeitszeitcheck')->error('Error in TimeEntryController: ' . $e->getMessage(), ['exception' => $e]);
			return new JSONResponse([
				'success' => false,
				'error' => $e->getMessage()
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get time entry statistics endpoint
	 *
	 * @NoAdminRequired
	 * @param string|null $start_date Start date for statistics
	 * @param string|null $end_date End date for statistics
	 * @return JSONResponse
	 */
	public function stats(?string $start_date = null, ?string $end_date = null): JSONResponse
	{
		try {
			$userId = $this->getUserId();

			$start = $start_date ? new \DateTime($start_date) : (new \DateTime())->modify('-30 days');
			$end = $end_date ? new \DateTime($end_date) : new \DateTime();
			$start->setTime(0, 0, 0);
			$end->setTime(23, 59, 59);

			$totalHours = $this->timeEntryMapper->getTotalHoursByUserAndDateRange($userId, $start, $end);
			$totalBreakHours = $this->timeEntryMapper->getTotalBreakHoursByUserAndDateRange($userId, $start, $end);
			$totalEntries = $this->timeEntryMapper->countByUser($userId);

			$workingDays = $this->calculateWorkingDays($start, $end);
			$averageHoursPerDay = $workingDays > 0 ? $totalHours / $workingDays : 0;

			// Calculate overtime using OvertimeService
			$overtimeService = \OCP\Server::get(\OCA\ArbeitszeitCheck\Service\OvertimeService::class);
			$overtimeData = $overtimeService->calculateOvertime($userId, $start, $end);

			return new JSONResponse([
				'success' => true,
				'stats' => [
					'total_hours' => $totalHours,
					'total_break_hours' => $totalBreakHours,
					'total_entries' => $totalEntries,
					'working_days' => $workingDays,
					'average_hours_per_day' => $averageHoursPerDay,
					'overtime' => [
						'overtime_hours' => $overtimeData['overtime_hours'],
						'required_hours' => $overtimeData['required_hours'],
						'total_hours_worked' => $overtimeData['total_hours_worked'],
						'cumulative_balance' => $overtimeData['cumulative_balance_after']
					],
					'period' => [
						'start' => $start->format('Y-m-d'),
						'end' => $end->format('Y-m-d')
					]
				]
			]);
		} catch (\Throwable $e) {
			\OCP\Log\logger('arbeitszeitcheck')->error('Error in TimeEntryController: ' . $e->getMessage(), ['exception' => $e]);
			return new JSONResponse([
				'success' => false,
				'error' => $e->getMessage()
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Calculate working days between two dates (excluding weekends)
	 *
	 * @param \DateTime $start
	 * @param \DateTime $end
	 * @return int
	 */
	private function calculateWorkingDays(\DateTime $start, \DateTime $end): int
	{
		$workingDays = 0;
		$current = clone $start;

		while ($current <= $end) {
			// Monday = 1, Sunday = 7
			if ($current->format('N') < 6) { // Monday to Friday
				$workingDays++;
			}
			$current->modify('+1 day');
		}

		return $workingDays;
	}

	/**
	 * API: Get time entries (alias for index)
	 *
	 * @NoAdminRequired
	 * @param string|null $start_date
	 * @param string|null $end_date
	 * @param string|null $status
	 * @param int|null $limit
	 * @param int|null $offset
	 * @return JSONResponse
	 */
	public function apiIndex(?string $start_date = null, ?string $end_date = null, ?string $status = null, ?int $limit = 25, ?int $offset = 0): JSONResponse
	{
		return $this->index($start_date, $end_date, $status, $limit, $offset);
	}

	/**
	 * API: Get time entry by ID (alias for show)
	 *
	 * @NoAdminRequired
	 * @param int $id
	 * @return JSONResponse
	 */
	public function apiShow(int $id): JSONResponse
	{
		return $this->show($id);
	}

	/**
	 * API: Create time entry (accepts JSON body)
	 *
	 * @NoAdminRequired
	 * @return JSONResponse
	 */
	public function apiStore(): JSONResponse
	{
		$params = $this->request->getParams();
		$date = $params['date'] ?? null;
		$hours = isset($params['hours']) ? (float)$params['hours'] : null;
		$description = $params['description'] ?? null;
		$project_check_project_id = $params['project_check_project_id'] ?? $params['projectCheckProjectId'] ?? null;

		if (!$date || $hours === null) {
			return new JSONResponse([
				'success' => false,
				'error' => 'Date and hours are required'
			], Http::STATUS_BAD_REQUEST);
		}

		return $this->store($date, $hours, $description, $project_check_project_id);
	}

	/**
	 * API: Update time entry (accepts JSON body)
	 *
	 * @NoAdminRequired
	 * @param int $id
	 * @return JSONResponse
	 */
	public function apiUpdate(int $id): JSONResponse
	{
		$params = $this->request->getParams();
		$date = $params['date'] ?? null;
		$hours = isset($params['hours']) ? (float)$params['hours'] : null;
		$description = $params['description'] ?? null;
		$project_check_project_id = $params['project_check_project_id'] ?? $params['projectCheckProjectId'] ?? null;

		return $this->update($id, $date, $hours, $description, $project_check_project_id);
	}

	/**
	 * API: Delete time entry (alias for delete)
	 *
	 * @NoAdminRequired
	 * @param int $id
	 * @return JSONResponse
	 */
	public function apiDelete(int $id): JSONResponse
	{
		return $this->delete($id);
	}

	/**
	 * API: Get overtime information
	 *
	 * @NoAdminRequired
	 * @param string|null $period Period: 'daily', 'weekly', 'monthly', 'yearly', or 'custom'
	 * @param string|null $start_date Start date for custom period (Y-m-d)
	 * @param string|null $end_date End date for custom period (Y-m-d)
	 * @return JSONResponse
	 */
	public function getOvertime(?string $period = 'monthly', ?string $start_date = null, ?string $end_date = null): JSONResponse
	{
		try {
			$userId = $this->getUserId();
			$overtimeService = \OCP\Server::get(\OCA\ArbeitszeitCheck\Service\OvertimeService::class);

			$overtimeData = match($period) {
				'daily' => $overtimeService->getDailyOvertime($userId),
				'weekly' => $overtimeService->getWeeklyOvertime($userId),
				'monthly' => $overtimeService->calculateMonthlyOvertime($userId),
				'yearly' => $overtimeService->calculateYearlyOvertime($userId),
				'custom' => $this->getCustomPeriodOvertime($overtimeService, $userId, $start_date, $end_date),
				default => $overtimeService->calculateMonthlyOvertime($userId)
			};

			return new JSONResponse([
				'success' => true,
				'overtime' => $overtimeData
			]);
		} catch (\Throwable $e) {
			\OCP\Log\logger('arbeitszeitcheck')->error('Error in TimeEntryController: ' . $e->getMessage(), ['exception' => $e]);
			return new JSONResponse([
				'success' => false,
				'error' => $e->getMessage()
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get overtime balance (cumulative)
	 *
	 * @NoAdminRequired
	 * @return JSONResponse
	 */
	public function getOvertimeBalance(): JSONResponse
	{
		try {
			$userId = $this->getUserId();
			$overtimeService = \OCP\Server::get(\OCA\ArbeitszeitCheck\Service\OvertimeService::class);
			$balance = $overtimeService->getOvertimeBalance($userId);

			return new JSONResponse([
				'success' => true,
				'balance' => $balance
			]);
		} catch (\Throwable $e) {
			\OCP\Log\logger('arbeitszeitcheck')->error('Error in TimeEntryController: ' . $e->getMessage(), ['exception' => $e]);
			return new JSONResponse([
				'success' => false,
				'error' => $e->getMessage()
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Helper method to get custom period overtime
	 *
	 * @param OvertimeService $overtimeService
	 * @param string $userId
	 * @param string|null $start_date
	 * @param string|null $end_date
	 * @return array
	 */
	private function getCustomPeriodOvertime($overtimeService, string $userId, ?string $start_date, ?string $end_date): array
	{
		if (!$start_date || !$end_date) {
			throw new \Exception('Start date and end date are required for custom period');
		}

		$start = new \DateTime($start_date);
		$start->setTime(0, 0, 0);
		$end = new \DateTime($end_date);
		$end->setTime(23, 59, 59);

		return $overtimeService->calculateOvertime($userId, $start, $end);
	}
}