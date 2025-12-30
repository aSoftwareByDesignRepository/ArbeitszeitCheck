<?php

declare(strict_types=1);

/**
 * TimeTracking service for the arbeitszeitcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Service;

use OCA\ArbeitszeitCheck\Db\TimeEntry;
use OCA\ArbeitszeitCheck\Db\TimeEntryMapper;
use OCA\ArbeitszeitCheck\Db\ComplianceViolationMapper;
use OCA\ArbeitszeitCheck\Db\AuditLogMapper;
use OCA\ArbeitszeitCheck\Service\ProjectCheckIntegrationService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IL10N;

/**
 * TimeTracking service for time tracking business logic
 */
class TimeTrackingService
{
	private TimeEntryMapper $timeEntryMapper;
	private ComplianceViolationMapper $violationMapper;
	private AuditLogMapper $auditLogMapper;
	private ProjectCheckIntegrationService $projectCheckService;
	private IL10N $l10n;

	public function __construct(
		TimeEntryMapper $timeEntryMapper,
		ComplianceViolationMapper $violationMapper,
		AuditLogMapper $auditLogMapper,
		ProjectCheckIntegrationService $projectCheckService,
		IL10N $l10n
	) {
		$this->timeEntryMapper = $timeEntryMapper;
		$this->violationMapper = $violationMapper;
		$this->auditLogMapper = $auditLogMapper;
		$this->projectCheckService = $projectCheckService;
		$this->l10n = $l10n;
	}

	/**
	 * Clock in a user (start working)
	 *
	 * @param string $userId
	 * @param string|null $projectCheckProjectId
	 * @param string|null $description
	 * @return TimeEntry
	 * @throws \Exception
	 */
	public function clockIn(string $userId, ?string $projectCheckProjectId = null, ?string $description = null): TimeEntry
	{
		// Check if user is already clocked in
		$activeEntry = $this->timeEntryMapper->findActiveByUser($userId);
		if ($activeEntry !== null) {
			throw new \Exception($this->l10n->t('User is already clocked in'));
		}

		// Check if user is on break
		$breakEntry = $this->timeEntryMapper->findOnBreakByUser($userId);
		if ($breakEntry !== null) {
			throw new \Exception($this->l10n->t('User is currently on break. End break first.'));
		}

		// Validate ProjectCheck project if provided
		if ($projectCheckProjectId && !$this->projectCheckService->projectExists($projectCheckProjectId)) {
			throw new \Exception($this->l10n->t('Selected project does not exist'));
		}

		// Check compliance rules before clocking in
		$this->checkComplianceBeforeClockIn($userId);

		$timeEntry = new TimeEntry();
		$timeEntry->setUserId($userId);
		$timeEntry->setStartTime(new \DateTime());
		$timeEntry->setStatus(TimeEntry::STATUS_ACTIVE);
		$timeEntry->setIsManualEntry(false);
		$timeEntry->setProjectCheckProjectId($projectCheckProjectId);
		$timeEntry->setDescription($description);
		$timeEntry->setCreatedAt(new \DateTime());
		$timeEntry->setUpdatedAt(new \DateTime());

		$savedEntry = $this->timeEntryMapper->insert($timeEntry);

		// Log the action
		try {
			$summary = $savedEntry->getSummary();
		} catch (\Throwable $e) {
			\OCP\Log\logger('arbeitszeitcheck')->error('Error getting summary for clock_in audit log: ' . $e->getMessage(), ["exception" => $e]);
			$summary = ['id' => $savedEntry->getId(), 'userId' => $userId, 'status' => $savedEntry->getStatus()];
		}
		$this->auditLogMapper->logAction(
			$userId,
			'clock_in',
			'time_entry',
			$savedEntry->getId(),
			null,
			$summary
		);

		return $savedEntry;
	}

	/**
	 * Clock out a user (end working)
	 *
	 * @param string $userId
	 * @return TimeEntry
	 * @throws \Exception
	 */
	public function clockOut(string $userId): TimeEntry
	{
		$activeEntry = $this->timeEntryMapper->findActiveByUser($userId);
		if ($activeEntry === null) {
			throw new \Exception($this->l10n->t('User is not currently clocked in'));
		}

		$now = new \DateTime();
		$activeEntry->setEndTime($now);
		$activeEntry->setStatus(TimeEntry::STATUS_COMPLETED);
		$activeEntry->setUpdatedAt($now);

		$updatedEntry = $this->timeEntryMapper->update($activeEntry);

		// Check compliance rules after clocking out
		$this->checkComplianceAfterClockOut($updatedEntry);

		// Log the action
		try {
			$oldSummary = $activeEntry->getSummary();
		} catch (\Throwable $e) {
			\OCP\Log\logger('arbeitszeitcheck')->error('Error getting old summary for clock_out audit log: ' . $e->getMessage(), ["exception" => $e]);
			$oldSummary = ['id' => $activeEntry->getId(), 'userId' => $userId];
		}
		try {
			$newSummary = $updatedEntry->getSummary();
		} catch (\Throwable $e) {
			\OCP\Log\logger('arbeitszeitcheck')->error('Error getting new summary for clock_out audit log: ' . $e->getMessage(), ["exception" => $e]);
			$newSummary = ['id' => $updatedEntry->getId(), 'userId' => $userId];
		}
		$this->auditLogMapper->logAction(
			$userId,
			'clock_out',
			'time_entry',
			$updatedEntry->getId(),
			$oldSummary,
			$newSummary
		);

		return $updatedEntry;
	}

	/**
	 * Start break for a user
	 *
	 * @param string $userId
	 * @return TimeEntry
	 * @throws \Exception
	 */
	public function startBreak(string $userId): TimeEntry
	{
		$activeEntry = $this->timeEntryMapper->findActiveByUser($userId);
		if ($activeEntry === null) {
			throw new \Exception($this->l10n->t('User is not currently clocked in'));
		}

		if ($activeEntry->getBreakStartTime() !== null) {
			throw new \Exception($this->l10n->t('Break is already started'));
		}

		$now = new \DateTime();
		$activeEntry->setBreakStartTime($now);
		$activeEntry->setStatus(TimeEntry::STATUS_BREAK);
		$activeEntry->setUpdatedAt($now);

		$updatedEntry = $this->timeEntryMapper->update($activeEntry);

		// Log the action
		try {
			$oldSummary = $activeEntry->getSummary();
		} catch (\Throwable $e) {
			\OCP\Log\logger('arbeitszeitcheck')->error('Error getting old summary for start_break audit log: ' . $e->getMessage(), ["exception" => $e]);
			$oldSummary = ['id' => $activeEntry->getId(), 'userId' => $userId];
		}
		try {
			$newSummary = $updatedEntry->getSummary();
		} catch (\Throwable $e) {
			\OCP\Log\logger('arbeitszeitcheck')->error('Error getting new summary for start_break audit log: ' . $e->getMessage(), ["exception" => $e]);
			$newSummary = ['id' => $updatedEntry->getId(), 'userId' => $userId];
		}
		$this->auditLogMapper->logAction(
			$userId,
			'start_break',
			'time_entry',
			$updatedEntry->getId(),
			$oldSummary,
			$newSummary
		);

		return $updatedEntry;
	}

	/**
	 * End break for a user
	 *
	 * @param string $userId
	 * @return TimeEntry
	 * @throws \Exception
	 */
	public function endBreak(string $userId): TimeEntry
	{
		$breakEntry = $this->timeEntryMapper->findOnBreakByUser($userId);
		if ($breakEntry === null) {
			throw new \Exception($this->l10n->t('User is not currently on break'));
		}

		$now = new \DateTime();
		$breakEntry->setBreakEndTime($now);
		$breakEntry->setStatus(TimeEntry::STATUS_ACTIVE);
		$breakEntry->setUpdatedAt($now);

		$updatedEntry = $this->timeEntryMapper->update($breakEntry);

		// Log the action
		try {
			$oldSummary = $breakEntry->getSummary();
		} catch (\Throwable $e) {
			\OCP\Log\logger('arbeitszeitcheck')->error('Error getting old summary for end_break audit log: ' . $e->getMessage(), ["exception" => $e]);
			$oldSummary = ['id' => $breakEntry->getId(), 'userId' => $userId];
		}
		try {
			$newSummary = $updatedEntry->getSummary();
		} catch (\Throwable $e) {
			\OCP\Log\logger('arbeitszeitcheck')->error('Error getting new summary for end_break audit log: ' . $e->getMessage(), ["exception" => $e]);
			$newSummary = ['id' => $updatedEntry->getId(), 'userId' => $userId];
		}
		$this->auditLogMapper->logAction(
			$userId,
			'end_break',
			'time_entry',
			$updatedEntry->getId(),
			$oldSummary,
			$newSummary
		);

		return $updatedEntry;
	}

	/**
	 * Get current status for a user
	 *
	 * @param string $userId
	 * @return array
	 */
	public function getStatus(string $userId): array
	{
		try {
			$activeEntry = $this->timeEntryMapper->findActiveByUser($userId);
			$breakEntry = $this->timeEntryMapper->findOnBreakByUser($userId);

			$currentEntry = $activeEntry ?: $breakEntry;

			if ($currentEntry === null) {
				return [
					'status' => 'clocked_out',
					'current_entry' => null,
					'working_today_hours' => $this->getTodayHours($userId),
					'current_session_duration' => null
				];
			}

			$now = new \DateTime();
			$sessionStart = $currentEntry->getStartTime();
			$sessionDuration = $sessionStart ? ($now->getTimestamp() - $sessionStart->getTimestamp()) : 0;

			// Safely get summary, handling any potential errors
			$entrySummary = null;
			try {
				$entrySummary = $currentEntry->getSummary();
			} catch (\Throwable $e) {
				\OCP\Log\logger('arbeitszeitcheck')->error('Error getting summary for current entry ' . $currentEntry->getId() . ' in getStatus: ' . $e->getMessage(), ["exception" => $e]);
				// Return a minimal summary if getSummary fails
				$entrySummary = [
					'id' => $currentEntry->getId(),
					'userId' => $currentEntry->getUserId(),
					'status' => $currentEntry->getStatus(),
					'startTime' => $sessionStart ? $sessionStart->format('c') : null
				];
			}

			return [
				'status' => $currentEntry->getStatus(),
				'current_entry' => $entrySummary,
				'working_today_hours' => $this->getTodayHours($userId),
				'current_session_duration' => $sessionDuration
			];
		} catch (\Throwable $e) {
			\OCP\Log\logger('arbeitszeitcheck')->error('Error in getStatus for user ' . $userId . ': ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString(), ["exception" => $e]);
			// Return a safe default status
			return [
				'status' => 'clocked_out',
				'current_entry' => null,
				'working_today_hours' => 0.0,
				'current_session_duration' => null
			];
		}
	}

	/**
	 * Get hours worked today by a user
	 *
	 * @param string $userId
	 * @return float
	 */
	public function getTodayHours(string $userId): float
	{
		try {
			$today = new \DateTime();
			$today->setTime(0, 0, 0);
			$tomorrow = clone $today;
			$tomorrow->modify('+1 day');

			return $this->timeEntryMapper->getTotalHoursByUserAndDateRange($userId, $today, $tomorrow);
		} catch (\Throwable $e) {
			\OCP\Log\logger('arbeitszeitcheck')->error('Error getting today hours for user ' . $userId . ': ' . $e->getMessage(), ["exception" => $e]);
			return 0.0;
		}
	}

	/**
	 * Check compliance rules before clocking in
	 *
	 * @param string $userId
	 * @throws \Exception
	 */
	private function checkComplianceBeforeClockIn(string $userId): void
	{
		$complianceService = \OCP\Server::get(ComplianceService::class);
		$issues = $complianceService->checkComplianceBeforeClockIn($userId);

		if (!empty($issues)) {
			$criticalIssues = array_filter($issues, fn($issue) => $issue['severity'] === 'error');
			if (!empty($criticalIssues)) {
				$firstIssue = reset($criticalIssues);
				throw new \Exception($firstIssue['message']);
			}
		}
	}

	/**
	 * Check compliance rules after clocking out
	 *
	 * @param TimeEntry $timeEntry
	 */
	private function checkComplianceAfterClockOut(TimeEntry $timeEntry): void
	{
		$complianceService = \OCP\Server::get(ComplianceService::class);
		$complianceService->checkComplianceAfterClockOut($timeEntry);
	}

}