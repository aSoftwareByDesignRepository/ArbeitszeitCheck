<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Service;

use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Db\Absence;
use OCA\ArbeitszeitCheck\Db\AbsenceMapper;
use OCP\IUserManager;

class DashboardWidgetDataService {
	private const MAX_MANAGER_MEMBERS = 50;
	/** Max accounts scanned for admin widget status summary (DoS guard). */
	private const MAX_ADMIN_USERS_SCAN = Constants::MAX_LIST_LIMIT;
	private const MAX_ADMIN_WIDGET_USERS = 50;

	public function __construct(
		private readonly TimeTrackingService $timeTrackingService,
		private readonly OvertimeService $overtimeService,
		private readonly OvertimeDisplayService $overtimeDisplayService,
		private readonly OvertimeBankService $overtimeBankService,
		private readonly AbsenceService $absenceService,
		private readonly AbsenceMapper $absenceMapper,
		private readonly TeamResolverService $teamResolverService,
		private readonly PermissionService $permissionService,
		private readonly IUserManager $userManager,
		private readonly TimeZoneService $timeZoneService,
		private readonly TimeCaptureMethodService $timeCaptureMethodService,
	) {
	}

	/**
	 * Lean status payload for the dashboard desklet.
	 *
	 * The desklet only renders the current punch-clock state, today's hours, the
	 * running session and the time-capture flags. It must NOT trigger the heavy
	 * overtime/vacation/traffic-light computations that {@see getEmployeeWidgetData()}
	 * performs for the native widget and the mobile bootstrap (those generated
	 * ~100 queries per poll, every 30s, per user). Keep this method query-cheap.
	 *
	 * @return array<string, mixed>
	 */
	public function getEmployeeStatusSummary(string $userId): array {
		$status = $this->timeTrackingService->getStatus($userId);

		$sessionStartFormatted = $this->formatInstantForWidget(
			(string)($status['current_entry']['startTime'] ?? ''),
			$userId
		);

		return [
			'userId'                 => $userId,
			'status'                 => (string)($status['status'] ?? 'clocked_out'),
			'workingTodayHours'      => (float)($status['working_today_hours'] ?? 0.0),
			'currentSessionDuration' => (int)($status['current_session_duration'] ?? 0),
			// Drift-safe timer anchor (same fields as GET /api/clock/status).
			'serverNow'              => (string)($status['server_now'] ?? ''),
			'serverTimezone'         => (string)($status['server_timezone'] ?? ''),
			'sessionStartFormatted'  => $sessionStartFormatted,
			'timeCapture'            => $this->timeCaptureMethodService->getSettings($userId),
		];
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

		$vacationYear = $this->currentYearInStorage();

		// Vacation entitlement and remaining balances for current year (storage TZ)
		try {
			$vacationStats = $this->absenceService->getVacationStats($userId, $vacationYear);
		} catch (\Throwable $e) {
			$vacationStats = [];
		}

		// Format session/break start times for display in the user's Nextcloud TZ.
		$sessionStartFormatted = $this->formatInstantForWidget(
			(string)($status['current_entry']['startTime'] ?? ''),
			$userId
		);
		$breakStartFormatted = $this->formatInstantForWidget(
			(string)($status['current_entry']['breakStartTime'] ?? ''),
			$userId
		);

		$currentEntryId = null;
		if (isset($status['current_entry']['id'])) {
			$currentEntryId = (int)$status['current_entry']['id'];
			if ($currentEntryId <= 0) {
				$currentEntryId = null;
			}
		}

		return [
			'userId'                 => $userId,
			'status'                 => (string)($status['status'] ?? 'clocked_out'),
			'currentEntryId'         => $currentEntryId,
			'workingTodayHours'      => (float)($status['working_today_hours'] ?? 0.0),
			'currentSessionDuration' => (int)($status['current_session_duration'] ?? 0),
			// Drift-safe timer anchor (same fields as GET /api/clock/status).
			'serverNow'              => (string)($status['server_now'] ?? ''),
			'serverTimezone'         => (string)($status['server_timezone'] ?? ''),
			'sessionStartFormatted'  => $sessionStartFormatted,
			'breakStartTime'         => (string)($status['current_entry']['breakStartTime'] ?? ''),
			'breakStartFormatted'    => $breakStartFormatted,
			'weekHoursWorked'        => (float)($weekly['total_hours_worked'] ?? 0.0),
			'weekHoursRequired'      => (float)($weekly['required_hours'] ?? 0.0),
			'weeklyContractHours'    => (float)($weekly['weekly_hours'] ?? 40.0),
			// Contract daily hours (same as employee dashboard “Daily target”).
			'impliedDailyHours'      => (float)($weekly['implied_daily_hours'] ?? 0.0),
			'cumulativeBalance'      => (float)($weekly['cumulative_balance'] ?? 0.0),
			'displayBalance'         => $this->overtimeDisplayService->getYearToDateBalanceForTrafficLight($userId),
			'overtimeBankEnabled'    => $this->overtimeBankService->isEnabled(),
			'trafficLightState'      => $this->overtimeDisplayService->buildTrafficLightViewModel($userId)['state'] ?? 'green',
			'breakRequired'          => (bool)($breakStatus['break_required'] ?? false),
			'remainingBreakMinutes'  => (int)round((float)($breakStatus['remaining_break_minutes'] ?? 0)),
			'breakWarningLevel'      => (string)($breakStatus['warning_level'] ?? 'none'),
			// Personal setting: server may insert ArbZG §4 breaks when none were recorded.
			'autoBreakCalculation'   => $this->timeTrackingService->isAutoBreakCalculationEnabled($userId),
			'vacationYear'           => (int)($vacationStats['year'] ?? $vacationYear),
			'vacationRemaining'      => (float)($vacationStats['remaining'] ?? 0.0),
			'vacationEntitlement'    => (float)($vacationStats['entitlement'] ?? 0.0),
			'vacationUsed'           => (float)($vacationStats['used'] ?? 0.0),
			'vacationCarryover'      => (float)($vacationStats['carryover_days'] ?? 0.0),
			'vacationCarryoverUsable'=> (float)($vacationStats['carryover_usable'] ?? 0.0),
			'timeCapture'            => $this->timeCaptureMethodService->getSettings($userId),
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
				'summaryTruncated' => false,
				'summaryScopeLimit' => self::MAX_ADMIN_USERS_SCAN,
				'directoryTotal' => 0,
			];
		}

		$summary = $this->emptySummary();
		$users = [];
		$effectiveLimit = max(1, min(self::MAX_ADMIN_WIDGET_USERS, $limit));
		$allUsers = $this->userManager->search('', self::MAX_ADMIN_USERS_SCAN, 0);
		$allUserIds = [];
		$index = 0;
		foreach ($allUsers as $user) {
			if (!$user->isEnabled()) {
				continue;
			}
			$uid = $user->getUID();
			$allUserIds[] = $uid;
			$status = $this->timeTrackingService->getStatus($uid);
			$statusKey = (string)($status['status'] ?? 'clocked_out');
			$summary['total']++;
			$this->incrementStatus($summary, $statusKey);

			if ($index < $effectiveLimit) {
				$users[] = [
					'userId' => $uid,
					'displayName' => $user->getDisplayName(),
					'status' => $statusKey,
					'workingTodayHours' => (float)($status['working_today_hours'] ?? 0.0),
				];
				$index++;
			}
		}

		$directoryTotal = $this->userManager->countUsersTotal(0, false);
		if ($directoryTotal === false) {
			$directoryTotal = $summary['total'];
		}
		$hitScanCap = count($allUsers) >= self::MAX_ADMIN_USERS_SCAN;
		// Only flag truncation when the scan window was exhausted (not when disabled accounts inflate directoryTotal).
		$summaryTruncated = $hitScanCap;

		$absenceSummary = $this->buildTeamAbsenceSummary($allUserIds);

		return [
			'authorized' => true,
			'users' => $users,
			'summary' => $summary,
			'absenceSummary' => $absenceSummary,
			'summaryTruncated' => $summaryTruncated,
			'summaryScopeLimit' => self::MAX_ADMIN_USERS_SCAN,
			'directoryTotal' => (int)$directoryTotal,
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

	private function formatInstantForWidget(string $raw, string $userId): string {
		if ($raw === '') {
			return '';
		}
		try {
			$instant = $this->parseWidgetInstant($raw);
			return $this->timeZoneService->formatForDisplay($instant, 'H:i', $userId);
		} catch (\Throwable $e) {
			return '';
		}
	}

	/**
	 * Parse a clock instant from widget status data (ISO-8601 from API or naive SQL).
	 */
	private function parseWidgetInstant(string $raw): \DateTimeInterface {
		$trimmed = trim($raw);
		if ($trimmed === '') {
			throw new \InvalidArgumentException('Empty instant');
		}
		if (preg_match('/[TZ]|(?:[+-]\d{2}:?\d{2})$/', $trimmed) === 1) {
			return $this->timeZoneService->fromIso($trimmed);
		}
		return $this->timeZoneService->hydrateNaiveAny(new \DateTimeImmutable($trimmed));
	}

	private function currentYearInStorage(): int {
		return (int)$this->timeZoneService->nowImmutableInStorage()->format('Y');
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

		[$todayStart] = $this->timeZoneService->todayWindowInStorage();

		try {
			$activeAbsences = $this->absenceMapper->findByUsersAndDateRange(
				$memberIds,
				$todayStart,
				$todayStart,
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
