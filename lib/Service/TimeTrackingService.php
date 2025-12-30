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

	/**
	 * Calculate required break duration based on working hours (German labor law - ArbZG)
	 * 
	 * @param float $hoursWorked Total hours worked today (including current session)
	 * @return int Required break duration in minutes
	 */
	public function calculateRequiredBreakMinutes(float $hoursWorked): int
	{
		// German labor law (ArbZG):
		// - 6+ hours: 30 minutes break required
		// - 9+ hours: 45 minutes break required
		
		if ($hoursWorked >= 9) {
			return 45; // 45 minutes required after 9 hours
		} elseif ($hoursWorked >= 6) {
			return 30; // 30 minutes required after 6 hours
		}
		
		return 0; // No break required if less than 6 hours
	}

	/**
	 * Calculate taken break minutes for a user today
	 *
	 * @param string $userId
	 * @return float Break duration in minutes
	 */
	public function calculateTakenBreakMinutes(string $userId): float
	{
		try {
			$today = new \DateTime();
			$today->setTime(0, 0, 0);
			$tomorrow = clone $today;
			$tomorrow->modify('+1 day');

			$entries = $this->timeEntryMapper->findByUserAndDateRange($userId, $today, $tomorrow);
			
			$totalBreakMinutes = 0.0;
			foreach ($entries as $entry) {
				$breakDuration = $entry->getBreakDurationHours();
				if ($breakDuration > 0) {
					$totalBreakMinutes += $breakDuration * 60; // Convert hours to minutes
				}
			}

			return round($totalBreakMinutes, 1);
		} catch (\Throwable $e) {
			\OCP\Log\logger('arbeitszeitcheck')->error('Error calculating taken break minutes for user ' . $userId . ': ' . $e->getMessage(), ["exception" => $e]);
			return 0.0;
		}
	}

	/**
	 * Get break status for user (current session)
	 * 
	 * @param string $userId
	 * @return array Break status with warnings and suggestions
	 */
	public function getBreakStatus(string $userId): array
	{
		try {
			// Calculate hours worked today (including current active session)
			$hoursWorked = $this->getTodayHours($userId);
			
			// Add current session if active
			$activeEntry = $this->timeEntryMapper->findActiveByUser($userId);
			$breakEntry = $this->timeEntryMapper->findOnBreakByUser($userId);
			$currentEntry = $activeEntry ?: $breakEntry;
			
			if ($currentEntry) {
				$now = new \DateTime();
				$sessionStart = $currentEntry->getStartTime();
				if ($sessionStart) {
					$sessionDuration = ($now->getTimestamp() - $sessionStart->getTimestamp()) / 3600; // hours
					
					// Subtract break time if on break
					if ($currentEntry->getBreakStartTime() !== null && $currentEntry->getBreakEndTime() === null) {
						$breakHours = ($now->getTimestamp() - $currentEntry->getBreakStartTime()->getTimestamp()) / 3600;
						$sessionDuration -= $breakHours;
					} elseif ($currentEntry->getBreakStartTime() !== null && $currentEntry->getBreakEndTime() !== null) {
						$breakHours = ($currentEntry->getBreakEndTime()->getTimestamp() - $currentEntry->getBreakStartTime()->getTimestamp()) / 3600;
						$sessionDuration -= $breakHours;
					}
					
					$hoursWorked += $sessionDuration;
				}
			}
			
			$requiredBreak = $this->calculateRequiredBreakMinutes($hoursWorked);
			$takenBreak = $this->calculateTakenBreakMinutes($userId);
			$remainingBreak = max(0, $requiredBreak - $takenBreak);
			
			$warningLevel = $this->getBreakWarningLevel($hoursWorked, $takenBreak, $requiredBreak);
			
			return [
				'hours_worked' => round($hoursWorked, 2),
				'required_break_minutes' => $requiredBreak,
				'taken_break_minutes' => round($takenBreak, 1),
				'remaining_break_minutes' => round($remainingBreak, 1),
				'break_required' => $remainingBreak > 0,
				'warning_level' => $warningLevel
			];
		} catch (\Throwable $e) {
			\OCP\Log\logger('arbeitszeitcheck')->error('Error getting break status for user ' . $userId . ': ' . $e->getMessage(), ["exception" => $e]);
			return [
				'hours_worked' => 0.0,
				'required_break_minutes' => 0,
				'taken_break_minutes' => 0.0,
				'remaining_break_minutes' => 0.0,
				'break_required' => false,
				'warning_level' => 'none'
			];
		}
	}

	/**
	 * Get break warning level based on hours worked and break status
	 *
	 * @param float $hoursWorked
	 * @param float $takenBreak
	 * @param int $requiredBreak
	 * @return string Warning level: 'none', 'info', 'warning', 'critical'
	 */
	private function getBreakWarningLevel(float $hoursWorked, float $takenBreak, int $requiredBreak): string
	{
		if ($requiredBreak === 0) {
			return 'none';
		}

		$remainingBreak = max(0, $requiredBreak - $takenBreak);
		
		// Critical: 9+ hours and still need 30+ minutes
		if ($hoursWorked >= 9 && $remainingBreak >= 30) {
			return 'critical';
		}
		
		// Warning: 6+ hours and still need 15+ minutes, or approaching 9 hours
		if (($hoursWorked >= 6 && $remainingBreak >= 15) || ($hoursWorked >= 8.5 && $requiredBreak >= 45)) {
			return 'warning';
		}
		
		// Info: Break required but not urgent
		if ($remainingBreak > 0) {
			return 'info';
		}
		
		return 'none';
	}

}