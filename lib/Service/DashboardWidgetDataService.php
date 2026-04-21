<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Service;

use OCA\ArbeitszeitCheck\Db\Absence;
use OCA\ArbeitszeitCheck\Db\AbsenceMapper;
use OCP\IUserManager;

class DashboardWidgetDataService {
	private const MAX_MANAGER_MEMBERS = 50;
	private const MAX_ADMIN_USERS = 200;
	private const MAX_ADMIN_WIDGET_USERS = 50;

	public function __construct(
		private readonly TimeTrackingService $timeTrackingService,
		private readonly OvertimeService $overtimeService,
		private readonly AbsenceService $absenceService,
		private readonly AbsenceMapper $absenceMapper,
		private readonly TeamResolverService $teamResolverService,
		private readonly PermissionService $permissionService,
		private readonly IUserManager $userManager,
	) {
	}

	public function getEmployeeWidgetData(string $userId): array {
		$status = $this->timeTrackingService->getStatus($userId);

		// Weekly overtime (also provides cumulative balance and contract target)
		try {
			$weekly = $this->overtimeService->getWeeklyOvertime($userId);
		} catch (\Throwable $e) {
			$weekly = [];
		}

		// Break compliance status for the current session
		try {
			$breakStatus = $this->timeTrackingService->getBreakStatus($userId);
		} catch (\Throwable $e) {
			$breakStatus = [];
		}

		// Vacation entitlement and remaining balances for current year
		try {
			$vacationStats = $this->absenceService->getVacationStats($userId, (int)date('Y'));
		} catch (\Throwable $e) {
			$vacationStats = [];
		}

		// Format session/break start times for display (HH:MM)
		$sessionStartFormatted = '';
		$sessionStartRaw = (string)($status['current_entry']['startTime'] ?? '');
		if ($sessionStartRaw !== '') {
			try {
				$sessionStartFormatted = (new \DateTime($sessionStartRaw))->format('H:i');
			} catch (\Throwable $e) {
				// leave empty
			}
		}

		$breakStartFormatted = '';
		$breakStartRaw = (string)($status['current_entry']['breakStartTime'] ?? '');
		if ($breakStartRaw !== '') {
			try {
				$breakStartFormatted = (new \DateTime($breakStartRaw))->format('H:i');
			} catch (\Throwable $e) {
				// leave empty
			}
		}

		return [
			'userId'                 => $userId,
			'status'                 => (string)($status['status'] ?? 'clocked_out'),
			'workingTodayHours'      => (float)($status['working_today_hours'] ?? 0.0),
			'currentSessionDuration' => (int)($status['current_session_duration'] ?? 0),
			'sessionStartFormatted'  => $sessionStartFormatted,
			'breakStartFormatted'    => $breakStartFormatted,
			'weekHoursWorked'        => (float)($weekly['total_hours_worked'] ?? 0.0),
			'weekHoursRequired'      => (float)($weekly['required_hours'] ?? 0.0),
			'weeklyContractHours'    => (float)($weekly['weekly_hours'] ?? 40.0),
			'cumulativeBalance'      => (float)($weekly['cumulative_balance'] ?? 0.0),
			'breakRequired'          => (bool)($breakStatus['break_required'] ?? false),
			'remainingBreakMinutes'  => (int)round((float)($breakStatus['remaining_break_minutes'] ?? 0)),
			'breakWarningLevel'      => (string)($breakStatus['warning_level'] ?? 'none'),
			'vacationYear'           => (int)($vacationStats['year'] ?? (int)date('Y')),
			'vacationRemaining'      => (float)($vacationStats['remaining'] ?? 0.0),
			'vacationEntitlement'    => (float)($vacationStats['entitlement'] ?? 0.0),
			'vacationUsed'           => (float)($vacationStats['used'] ?? 0.0),
			'vacationCarryover'      => (float)($vacationStats['carryover_days'] ?? 0.0),
			'vacationCarryoverUsable'=> (float)($vacationStats['carryover_usable'] ?? 0.0),
		];
	}

	public function getManagerWidgetData(string $userId, int $limit = 7): array {
		if (!$this->permissionService->canAccessManagerDashboard($userId)) {
			return [
				'authorized' => false,
				'members' => [],
				'summary' => $this->emptySummary(),
			];
		}

		$memberIds = $this->teamResolverService->getTeamMemberIds($userId);
		$members = [];
		$summary = $this->emptySummary();
		$absenceSummary = [
			'vacation' => 0,
			'sick' => 0,
			'other_absent' => 0,
			'total_absent' => 0,
		];

		$effectiveLimit = max(1, min(self::MAX_MANAGER_MEMBERS, $limit));
		foreach (array_slice($memberIds, 0, $effectiveLimit) as $memberId) {
			$member = $this->userManager->get($memberId);
			if ($member === null) {
				continue;
			}
			$status = $this->timeTrackingService->getStatus($memberId);
			$statusKey = (string)($status['status'] ?? 'clocked_out');
			$summary['total']++;
			$this->incrementStatus($summary, $statusKey);

			$members[] = [
				'userId' => $memberId,
				'displayName' => $member->getDisplayName(),
				'status' => $statusKey,
				'workingTodayHours' => (float)($status['working_today_hours'] ?? 0.0),
			];
		}

		$absenceSummary = $this->buildTeamAbsenceSummary($memberIds);

		return [
			'authorized' => true,
			'members' => $members,
			'summary' => $summary,
			'absenceSummary' => $absenceSummary,
		];
	}

	public function getAdminWidgetData(string $userId, int $limit = 10): array {
		if (!$this->permissionService->isAdmin($userId)) {
			return [
				'authorized' => false,
				'users' => [],
				'summary' => $this->emptySummary(),
				'absenceSummary' => [
					'vacation' => 0,
					'sick' => 0,
					'other_absent' => 0,
					'total_absent' => 0,
				],
			];
		}

		$summary = $this->emptySummary();
		$users = [];
		$effectiveLimit = max(1, min(self::MAX_ADMIN_WIDGET_USERS, $limit));
		$allUsers = $this->userManager->search('', self::MAX_ADMIN_USERS, 0);
		$allUserIds = [];
		$index = 0;
		foreach ($allUsers as $user) {
			$allUserIds[] = $user->getUID();
			$status = $this->timeTrackingService->getStatus($user->getUID());
			$statusKey = (string)($status['status'] ?? 'clocked_out');
			$summary['total']++;
			$this->incrementStatus($summary, $statusKey);

			if ($index < $effectiveLimit) {
				$users[] = [
					'userId' => $user->getUID(),
					'displayName' => $user->getDisplayName(),
					'status' => $statusKey,
					'workingTodayHours' => (float)($status['working_today_hours'] ?? 0.0),
				];
				$index++;
			}
		}

		$absenceSummary = $this->buildTeamAbsenceSummary($allUserIds);

		return [
			'authorized' => true,
			'users' => $users,
			'summary' => $summary,
			'absenceSummary' => $absenceSummary,
		];
	}

	private function emptySummary(): array {
		return [
			'total' => 0,
			'active' => 0,
			'break' => 0,
			'paused' => 0,
			'clocked_out' => 0,
			'other' => 0,
		];
	}

	private function incrementStatus(array &$summary, string $status): void {
		if (isset($summary[$status])) {
			$summary[$status]++;
			return;
		}
		$summary['other']++;
	}

	private function buildTeamAbsenceSummary(array $memberIds): array {
		if ($memberIds === []) {
			return [
				'vacation' => 0,
				'sick' => 0,
				'other_absent' => 0,
				'total_absent' => 0,
			];
		}

		$today = new \DateTimeImmutable('today');

		try {
			$activeAbsences = $this->absenceMapper->findByUsersAndDateRange(
				$memberIds,
				$today,
				$today,
				Absence::STATUS_APPROVED
			);
		} catch (\Throwable $e) {
			return [
				'vacation' => 0,
				'sick' => 0,
				'other_absent' => 0,
				'total_absent' => 0,
			];
		}

		$vacationUsers = [];
		$sickUsers = [];
		$otherUsers = [];

		foreach ($activeAbsences as $absence) {
			$uid = (string)$absence->getUserId();
			$type = (string)$absence->getType();

			if ($type === Absence::TYPE_VACATION) {
				$vacationUsers[$uid] = true;
				continue;
			}
			if ($type === Absence::TYPE_SICK_LEAVE) {
				$sickUsers[$uid] = true;
				continue;
			}
			$otherUsers[$uid] = true;
		}

		$totalAbsentUsers = $vacationUsers + $sickUsers + $otherUsers;

		return [
			'vacation' => count($vacationUsers),
			'sick' => count($sickUsers),
			'other_absent' => count($otherUsers),
			'total_absent' => count($totalAbsentUsers),
		];
	}
}
