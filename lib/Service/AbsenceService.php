<?php

declare(strict_types=1);

/**
 * Absence service for the arbeitszeitcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Service;

use OCA\ArbeitszeitCheck\Db\Absence;
use OCA\ArbeitszeitCheck\Db\AbsenceMapper;
use OCA\ArbeitszeitCheck\Db\AuditLogMapper;
use OCA\ArbeitszeitCheck\Db\UserSettingsMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IL10N;

/**
 * Absence service for absence management business logic
 */
class AbsenceService
{
	private AbsenceMapper $absenceMapper;
	private AuditLogMapper $auditLogMapper;
	private UserSettingsMapper $userSettingsMapper;
	private IL10N $l10n;
	private ?NotificationService $notificationService;

	public function __construct(
		AbsenceMapper $absenceMapper,
		AuditLogMapper $auditLogMapper,
		UserSettingsMapper $userSettingsMapper,
		IL10N $l10n,
		?NotificationService $notificationService = null
	) {
		$this->absenceMapper = $absenceMapper;
		$this->auditLogMapper = $auditLogMapper;
		$this->userSettingsMapper = $userSettingsMapper;
		$this->l10n = $l10n;
		$this->notificationService = $notificationService;
	}

	/**
	 * Create a new absence request
	 *
	 * @param array $data Absence data
	 * @param string $userId User ID creating the request
	 * @return Absence
	 * @throws \Exception
	 */
	public function createAbsence(array $data, string $userId): Absence
	{
		$this->validateAbsenceData($data, $userId);

		$absence = new Absence();
		$absence->setUserId($userId);
		$absence->setType($data['type']);
		$absence->setStartDate($this->parseDate($data['start_date']));
		$absence->setEndDate($this->parseDate($data['end_date']));
		$absence->setReason($data['reason'] ?? null);
		$absence->setSubstituteUserId($data['substitute_user_id'] ?? null);
		$absence->setStatus(Absence::STATUS_PENDING);
		$absence->setCreatedAt(new \DateTime());
		$absence->setUpdatedAt(new \DateTime());

		// Calculate working days
		$workingDays = $absence->calculateWorkingDays();
		$absence->setDays($workingDays);

		$savedAbsence = $this->absenceMapper->insert($absence);

		// Log the action
		$this->auditLogMapper->logAction(
			$userId,
			'absence_created',
			'absence',
			$savedAbsence->getId(),
			null,
			$savedAbsence->getSummary()
		);

		return $savedAbsence;
	}

	/**
	 * Get an absence by ID
	 *
	 * @param int $id Absence ID
	 * @param string $userId User ID (for access control)
	 * @return Absence|null
	 */
	public function getAbsence(int $id, string $userId): ?Absence
	{
		try {
			$absence = $this->absenceMapper->find($id);

			// Check if user has access to this absence
			// Note: Manager/admin access is handled at the controller level
			// Managers use ManagerController methods which check permissions separately
			if ($absence->getUserId() !== $userId) {
				return null;
			}

			return $absence;
		} catch (DoesNotExistException $e) {
			return null;
		}
	}

	/**
	 * Update an absence
	 *
	 * @param int $id Absence ID
	 * @param array $data Update data
	 * @param string $userId User ID performing the update
	 * @return Absence
	 * @throws \Exception
	 */
	public function updateAbsence(int $id, array $data, string $userId): Absence
	{
		$absence = $this->getAbsence($id, $userId);
		if (!$absence) {
			throw new \Exception($this->l10n->t('Absence not found'));
		}

		// Check if absence can be updated (only pending absences can be modified)
		if ($absence->getStatus() !== Absence::STATUS_PENDING) {
			throw new \Exception($this->l10n->t('Only pending absences can be updated'));
		}

		$oldData = $absence->getSummary();

		// Update allowed fields
		if (isset($data['start_date'])) {
			$absence->setStartDate(new \DateTime($data['start_date']));
		}
		if (isset($data['end_date'])) {
			$absence->setEndDate(new \DateTime($data['end_date']));
		}
		if (isset($data['reason'])) {
			$absence->setReason($data['reason']);
		}

		$startDate = $absence->getStartDate();
		$endDate = $absence->getEndDate();
		if (!$startDate || !$endDate) {
			throw new \Exception($this->l10n->t('Start date and end date are required'));
		}
		$this->validateAbsenceData([
			'type' => $absence->getType(),
			'start_date' => $startDate->format('Y-m-d'),
			'end_date' => $endDate->format('Y-m-d'),
			'reason' => $absence->getReason()
		], $userId);

		// Recalculate working days
		$workingDays = $absence->calculateWorkingDays();
		$absence->setDays($workingDays);
		$absence->setUpdatedAt(new \DateTime());

		$updatedAbsence = $this->absenceMapper->update($absence);

		// Log the action
		$this->auditLogMapper->logAction(
			$userId,
			'absence_updated',
			'absence',
			$updatedAbsence->getId(),
			$oldData,
			$updatedAbsence->getSummary()
		);

		return $updatedAbsence;
	}

	/**
	 * Delete an absence
	 *
	 * @param int $id Absence ID
	 * @param string $userId User ID performing the deletion
	 * @throws \Exception
	 */
	public function deleteAbsence(int $id, string $userId): void
	{
		$absence = $this->getAbsence($id, $userId);
		if (!$absence) {
			throw new \Exception($this->l10n->t('Absence not found'));
		}

		// Check if absence can be deleted (only pending absences can be deleted)
		if ($absence->getStatus() !== Absence::STATUS_PENDING) {
			throw new \Exception($this->l10n->t('Only pending absences can be deleted'));
		}

		$this->absenceMapper->delete($absence);

		// Log the action
		$this->auditLogMapper->logAction(
			$userId,
			'absence_deleted',
			'absence',
			$id,
			$absence->getSummary(),
			null
		);
	}

	/**
	 * Approve an absence request
	 *
	 * @param int $id Absence ID
	 * @param string $approverId User ID of the approver
	 * @param string|null $comment Approval comment
	 * @return Absence
	 * @throws \Exception
	 */
	public function approveAbsence(int $id, string $approverId, ?string $comment = null): Absence
	{
		$absence = $this->absenceMapper->find($id);
		if (!$absence) {
			throw new \Exception($this->l10n->t('Absence not found'));
		}

		if ($absence->getStatus() !== Absence::STATUS_PENDING) {
			throw new \Exception($this->l10n->t('Absence is not pending approval'));
		}

		$oldData = $absence->getSummary();

		$absence->setStatus(Absence::STATUS_APPROVED);
		$absence->setApproverComment($comment);
		// Note: approvedBy stores approver user ID as string in database via entity mapping
		// The audit log stores it properly as string in performedBy field
		$absence->setApprovedBy(null); // Store approver ID in audit log instead (performedBy field)
		$absence->setApprovedAt(new \DateTime());
		$absence->setUpdatedAt(new \DateTime());

		$updatedAbsence = $this->absenceMapper->update($absence);

		// Log the action
		$this->auditLogMapper->logAction(
			$approverId,
			'absence_approved',
			'absence',
			$updatedAbsence->getId(),
			$oldData,
			$updatedAbsence->getSummary(),
			$approverId
		);

		// Send notification to the employee
		if ($this->notificationService) {
			$startDate = $updatedAbsence->getStartDate();
			$endDate = $updatedAbsence->getEndDate();
			$this->notificationService->notifyAbsenceApproved($updatedAbsence->getUserId(), [
				'id' => $updatedAbsence->getId(),
				'type' => $updatedAbsence->getType(),
				'start_date' => $startDate ? $startDate->format('Y-m-d') : null,
				'end_date' => $endDate ? $endDate->format('Y-m-d') : null,
				'days' => $updatedAbsence->getDays()
			]);
		}

		return $updatedAbsence;
	}

	/**
	 * Reject an absence request
	 *
	 * @param int $id Absence ID
	 * @param string $approverId User ID of the approver
	 * @param string|null $comment Rejection comment
	 * @return Absence
	 * @throws \Exception
	 */
	public function rejectAbsence(int $id, string $approverId, ?string $comment = null): Absence
	{
		$absence = $this->absenceMapper->find($id);
		if (!$absence) {
			throw new \Exception($this->l10n->t('Absence not found'));
		}

		if ($absence->getStatus() !== Absence::STATUS_PENDING) {
			throw new \Exception($this->l10n->t('Absence is not pending approval'));
		}

		$oldData = $absence->getSummary();

		$absence->setStatus(Absence::STATUS_REJECTED);
		$absence->setApproverComment($comment);
		// Note: approvedBy stores approver user ID as string in database via entity mapping
		// The audit log stores it properly as string in performedBy field
		$absence->setApprovedBy(null); // Store approver ID in audit log instead (performedBy field)
		$absence->setApprovedAt(new \DateTime());
		$absence->setUpdatedAt(new \DateTime());

		$updatedAbsence = $this->absenceMapper->update($absence);

		// Log the action
		$this->auditLogMapper->logAction(
			$approverId,
			'absence_rejected',
			'absence',
			$updatedAbsence->getId(),
			$oldData,
			$updatedAbsence->getSummary(),
			$approverId
		);

		// Send notification to the employee
		if ($this->notificationService) {
			$startDate = $updatedAbsence->getStartDate();
			$endDate = $updatedAbsence->getEndDate();
			$this->notificationService->notifyAbsenceRejected($updatedAbsence->getUserId(), [
				'id' => $updatedAbsence->getId(),
				'type' => $updatedAbsence->getType(),
				'start_date' => $startDate ? $startDate->format('Y-m-d') : null,
				'end_date' => $endDate ? $endDate->format('Y-m-d') : null,
				'days' => $updatedAbsence->getDays()
			], $comment);
		}

		return $updatedAbsence;
	}

	/**
	 * Get absences for a user with optional filters
	 *
	 * @param string $userId User ID (empty string to get all users - for manager views)
	 * @param array $filters Optional filters (status, type, date_range)
	 * @param int|null $limit
	 * @param int|null $offset
	 * @return Absence[]
	 */
	public function getAbsencesByUser(string $userId, array $filters = [], ?int $limit = null, ?int $offset = null): array
	{
		// Handle date range filter
		if (isset($filters['date_range']) && isset($filters['date_range']['start']) && isset($filters['date_range']['end'])) {
			if (!empty($userId)) {
				return $this->absenceMapper->findByUserAndDateRange(
					$userId,
					$filters['date_range']['start'],
					$filters['date_range']['end']
				);
			}
		}

		// Handle status filter
		if (isset($filters['status'])) {
			if (empty($userId)) {
				// Get all absences with this status
				return $this->absenceMapper->findByStatus($filters['status'], $limit, $offset);
			} else {
				// Get user's absences with this status
				$allAbsences = $this->absenceMapper->findByUser($userId, $limit, $offset);
				return array_filter($allAbsences, function ($absence) use ($filters) {
					return $absence->getStatus() === $filters['status'];
				});
			}
		}

		// Default: get absences for user
		if (empty($userId)) {
			return [];
		}

		return $this->absenceMapper->findByUser($userId, $limit, $offset);
	}

	/**
	 * Get vacation statistics for a user
	 *
	 * @param string $userId User ID
	 * @param int $year Year to get statistics for
	 * @return array
	 */
	public function getVacationStats(string $userId, int $year): array
	{
		try {
			$usedDays = $this->absenceMapper->getVacationDaysUsed($userId, $year);
		} catch (\Throwable $e) {
			\OCP\Log\logger('arbeitszeitcheck')->error('Error getting vacation days used: ' . $e->getMessage(), ['exception' => $e]);
			$usedDays = 0.0;
		}

		try {
			$sickDays = $this->absenceMapper->getSickLeaveDays($userId, $year);
		} catch (\Throwable $e) {
			\OCP\Log\logger('arbeitszeitcheck')->error('Error getting sick leave days: ' . $e->getMessage(), ['exception' => $e]);
			$sickDays = 0.0;
		}

		// Get total vacation entitlement from user settings (default to 25 if not set)
		try {
			$totalEntitlement = $this->userSettingsMapper->getIntegerSetting(
				$userId,
				'vacation_days_per_year',
				25 // Default value if not set
			);
		} catch (\Throwable $e) {
			\OCP\Log\logger('arbeitszeitcheck')->error('Error getting vacation entitlement: ' . $e->getMessage(), ['exception' => $e]);
			$totalEntitlement = 25; // Default value on error
		}

		return [
			'year' => $year,
			'entitlement' => $totalEntitlement,
			'used' => $usedDays,
			'remaining' => max(0, $totalEntitlement - $usedDays),
			'sick_days' => $sickDays
		];
	}

	/**
	 * Validate absence data
	 *
	 * @param array $data Absence data
	 * @param string $userId User ID
	 * @throws \Exception
	 */
	private function validateAbsenceData(array $data, string $userId): void
	{
		// Validate required fields
		if (empty($data['type']) || empty($data['start_date']) || empty($data['end_date'])) {
			throw new \Exception($this->l10n->t('Type, start date, and end date are required'));
		}

		// Validate dates (parseDate handles both ISO and German format)
		$startDate = $this->parseDate($data['start_date']);
		$endDate = $this->parseDate($data['end_date']);

		if ($startDate > $endDate) {
			throw new \Exception($this->l10n->t('Start date cannot be after end date'));
		}

		// Validate dates are not in the past (with small tolerance for same-day requests)
		$today = new \DateTime();
		$today->setTime(0, 0, 0);

		if ($startDate < $today) {
			throw new \Exception($this->l10n->t('Start date cannot be in the past'));
		}

		// Check for overlapping absences
		$overlapping = $this->absenceMapper->findOverlapping($userId, $startDate, $endDate);
		if (!empty($overlapping)) {
			throw new \Exception($this->l10n->t('Absence overlaps with existing absence'));
		}

		// Validate absence type specific rules
		// Ensure type is a string (handle case where it might be an array)
		$type = $data['type'];
		if (is_array($type)) {
			$type = !empty($type) ? (string)reset($type) : '';
		} else {
			$type = (string)$type;
		}
		if (empty($type)) {
			throw new \Exception($this->l10n->t('Absence type is required'));
		}
		$this->validateAbsenceTypeRules($type, $startDate, $endDate);
	}

	/**
	 * Validate absence type specific rules
	 *
	 * @param string $type Absence type
	 * @param \DateTime $startDate
	 * @param \DateTime $endDate
	 * @throws \Exception
	 */
	private function validateAbsenceTypeRules(string $type, \DateTime $startDate, \DateTime $endDate): void
	{
		$days = $startDate->diff($endDate)->days + 1;

		switch ($type) {
			case Absence::TYPE_VACATION:
				// Vacation can be up to 30 days
				if ($days > 30) {
					throw new \Exception($this->l10n->t('Vacation cannot exceed 30 days'));
				}
				break;

			case Absence::TYPE_SICK_LEAVE:
				// No specific limits, but should be reasonable
				if ($days > 365) {
					throw new \Exception($this->l10n->t('Sick leave duration seems unreasonable'));
				}
				break;

			case Absence::TYPE_PERSONAL_LEAVE:
				// Personal leave limited to 5 days
				if ($days > 5) {
					throw new \Exception($this->l10n->t('Personal leave cannot exceed 5 days'));
				}
				break;
		}
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
}