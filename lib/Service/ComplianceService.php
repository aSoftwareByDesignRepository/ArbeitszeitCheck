<?php

declare(strict_types=1);

/**
 * Compliance service for German labor law (ArbZG) and GDPR requirements
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Service;

use OCA\ArbeitszeitCheck\Db\TimeEntry;
use OCA\ArbeitszeitCheck\Db\TimeEntryMapper;
use OCA\ArbeitszeitCheck\Db\ComplianceViolationMapper;
use OCA\ArbeitszeitCheck\Db\WorkingTimeModelMapper;
use OCA\ArbeitszeitCheck\Db\UserWorkingTimeModelMapper;
use OCA\ArbeitszeitCheck\Db\ComplianceViolation;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IUserManager;

/**
 * Compliance service implementing German labor law requirements
 */
class ComplianceService
{
    private TimeEntryMapper $timeEntryMapper;
    private ComplianceViolationMapper $violationMapper;
    private WorkingTimeModelMapper $workingTimeModelMapper;
    private UserWorkingTimeModelMapper $userWorkingTimeModelMapper;
    private IUserManager $userManager;
    private IL10N $l10n;
    private ?NotificationService $notificationService;
    private HolidayService $holidayCalendarService;
    private IConfig $config;
    private PermissionService $permissionService;
    private TimeZoneService $timeZoneService;
    private DailyWorkingHoursCalculator $dailyWorkingHoursCalculator;

    public function __construct(
        TimeEntryMapper $timeEntryMapper,
        ComplianceViolationMapper $violationMapper,
        WorkingTimeModelMapper $workingTimeModelMapper,
        UserWorkingTimeModelMapper $userWorkingTimeModelMapper,
        IUserManager $userManager,
        IL10N $l10n,
        ?NotificationService $notificationService,
        HolidayService $holidayCalendarService,
        IConfig $config,
        PermissionService $permissionService,
        TimeZoneService $timeZoneService,
        DailyWorkingHoursCalculator $dailyWorkingHoursCalculator,
    ) {
        $this->timeEntryMapper = $timeEntryMapper;
        $this->violationMapper = $violationMapper;
        $this->workingTimeModelMapper = $workingTimeModelMapper;
        $this->userWorkingTimeModelMapper = $userWorkingTimeModelMapper;
        $this->userManager = $userManager;
        $this->l10n = $l10n;
        $this->notificationService = $notificationService;
        $this->holidayCalendarService = $holidayCalendarService;
        $this->config = $config;
        $this->permissionService = $permissionService;
        $this->timeZoneService = $timeZoneService;
        $this->dailyWorkingHoursCalculator = $dailyWorkingHoursCalculator;
    }

    /**
     * Render a stored DateTime as HH:MM in the affected user's display TZ.
     * Falls back to the storage TZ when no user is known so the value still
     * reflects the persisted civil time, never the container's UTC offset.
     */
    private function displayClock(\DateTimeInterface $dt, ?string $userId = null): string
    {
        return $this->timeZoneService->formatForDisplay($dt, 'H:i', $userId);
    }

    /**
     * Render a stored DateTime as `d.m.Y` in the affected user's display TZ.
     */
    private function displayDate(\DateTimeInterface $dt, ?string $userId = null): string
    {
        return $this->timeZoneService->formatForDisplay($dt, 'd.m.Y', $userId);
    }

    private function getMaxDailyHours(): float
    {
        return max(1.0, min(24.0, (float)$this->config->getAppValue('arbeitszeitcheck', 'max_daily_hours', '10')));
    }

    private function getMinRestPeriod(): float
    {
        return max(1.0, min(24.0, (float)$this->config->getAppValue('arbeitszeitcheck', 'min_rest_period', '11')));
    }

    /**
     * Check compliance before clocking in
     *
     * @param string $userId
     * @return array Array of compliance issues (empty if compliant)
     */
    public function checkComplianceBeforeClockIn(string $userId): array
    {
        $issues = [];

        // Check rest period (11 hours between shifts) - CRITICAL: Always enforce (ArbZG §5)
        $restEvaluation = $this->evaluateRestPeriodForClockIn($userId);
        if (!$restEvaluation['valid']) {
            $issues[] = [
                'type' => ComplianceViolation::TYPE_INSUFFICIENT_REST_PERIOD,
                'severity' => ComplianceViolation::SEVERITY_ERROR,
                'message' => $restEvaluation['message'],
            ];
        }

        // Check daily working hours limit
        if (!$this->checkDailyWorkingHoursLimit($userId)) {
            $maxDaily = (int)$this->getMaxDailyHours();
            $issues[] = [
                'type' => ComplianceViolation::TYPE_DAILY_HOURS_LIMIT_EXCEEDED,
                'severity' => ComplianceViolation::SEVERITY_ERROR,
                'message' => $this->l10n->t('Daily working hours limit reached (%d hours maximum)', [$maxDaily])
            ];
        }

        // Check weekly working hours average
        if (!$this->checkWeeklyWorkingHoursLimit($userId)) {
            $issues[] = [
                'type' => ComplianceViolation::TYPE_WEEKLY_HOURS_LIMIT_EXCEEDED,
                'severity' => ComplianceViolation::SEVERITY_WARNING,
                'message' => $this->l10n->t('Weekly working hours average limit (48 hours) exceeded')
            ];
        }

        return $issues;
    }

    /**
     * Check compliance after clocking out.
     *
     * Each individual rule is wrapped in its own try/catch so that a defect in one
     * check (e.g. a malformed translation or an unexpected DB state) cannot stop the
     * remaining checks from running. The caller is also expected to invoke this
     * method OUTSIDE of any clock-out transaction so that — in the worst case — a
     * compliance failure can never roll back the user's clock-out itself.
     *
     * @param TimeEntry $timeEntry
     * @return void
     */
    public function checkComplianceAfterClockOut(TimeEntry $timeEntry): void
    {
        // Ordered list of (label, callable). The label is used for log correlation
        // and never user-visible.
        $checks = [
            'mandatory_breaks'             => fn() => $this->checkMandatoryBreaks($timeEntry),
            'excessive_working_hours'      => fn() => $this->checkExcessiveWorkingHours($timeEntry),
            'night_work'                   => fn() => $this->checkNightWork($timeEntry),
            'sunday_and_holiday_work'      => fn() => $this->checkSundayAndHolidayWork($timeEntry),
            'six_month_and_weekly_average' => fn() => $this->checkSixMonthAverageAndWeeklyHours($timeEntry),
        ];

        foreach ($checks as $name => $check) {
            try {
                $check();
            } catch (\Throwable $e) {
                \OCP\Log\logger('arbeitszeitcheck')->error(
                    'Compliance check "' . $name . '" failed for entry ' . (int)$timeEntry->getId() . ': ' . $e->getMessage(),
                    [
                        'exception' => $e,
                        'user_id'   => $timeEntry->getUserId(),
                        'entry_id'  => $timeEntry->getId(),
                        'check'     => $name,
                    ]
                );
                // Intentionally swallow: continue with the remaining checks so that
                // one buggy check never silences the others.
            }
        }
    }

    /**
     * Check compliance for a completed time entry (real-time check)
     * 
     * This method is called immediately when a time entry is completed (status = COMPLETED).
     * It performs all compliance checks and creates violations if necessary.
     * 
     * Based on industry best practices (Personio, Flintec, etc.), real-time compliance
     * checking ensures immediate detection of violations and proactive compliance management.
     * 
     * @param TimeEntry $timeEntry The completed time entry to check
     * @param bool $strictMode If true, throws exception on critical violations (prevents saving)
     * @return array Array of detected violations (empty if compliant)
     * @throws \Exception If strict mode is enabled and critical violations are found
     */
    public function checkComplianceForCompletedEntry(TimeEntry $timeEntry, bool $strictMode = false, bool $persistViolations = true): array
    {
        // Only check completed entries with end time
        if ($timeEntry->getStatus() !== TimeEntry::STATUS_COMPLETED || !$timeEntry->getEndTime()) {
            return [];
        }

        $violations = [];
        $criticalViolations = [];

        // Check mandatory breaks (ArbZG §4)
        $breakViolations = $this->checkMandatoryBreaksWithResult($timeEntry, $persistViolations);
        if (!empty($breakViolations)) {
            $violations = array_merge($violations, $breakViolations);
            $criticalViolations = array_merge($criticalViolations, array_filter($breakViolations, fn($v) => $v['severity'] === ComplianceViolation::SEVERITY_ERROR));
        }

        // Check excessive working hours (ArbZG §3)
        $hoursViolations = $this->checkExcessiveWorkingHoursWithResult($timeEntry, $persistViolations);
        if (!empty($hoursViolations)) {
            $violations = array_merge($violations, $hoursViolations);
            $criticalViolations = array_merge($criticalViolations, array_filter($hoursViolations, fn($v) => $v['severity'] === ComplianceViolation::SEVERITY_ERROR));
        }

        // Check night work (ArbZG §6) - informational
        $this->checkNightWork($timeEntry);

        // Check Sunday and holiday work (ArbZG §9) - warnings
        $this->checkSundayAndHolidayWork($timeEntry);

        // Check 6-month average and weekly hours (ArbZG §3) - warnings to manager only
        // These are warnings, not blocking violations
        $this->checkSixMonthAverageAndWeeklyHours($timeEntry);

        // In strict mode, throw exception if critical violations found
        if ($strictMode && !empty($criticalViolations)) {
            $firstCritical = reset($criticalViolations);
            throw new \Exception($firstCritical['message']);
        }

        return $violations;
    }

    /**
     * Pre-save compliance gate for employee/API completed entries (no violation rows written).
     *
     * ArbZG §4 mandatory breaks are always blocking for portal saves.
     * Additional critical checks run when strict mode is enabled.
     *
     * @return list<string> Human-readable blocking messages (empty if savable)
     */
    public function blockingIssuesForCompletedEntry(TimeEntry $timeEntry, bool $strictMode = false): array
    {
        if ($timeEntry->getStatus() !== TimeEntry::STATUS_COMPLETED || $timeEntry->getEndTime() === null) {
            return [];
        }

        $issues = [];
        $duration = $timeEntry->getDurationHours();
        $breakDuration = $timeEntry->getBreakDurationHours();

        if ($duration >= 9 && $breakDuration < 0.75) {
            $issues[] = $this->l10n->t('Mandatory 45-minute break missing after 9 hours of work (ArbZG §4).');
        } elseif ($duration >= 6 && $breakDuration < 0.5) {
            $issues[] = $this->l10n->t('Mandatory 30-minute break missing after 6 hours of work (ArbZG §4).');
        }

        return $issues;
    }

    /**
     * Check 6-month average and weekly hours (ArbZG §3)
     * Sends warnings to manager if limits are exceeded (non-blocking)
     * 
     * @param TimeEntry $timeEntry
     * @return void
     */
    private function checkSixMonthAverageAndWeeklyHours(TimeEntry $timeEntry): void
    {
        if (!$timeEntry->getEndTime()) {
            return; // Only check completed entries
        }

        $userId = $timeEntry->getUserId();
        $entryDate = clone $timeEntry->getEndTime();
        $entryDate->setTime(0, 0, 0);
        $todayKey = $entryDate->format('Y-m-d');

        // Check if we already sent a warning today (to avoid spam)
        // Use a simple cache key based on date
        static $warningsSentToday = [];
        $cacheKey = $userId . '_' . $todayKey;

        // Check 6-month average (for 10-hour days)
        $workingHours = $timeEntry->getWorkingDurationHours();
        if ($workingHours !== null && $workingHours >= 8.0) {
            // Only check if working 8+ hours (approaching 10-hour limit)
            $sixMonthCheck = $this->checkSixMonthAverage($userId, $entryDate);
            if (!$sixMonthCheck['valid'] && !isset($warningsSentToday[$cacheKey . '_6month'])) {
                // Send warning to manager (non-blocking)
                if ($this->notificationService) {
                    $this->notificationService->notifyManagerWorkingTimeWarning($userId, 'six_month_average', [
                        'message' => $sixMonthCheck['message'],
                        'current_value' => $sixMonthCheck['average'],
                        'limit' => $sixMonthCheck['limit'],
                        'date' => $todayKey
                    ]);
                }
                $warningsSentToday[$cacheKey . '_6month'] = true;
            }
        }

        // Check weekly hours average
        $weeklyCheck = $this->checkWeeklyHoursAverage($userId, $entryDate);
        if (!$weeklyCheck['valid'] && !isset($warningsSentToday[$cacheKey . '_weekly'])) {
            // Send warning to manager (non-blocking)
            if ($this->notificationService) {
                $this->notificationService->notifyManagerWorkingTimeWarning($userId, 'weekly_hours', [
                    'message' => $weeklyCheck['message'],
                    'current_value' => $weeklyCheck['average'],
                    'limit' => $weeklyCheck['limit'],
                    'date' => $todayKey
                ]);
            }
            $warningsSentToday[$cacheKey . '_weekly'] = true;
        }
    }

    /**
     * Check mandatory breaks and return violations as array
     * 
     * @param TimeEntry $timeEntry
     * @return array Array of violation information
     */
    private function checkMandatoryBreaksWithResult(TimeEntry $timeEntry, bool $persistViolations = true): array
    {
        $violations = [];
        $duration = $timeEntry->getDurationHours();
        $breakDuration = $timeEntry->getBreakDurationHours();

        // ArbZG §4: Check 9h (45 min break) first — otherwise duration >= 6 would catch it
        if ($duration >= 9 && $breakDuration < 0.75) { // 45 minutes break required
            $message = $this->l10n->t('Mandatory 45-minute break missing after 9 hours of work');
            if (!$persistViolations) {
                return [[
                    'type' => ComplianceViolation::TYPE_MISSING_BREAK,
                    'severity' => ComplianceViolation::SEVERITY_ERROR,
                    'message' => $message,
                ]];
            }
            $violation = $this->violationMapper->createViolation(
                $timeEntry->getUserId(),
                ComplianceViolation::TYPE_MISSING_BREAK,
                $this->l10n->t('Mandatory 45-minute break missing after 9 hours of work'),
                $timeEntry->getEndTime() ?: new \DateTime(),
                $timeEntry->getId(),
                ComplianceViolation::SEVERITY_ERROR
            );
            
            $violations[] = [
                'id' => $violation->getId(),
                'type' => ComplianceViolation::TYPE_MISSING_BREAK,
                'severity' => ComplianceViolation::SEVERITY_ERROR,
                'message' => $this->l10n->t('Mandatory 45-minute break missing after 9 hours of work')
            ];
            
            // Send notification
            if ($this->notificationService) {
                $this->notificationService->notifyComplianceViolation($timeEntry->getUserId(), [
                    'id' => $violation->getId(),
                    'type' => ComplianceViolation::TYPE_MISSING_BREAK,
                    'message' => $this->l10n->t('Mandatory 45-minute break missing after 9 hours of work'),
                    'date' => ($timeEntry->getEndTime() ?: new \DateTime())->format('Y-m-d'),
                    'severity' => ComplianceViolation::SEVERITY_ERROR
                ]);
            }
        } elseif ($duration >= 6 && $breakDuration < 0.5) { // 30 minutes break required
            $message = $this->l10n->t('Mandatory 30-minute break missing after 6 hours of work');
            if (!$persistViolations) {
                return [[
                    'type' => ComplianceViolation::TYPE_MISSING_BREAK,
                    'severity' => ComplianceViolation::SEVERITY_ERROR,
                    'message' => $message,
                ]];
            }
            $violation = $this->violationMapper->createViolation(
                $timeEntry->getUserId(),
                ComplianceViolation::TYPE_MISSING_BREAK,
                $message,
                $timeEntry->getEndTime() ?: new \DateTime(),
                $timeEntry->getId(),
                ComplianceViolation::SEVERITY_ERROR
            );

            $violations[] = [
                'id' => $violation->getId(),
                'type' => ComplianceViolation::TYPE_MISSING_BREAK,
                'severity' => ComplianceViolation::SEVERITY_ERROR,
                'message' => $message,
            ];

            // Send notification
            if ($this->notificationService) {
                $this->notificationService->notifyComplianceViolation($timeEntry->getUserId(), [
                    'id' => $violation->getId(),
                    'type' => ComplianceViolation::TYPE_MISSING_BREAK,
                    'message' => $this->l10n->t('Mandatory 30-minute break missing after 6 hours of work'),
                    'date' => ($timeEntry->getEndTime() ?: new \DateTime())->format('Y-m-d'),
                    'severity' => ComplianceViolation::SEVERITY_ERROR
                ]);
            }
        }

        return $violations;
    }

    /**
     * Check excessive working hours and return violations as array
     * 
     * @param TimeEntry $timeEntry
     * @return array Array of violation information
     */
    private function checkExcessiveWorkingHoursWithResult(TimeEntry $timeEntry, bool $persistViolations = true): array
    {
        $violations = [];
        foreach ($this->createExcessiveHoursViolationsForEntry($timeEntry, $persistViolations) as $recorded) {
            $violations[] = $recorded['summary'];
        }

        return $violations;
    }

    /**
     * ArbZG §3: flag each calendar day (storage TZ) whose total exceeds the daily maximum.
     *
     * @return list<array{summary: array<string, mixed>, violation: ComplianceViolation}>
     */
    private function createExcessiveHoursViolationsForEntry(TimeEntry $timeEntry, bool $persistViolations = true): array
    {
        if ($timeEntry->getStartTime() === null || $timeEntry->getEndTime() === null) {
            return [];
        }

        $userId = $timeEntry->getUserId();
        $maxDaily = $this->getMaxDailyHours();
        $exceedingDays = $this->dailyWorkingHoursCalculator->findAllCalendarDaysExceedingMaximum(
            $userId,
            $timeEntry,
            $maxDaily,
        );

        if ($exceedingDays === []) {
            return [];
        }

        $recorded = [];
        foreach ($exceedingDays as $day) {
            [$dayStart] = $this->timeZoneService->dayWindowInStorage(
                new \DateTime($day['date'] . ' 12:00:00', $this->timeZoneService->storageTimeZone())
            );

            if ($persistViolations && $this->excessiveHoursViolationExistsForCalendarDay($userId, $dayStart)) {
                continue;
            }

            $message = $this->l10n->t(
                'Working hours on %1$s exceeded %2$d hours (%.1f h on that calendar day, ArbZG §3)',
                [
                    $this->displayDate($dayStart, $userId),
                    (int)$maxDaily,
                    $day['hours'],
                ]
            );

            if (!$persistViolations) {
                $recorded[] = [
                    'summary' => [
                        'type' => ComplianceViolation::TYPE_EXCESSIVE_WORKING_HOURS,
                        'severity' => ComplianceViolation::SEVERITY_ERROR,
                        'message' => $message,
                    ],
                    'violation' => null,
                ];
                continue;
            }

            $violation = $this->violationMapper->createViolation(
                $userId,
                ComplianceViolation::TYPE_EXCESSIVE_WORKING_HOURS,
                $message,
                $dayStart,
                $timeEntry->getId(),
                ComplianceViolation::SEVERITY_ERROR
            );

            $summary = [
                'id' => $violation->getId(),
                'type' => ComplianceViolation::TYPE_EXCESSIVE_WORKING_HOURS,
                'severity' => ComplianceViolation::SEVERITY_ERROR,
                'message' => $message,
            ];

            if ($this->notificationService) {
                $this->notificationService->notifyComplianceViolation($userId, [
                    'id' => $violation->getId(),
                    'type' => ComplianceViolation::TYPE_EXCESSIVE_WORKING_HOURS,
                    'message' => $message,
                    'date' => $day['date'],
                    'severity' => ComplianceViolation::SEVERITY_ERROR,
                ]);
            }

            $recorded[] = ['summary' => $summary, 'violation' => $violation];
        }

        return $recorded;
    }

    /**
     * Avoid duplicate ERROR rows when batch checks touch several entries on the same day.
     */
    private function excessiveHoursViolationExistsForCalendarDay(string $userId, \DateTime $dayStart): bool
    {
        $dayEnd = (clone $dayStart)->modify('+1 day');
        foreach ($this->violationMapper->findByDateRange($dayStart, $dayEnd, $userId) as $existing) {
            if ($existing->getViolationType() === ComplianceViolation::TYPE_EXCESSIVE_WORKING_HOURS
                && !$existing->isResolved()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if minimum rest period is met (11 hours between shifts, ArbZG §5).
     *
     * Uses targeted DB queries instead of a full user-entry scan to keep clock-in fast.
     *
     * @param string $userId
     * @return bool
     */
    private function checkRestPeriod(string $userId): bool
    {
        return $this->evaluateRestPeriodForClockIn($userId)['valid'];
    }

    /**
     * Evaluate ArbZG §5 rest for clock-in at "now" in the organisation storage TZ.
     *
     * Critical invariants:
     *  - "Now" is always {@see TimeZoneService::nowInStorage()} (never PHP default TZ).
     *  - The rest anchor is the latest completed end_time strictly before now.
     *    Entries with end_time in the future (bad manual rows / planned days) must
     *    never block clock-in — they are not an ended shift yet.
     *  - User-facing times always include the calendar date so "04:00" cannot be
     *    mistaken for a time that already passed today when it is tomorrow.
     *
     * @return array{valid: bool, message: string|null, lastEndTime: ?\DateTime, earliestClockIn: ?\DateTime}
     */
    private function evaluateRestPeriodForClockIn(string $userId): array
    {
        $now = $this->timeZoneService->nowInStorage();
        $lastEndTime = $this->resolveRestPeriodAnchor($userId, $now);
        $minRest = $this->getMinRestPeriod();

        if ($lastEndTime === null) {
            return [
                'valid' => true,
                'message' => null,
                'lastEndTime' => null,
                'earliestClockIn' => null,
            ];
        }

        $hoursSinceLastEntry = ($now->getTimestamp() - $lastEndTime->getTimestamp()) / 3600.0;
        if ($hoursSinceLastEntry >= $minRest) {
            return [
                'valid' => true,
                'message' => null,
                'lastEndTime' => $lastEndTime,
                'earliestClockIn' => null,
            ];
        }

        $earliestClockIn = $this->addRestHours($lastEndTime, $minRest);
        $hoursRemaining = ($earliestClockIn->getTimestamp() - $now->getTimestamp()) / 3600.0;

        return [
            'valid' => false,
            'message' => $this->l10n->t(
                'Minimum %1$d-hour rest period required between shifts (ArbZG §5). Your last shift ended on %2$s at %3$s. You can clock in after %4$s (in %5$.1f hours).',
                [
                    (int)$minRest,
                    $this->displayDate($lastEndTime, $userId),
                    $this->displayClock($lastEndTime, $userId),
                    $this->timeZoneService->formatForDisplay($earliestClockIn, 'd.m.Y H:i', $userId),
                    max(0.0, $hoursRemaining),
                ]
            ),
            'lastEndTime' => $lastEndTime,
            'earliestClockIn' => $earliestClockIn,
        ];
    }

    /**
     * Latest shift-end instant that may anchor an ArbZG §5 rest check at $asOf.
     *
     * Prefers the newest completed entry with end_time < $asOf (so future-dated
     * completed rows cannot poison the check). Falls back to a recent paused
     * entry's updatedAt when it is also not after $asOf.
     */
    private function resolveRestPeriodAnchor(string $userId, \DateTime $asOf): ?\DateTime
    {
        $lastCompleted = $this->timeEntryMapper->findLastCompletedBeforeTime($userId, $asOf);
        $lastEndTime = $lastCompleted?->getEndTime();
        if ($lastEndTime !== null) {
            return $lastEndTime;
        }

        $minRest = $this->getMinRestPeriod();
        $lookbackHours = max(48, (int)ceil($minRest * 2));
        $lastPaused = $this->timeEntryMapper->findLastPausedWithinHours($userId, $lookbackHours);
        $pausedAt = $lastPaused?->getUpdatedAt();
        if ($pausedAt !== null && $pausedAt->getTimestamp() <= $asOf->getTimestamp()) {
            return $pausedAt;
        }

        return null;
    }

    /**
     * Add a fractional rest period to an instant using whole seconds (avoids
     * truncating e.g. 11.5 h down to 11 h via (int) cast on modify('+N hours')).
     */
    private function addRestHours(\DateTime $from, float $hours): \DateTime
    {
        $earliest = clone $from;
        $seconds = (int)round(max(0.0, $hours) * 3600.0);
        $earliest->modify('+' . $seconds . ' seconds');
        return $earliest;
    }

    /**
     * Check if minimum rest period is met for a specific start time (ArbZG §5)
     * 
     * This method is used for validating manual time entries before they are saved.
     * It checks if the provided start time violates the 11-hour rest period requirement
     * since the last completed entry's end time.
     *
     * @param string $userId
     * @param \DateTime $startTime The start time to check
     * @param int|null $excludeEntryId Optional: exclude this entry ID from the check (for updates)
     * @return array Array with 'valid' (bool) and 'message' (string) if invalid
     */
    public function checkRestPeriodForStartTime(string $userId, \DateTime $startTime, ?int $excludeEntryId = null): array
    {
        // Single indexed query: most-recent completed entry ending before $startTime.
        $lastCompletedEntry = $this->timeEntryMapper->findLastCompletedBeforeTime($userId, $startTime, $excludeEntryId);

        // If no previous completed entry found, rest period check is not applicable.
        if ($lastCompletedEntry === null || $lastCompletedEntry->getEndTime() === null) {
            return ['valid' => true, 'message' => null];
        }

        $lastEndTime = $lastCompletedEntry->getEndTime();
        
        // Check if it's the same day
        $lastEndDate = $lastEndTime->format('Y-m-d');
        $startDate = $startTime->format('Y-m-d');
        
        if ($lastEndDate === $startDate) {
            // Same day: No rest period check required (it's a work interruption, not a new shift)
            return ['valid' => true, 'message' => null];
        }
        
        // Check days difference: start date - last end date
        $lastEndDateObj = new \DateTime($lastEndDate);
        $lastEndDateObj->setTime(0, 0, 0);
        $startDateObj = new \DateTime($startDate);
        $startDateObj->setTime(0, 0, 0);
        
		// Calculate calendar day distance between the two dates (normalised to midnight).
		// $startDateObj->diff($lastEndDateObj) goes FROM startDate TO lastEndDate.
		// Because startDate > lastEndDate (we only consider entries ending before startTime)
		// the diff is always negative: -1 means "exactly the next calendar day", -2 means
		// "two calendar days apart", etc.
		$diff = $startDateObj->diff($lastEndDateObj);
		$daysDifference = (int)$diff->format('%r%a'); // negative when startDate > lastEndDate

		// Two or more full calendar days apart: 11 h rest is guaranteed regardless of clock time.
		// Example: last end = Jan 15, new start = Jan 17 → daysDifference = -2 → always valid.
		if ($daysDifference <= -2) {
			return ['valid' => true, 'message' => null];
		}

		// Exactly one calendar day apart (daysDifference == -1) or any unexpected positive value:
		// fall through to the exact timestamp check below.
		// Example: last end = Jan 15 23:30, new start = Jan 16 00:30 → only 1 h rest → must fail.

        $minRest = $this->getMinRestPeriod();
        $hoursSinceLastEntry = ($startTime->getTimestamp() - $lastEndTime->getTimestamp()) / 3600;

        if ($hoursSinceLastEntry >= $minRest) {
            return ['valid' => true, 'message' => null];
        }
        
        $earliestStartTime = $this->addRestHours($lastEndTime, $minRest);
        $hoursStillNeeded = ($earliestStartTime->getTimestamp() - $startTime->getTimestamp()) / 3600;
        
        // Format the user-facing message in the affected user's display TZ so
        // employees in non-storage zones (rare but legal) see their own clock.
        $lastEndDateFormatted = $this->displayDate($lastEndTime, $userId);
        $earliestStartDateFormatted = $this->timeZoneService->formatForDisplay($earliestStartTime, 'd.m.Y H:i', $userId);

        return [
            'valid' => false,
            'message' => $this->l10n->t(
                'Minimum %1$d-hour rest period required between shifts (ArbZG §5). Your last shift ended on %2$s at %3$s. This entry cannot start before %4$s (%5$.1f hours required).',
                [
                    (int)$minRest,
                    $lastEndDateFormatted,
                    $this->displayClock($lastEndTime, $userId),
                    $earliestStartDateFormatted,
                    abs($hoursStillNeeded),
                ]
            ),
            'earliestStartTime' => $earliestStartTime,
        ];
    }

    /**
     * Check daily working hours limit (max 10 hours)
     *
     * @param string $userId
     * @return bool
     */
    private function checkDailyWorkingHoursLimit(string $userId): bool
    {
        $todayHours = $this->dailyWorkingHoursCalculator->getWorkingHoursForToday($userId);
        return $todayHours < $this->getMaxDailyHours();
    }

    /**
     * Check weekly working hours average (max 48 hours over 6 months)
     *
     * @param string $userId
     * @return bool
     */
    private function checkWeeklyWorkingHoursLimit(string $userId): bool
    {
        $now = $this->timeZoneService->nowInStorage();
        $sixMonthsAgo = (clone $now)->modify('-6 months');

        $totalHours = $this->timeEntryMapper->getTotalHoursByUserAndDateRange(
            $userId,
            $sixMonthsAgo,
            $now
        );

        $weeksWorked = 26; // Approximate weeks in 6 months
        $averageWeeklyHours = $totalHours / $weeksWorked;

        return $averageWeeklyHours <= 48;
    }

    /**
     * Check 6-month average daily working hours (ArbZG §3)
     * 
     * 10-hour days are only allowed if the 6-month average does not exceed 8 hours per day.
     * 
     * @param string $userId
     * @param \DateTime $entryDate The date of the entry to check
     * @return array Array with 'valid' (bool), 'message' (string|null), 'average' (float), 'limit' (float)
     */
    private function checkSixMonthAverage(string $userId, \DateTime $entryDate): array
    {
        $sixMonthsAgo = clone $entryDate;
        $sixMonthsAgo->modify('-6 months');
        
        // Get total hours worked in the last 6 months
        $totalHours = $this->timeEntryMapper->getTotalHoursByUserAndDateRange(
            $userId,
            $sixMonthsAgo,
            $entryDate
        );
        
        // Calculate number of working days (excluding weekends and holidays)
        // For simplicity, we'll use approximate: 6 months = ~130 working days (5 days/week * 26 weeks)
        // More accurate would be to count actual working days, but this is acceptable for a warning check
        $approximateWorkingDays = 130;
        
        // Calculate average daily working hours
        $averageDailyHours = $approximateWorkingDays > 0 ? $totalHours / $approximateWorkingDays : 0;
        
        $limit = 8.0; // ArbZG §3: 6-month average must not exceed 8 hours/day for 10-hour days to be allowed
        
        if ($averageDailyHours > $limit) {
            return [
                'valid' => false,
                'message' => $this->l10n->t(
                    'Warning: 6-month average working hours (%.2f h/day) exceeds 8 hours/day. 10-hour days are only allowed if the average does not exceed 8 hours (ArbZG §3).',
                    [$averageDailyHours]
                ),
                'average' => $averageDailyHours,
                'limit' => $limit
            ];
        }
        
        return [
            'valid' => true,
            'message' => null,
            'average' => $averageDailyHours,
            'limit' => $limit
        ];
    }

    /**
     * Check weekly hours average over 6 months (ArbZG §3)
     * 
     * Average weekly working hours over 6 months must not exceed 48 hours.
     * 
     * @param string $userId
     * @param \DateTime $entryDate The date of the entry to check
     * @return array Array with 'valid' (bool), 'message' (string|null), 'average' (float), 'limit' (float)
     */
    private function checkWeeklyHoursAverage(string $userId, \DateTime $entryDate): array
    {
        $sixMonthsAgo = clone $entryDate;
        $sixMonthsAgo->modify('-6 months');
        
        // Get total hours worked in the last 6 months
        $totalHours = $this->timeEntryMapper->getTotalHoursByUserAndDateRange(
            $userId,
            $sixMonthsAgo,
            $entryDate
        );
        
        // Calculate number of weeks (approximately 26 weeks in 6 months)
        $weeks = 26;
        $averageWeeklyHours = $weeks > 0 ? $totalHours / $weeks : 0;
        
        $limit = 48.0; // ArbZG §3: Average weekly hours must not exceed 48 hours over 6 months
        
        if ($averageWeeklyHours > $limit) {
            return [
                'valid' => false,
                'message' => $this->l10n->t(
                    'Warning: 6-month average weekly working hours (%.2f h/week) exceeds 48 hours/week (ArbZG §3).',
                    [$averageWeeklyHours]
                ),
                'average' => $averageWeeklyHours,
                'limit' => $limit
            ];
        }
        
        return [
            'valid' => true,
            'message' => null,
            'average' => $averageWeeklyHours,
            'limit' => $limit
        ];
    }

    /**
     * Check mandatory breaks in time entry
     *
     * @param TimeEntry $timeEntry
     * @return void
     */
    private function checkMandatoryBreaks(TimeEntry $timeEntry): void
    {
        $duration = $timeEntry->getDurationHours();
        $breakDuration = $timeEntry->getBreakDurationHours();

        // ArbZG §4: Check 9h (45 min break) first — otherwise duration >= 6 would catch it
        if ($duration >= 9 && $breakDuration < 0.75) { // 45 minutes break required
            $violation = $this->violationMapper->createViolation(
                $timeEntry->getUserId(),
                ComplianceViolation::TYPE_MISSING_BREAK,
                $this->l10n->t('Mandatory 45-minute break missing after 9 hours of work'),
                $timeEntry->getEndTime() ?: new \DateTime(),
                $timeEntry->getId(),
                ComplianceViolation::SEVERITY_ERROR
            );
            
            // Send notification
            if ($this->notificationService) {
                $this->notificationService->notifyComplianceViolation($timeEntry->getUserId(), [
                    'id' => $violation->getId(),
                    'type' => ComplianceViolation::TYPE_MISSING_BREAK,
                    'message' => $this->l10n->t('Mandatory 45-minute break missing after 9 hours of work'),
                    'date' => ($timeEntry->getEndTime() ?: new \DateTime())->format('Y-m-d'),
                    'severity' => ComplianceViolation::SEVERITY_ERROR
                ]);
            }
        } elseif ($duration >= 6 && $breakDuration < 0.5) { // 30 minutes break required
            $violation = $this->violationMapper->createViolation(
                $timeEntry->getUserId(),
                ComplianceViolation::TYPE_MISSING_BREAK,
                $this->l10n->t('Mandatory 30-minute break missing after 6 hours of work'),
                $timeEntry->getEndTime() ?: new \DateTime(),
                $timeEntry->getId(),
                ComplianceViolation::SEVERITY_ERROR
            );

            // Send notification
            if ($this->notificationService) {
                $this->notificationService->notifyComplianceViolation($timeEntry->getUserId(), [
                    'id' => $violation->getId(),
                    'type' => ComplianceViolation::TYPE_MISSING_BREAK,
                    'message' => $this->l10n->t('Mandatory 30-minute break missing after 6 hours of work'),
                    'date' => ($timeEntry->getEndTime() ?: new \DateTime())->format('Y-m-d'),
                    'severity' => ComplianceViolation::SEVERITY_ERROR
                ]);
            }
        }
    }

    /**
     * Check for excessive working hours (over 10 hours in a day)
     *
     * @param TimeEntry $timeEntry
     * @return void
     */
    private function checkExcessiveWorkingHours(TimeEntry $timeEntry): void
    {
        $this->createExcessiveHoursViolationsForEntry($timeEntry);
    }

    /**
     * Check for night work (23:00 – 06:00).
     *
     * Authoritative source of truth is {@see calculateNightHours()}: if the actual
     * intersection with the night window is positive we record an INFO violation,
     * otherwise we skip it (boundary cases like 22:00–23:00 or 06:00–14:00 must not
     * produce a "0.00 hours" violation).
     *
     * @param TimeEntry $timeEntry
     * @return void
     */
    private function checkNightWork(TimeEntry $timeEntry): void
    {
        $startTime = $timeEntry->getStartTime();
        $endTime = $timeEntry->getEndTime();

        if (!$endTime || !$startTime) {
            return;
        }

        $nightHours = $this->calculateNightHours($startTime, $endTime);

        if ($nightHours <= 0.0) {
            return;
        }

        // CRITICAL: pass $nightHours as a parameter to t() so the L10NString carries
        // the value into its internal vsprintf(). Calling sprintf() on the OUTSIDE of
        // a parameterless t() corrupts the placeholder pipeline and triggers a
        // ValueError in OC\L10N\L10NString::__toString() (see issue triggered on
        // /api/clock/out for late-night shifts).
        $this->violationMapper->createViolation(
            $timeEntry->getUserId(),
            ComplianceViolation::TYPE_NIGHT_WORK,
            $this->l10n->t('Night work detected: %.2f hours between 11 PM and 6 AM', [$nightHours]),
            $timeEntry->getEndTime(),
            $timeEntry->getId(),
            ComplianceViolation::SEVERITY_INFO
        );
    }

    /**
     * Check for Sunday and holiday work
     *
     * @param TimeEntry $timeEntry
     * @return void
     */
    private function checkSundayAndHolidayWork(TimeEntry $timeEntry): void
    {
        $startTime = $timeEntry->getStartTime();
        $endTime = $timeEntry->getEndTime();

        if (!$endTime || !$startTime) {
            return;
        }

        $userId = $timeEntry->getUserId();
        $entryId = $timeEntry->getId();

        // Every calendar day touched by the shift must be evaluated. Shifts that
        // start on Saturday and end on Sunday must still flag Sunday work; the old
        // logic only inspected the start date and missed that case.
        $cursor = (clone $startTime)->setTime(0, 0, 0);
        $lastCalendarDay = (clone $endTime)->setTime(0, 0, 0);

        while ($cursor <= $lastCalendarDay) {
            // $cursor is always normalized to 00:00:00 of the calendar day under test.
            $occurredAt = $startTime > $cursor ? clone $startTime : clone $cursor;

            if ((int)$cursor->format('w') === 0) {
                $this->violationMapper->createViolation(
                    $userId,
                    ComplianceViolation::TYPE_SUNDAY_WORK,
                    $this->l10n->t('Work performed on Sunday'),
                    $occurredAt,
                    $entryId,
                    ComplianceViolation::SEVERITY_WARNING
                );
            }

            $isHoliday = false;
            try {
                $isHoliday = $this->holidayCalendarService->isHolidayForUser(
                    $userId,
                    clone $cursor
                );
            } catch (\Throwable) {
                // If holiday lookup fails, we fall back to "not a holiday" to avoid false positives.
            }

            if ($isHoliday) {
                $this->violationMapper->createViolation(
                    $userId,
                    ComplianceViolation::TYPE_HOLIDAY_WORK,
                    $this->l10n->t('Work performed on public holiday'),
                    $occurredAt,
                    $entryId,
                    ComplianceViolation::SEVERITY_WARNING
                );
            }

            $cursor->modify('+1 day');
        }
    }

    /**
     * Calculate the total hours worked inside the night window (23:00 – 06:00).
     *
     * Each "night window" is the half-open interval [day X 23:00, day X+1 06:00).
     * A work span can intersect with multiple night windows:
     *   - the previous night that bleeds into "today" (e.g. a 02:00–04:00 shift
     *     belongs entirely to the night that started at 23:00 the previous day),
     *   - the upcoming night (e.g. a 22:00–02:00 shift),
     *   - and — for unusually long shifts — additional ones in between.
     *
     * The previous implementation only considered the night window starting on the
     * shift's start date, which incorrectly returned 0 for early-morning shifts that
     * fell entirely inside the prior night.
     *
     * @param \DateTime $start Inclusive start of the work span.
     * @param \DateTime $end   Exclusive end of the work span.
     * @return float Hours of work that fell inside any night window (≥ 0).
     */
    private function calculateNightHours(\DateTime $start, \DateTime $end): float
    {
        if ($end <= $start) {
            return 0.0;
        }

        // Iterate from the calendar day BEFORE $start through $end so we never miss
        // the previous-night window for early-morning shifts. For typical shifts
        // (≤ 24h) this loop runs at most three times.
        $totalSeconds = 0;
        $iter = (clone $start)->setTime(0, 0, 0)->modify('-1 day');
        $stopDay = (clone $end)->setTime(0, 0, 0);

        while ($iter <= $stopDay) {
            $windowStart = (clone $iter)->setTime(23, 0, 0);
            $windowEnd = (clone $iter)->modify('+1 day')->setTime(6, 0, 0);

            $overlapStart = max($start, $windowStart);
            $overlapEnd = min($end, $windowEnd);

            if ($overlapEnd > $overlapStart) {
                $totalSeconds += $overlapEnd->getTimestamp() - $overlapStart->getTimestamp();
            }

            $iter->modify('+1 day');
        }

        return $totalSeconds / 3600;
    }

    /**
     * Check if a date is a German public holiday
     *
     * @param \DateTime $date
     * @param string|null $state Optional German state code (e.g., 'NW' for Nordrhein-Westfalen)
     * @return bool
     */
    public function isGermanPublicHoliday(\DateTime $date, ?string $state = null): bool
    {
        $checkDate = (clone $date)->setTime(0, 0, 0);

        if ($state !== null && $state !== '') {
            return $this->holidayCalendarService->isHolidayForState($state, $checkDate);
        }

        // Legacy-style call without explicit state falls back to the app default state.
        $defaultState = $this->config->getAppValue('arbeitszeitcheck', 'german_state', 'NW');

        return $this->holidayCalendarService->isHolidayForState($defaultState, $checkDate);
    }

    /**
     * Run daily compliance check for all users
     *
     * This method should be called by a Nextcloud cron job to check all users
     * for compliance violations on a daily basis.
     *
     * @return array Statistics about the compliance check
     */
    public function runDailyComplianceCheck(): array
    {
        // Calendar day boundaries in organisation storage TZ (never PHP default / container UTC).
        [$todayStart] = $this->timeZoneService->todayWindowInStorage();
        $yesterday = (clone $todayStart)->modify('-1 day');
        $today = clone $todayStart;

        $stats = [
            'users_checked' => 0,
            'violations_found' => 0,
            'check_date' => $yesterday->format('Y-m-d')
        ];

        // Iterate through all users
        $this->userManager->callForAllUsers(function ($user) use ($yesterday, $today, &$stats) {
            $userId = $user->getUID();
            if (!$this->permissionService->isUserAllowedByAccessGroups($userId)) {
                return;
            }
            $stats['users_checked']++;

            // Count existing violations for this user from yesterday before checks
            $violationsBefore = $this->violationMapper->findByDateRange($yesterday, $today, $userId);
            $violationCountBefore = count($violationsBefore);

            // Get all time entries from yesterday
            $entries = $this->timeEntryMapper->findByUserAndDateRange($userId, $yesterday, $today);

            // Check compliance for each completed entry
            foreach ($entries as $entry) {
                if ($entry->getStatus() === TimeEntry::STATUS_COMPLETED && $entry->getEndTime() !== null) {
                    // Check if violations already exist for this entry
                    $hasExistingViolation = false;
                    foreach ($violationsBefore as $existing) {
                        if ($existing->getTimeEntryId() === $entry->getId()) {
                            $hasExistingViolation = true;
                            break;
                        }
                    }

                    // Only check if no violations exist yet for this entry
                    if (!$hasExistingViolation) {
                        $this->checkMandatoryBreaks($entry);
                        $this->checkExcessiveWorkingHours($entry);
                        $this->checkNightWork($entry);
                        $this->checkSundayAndHolidayWork($entry);
                    }
                }
            }

            // Check for weekly hours limit violations
            $weeklyHoursCheck = $this->checkWeeklyWorkingHoursLimit($userId);
            if (!$weeklyHoursCheck) {
                // Create violation if not already exists for this period
                $weekStart = clone $yesterday;
                $weekStart->modify('monday this week');
                $weekEnd = clone $weekStart;
                $weekEnd->modify('+7 days');

                $existingWeeklyViolations = $this->violationMapper->findByDateRange($weekStart, $weekEnd, $userId);
                $hasWeeklyViolation = false;
                foreach ($existingWeeklyViolations as $existing) {
                    if ($existing->getViolationType() === ComplianceViolation::TYPE_WEEKLY_HOURS_LIMIT_EXCEEDED) {
                        $hasWeeklyViolation = true;
                        break;
                    }
                }

                if (!$hasWeeklyViolation) {
                    $this->violationMapper->createViolation(
                        $userId,
                        ComplianceViolation::TYPE_WEEKLY_HOURS_LIMIT_EXCEEDED,
                        $this->l10n->t('Weekly working hours average limit (48 hours) exceeded over the last 6 months'),
                        $yesterday,
                        null,
                        ComplianceViolation::SEVERITY_WARNING
                    );
                }
            }

            // Count violations after checks to see how many were created
            $violationsAfter = $this->violationMapper->findByDateRange($yesterday, $today, $userId);
            $violationCountAfter = count($violationsAfter);
            $newViolations = $violationCountAfter - $violationCountBefore;
            $stats['violations_found'] += $newViolations;
        });

        return $stats;
    }

    /**
     * Get compliance status for a user
     *
     * @param string $userId
     * @return array{compliant: bool, score: int, violation_count: int, critical_violations: int, warning_violations: int, info_violations: int, has_data: bool, last_check: \DateTime}
     */
    public function getComplianceStatus(string $userId): array
    {
        $unresolvedViolations = $this->violationMapper->findByUser($userId, false);

        $critical = 0;
        $warning = 0;
        $info = 0;
        foreach ($unresolvedViolations as $violation) {
            switch ($violation->getSeverity()) {
                case ComplianceViolation::SEVERITY_ERROR:
                    $critical++;
                    break;
                case ComplianceViolation::SEVERITY_WARNING:
                    $warning++;
                    break;
                case ComplianceViolation::SEVERITY_INFO:
                    $info++;
                    break;
            }
        }

        $compliant = empty($unresolvedViolations);

        // Score: 100 = perfect, reduced by severity-weighted violations (max -100)
        $score = 100;
        $score -= min(
			\OCA\ArbeitszeitCheck\Constants::COMPLIANCE_SCORE_MAX_DEDUCTION,
			($critical * \OCA\ArbeitszeitCheck\Constants::COMPLIANCE_SCORE_CRITICAL_WEIGHT)
			+ ($warning * \OCA\ArbeitszeitCheck\Constants::COMPLIANCE_SCORE_WARNING_WEIGHT)
			+ ($info * \OCA\ArbeitszeitCheck\Constants::COMPLIANCE_SCORE_INFO_WEIGHT)
		);

        // Check if we have analyzable data (time entries exist)
        $timeEntryCount = $this->timeEntryMapper->countByUser($userId);
        $hasData = $timeEntryCount > 0;

        return [
            'compliant' => $compliant,
            'score' => max(0, $score),
            'violation_count' => count($unresolvedViolations),
            'critical_violations' => $critical,
            'warning_violations' => $warning,
            'info_violations' => $info,
            'has_data' => $hasData,
            'last_check' => new \DateTime()
        ];
    }

    /**
     * Generate compliance report for a date range
     *
     * @param \DateTime $startDate
     * @param \DateTime $endDate
     * @param string|null $userId
     * @return array
     */
    public function generateComplianceReport(\DateTime $startDate, \DateTime $endDate, ?string $userId = null): array
    {
        $violations = $this->violationMapper->findByDateRange($startDate, $endDate, $userId);

        $report = [
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d')
            ],
            'total_violations' => count($violations),
            'violations_by_type' => [],
            'violations_by_severity' => [],
            'violations_by_user' => [],
            'compliance_rate' => 0,
            'generated_at' => new \DateTime()
        ];

        foreach ($violations as $violation) {
            // Group by type
            $type = $violation->getViolationType();
            if (!isset($report['violations_by_type'][$type])) {
                $report['violations_by_type'][$type] = 0;
            }
            $report['violations_by_type'][$type]++;

            // Group by severity
            $severity = $violation->getSeverity();
            if (!isset($report['violations_by_severity'][$severity])) {
                $report['violations_by_severity'][$severity] = 0;
            }
            $report['violations_by_severity'][$severity]++;

            // Group by user
            $user = $violation->getUserId();
            if (!isset($report['violations_by_user'][$user])) {
                $report['violations_by_user'][$user] = 0;
            }
            $report['violations_by_user'][$user]++;
        }

        return $report;
    }
}