<?php

declare(strict_types=1);

/**
 * Tests for TimeTrackingService
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service;

use OCA\ArbeitszeitCheck\Db\TimeEntryMapper;
use OCA\ArbeitszeitCheck\Db\ComplianceViolationMapper;
use OCA\ArbeitszeitCheck\Db\AuditLogMapper;
use OCA\ArbeitszeitCheck\Db\UserSettingsMapper;
use OCA\ArbeitszeitCheck\Db\UserWorkingTimeModelMapper;
use OCA\ArbeitszeitCheck\Db\WorkingTimeModelMapper;
use OCA\ArbeitszeitCheck\Service\ComplianceService;
use OCA\ArbeitszeitCheck\Service\DailyWorkingHoursCalculator;
use OCA\ArbeitszeitCheck\Service\TimeTrackingService;
use OCA\ArbeitszeitCheck\Service\TimeCaptureMethodService;
use OCA\ArbeitszeitCheck\Service\TimeZoneService;
use OCA\ArbeitszeitCheck\Service\ProjectCheckIntegrationService;
use OCA\ArbeitszeitCheck\Service\MonthClosureGuard;
use OCA\ArbeitszeitCheck\Db\TimeEntry;
use OCA\ArbeitszeitCheck\Exception\BusinessRuleException;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IDateTimeZone;
use OCP\IL10N;
use OCP\IUserSession;
use OCP\Lock\ILockingProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Class TimeTrackingServiceTest
 */
class TimeTrackingServiceTest extends TestCase {

	/** @var TimeTrackingService */
	private $service;

	/** @var TimeEntryMapper|\PHPUnit\Framework\MockObject\MockObject */
	private $timeEntryMapper;

	/** @var ComplianceViolationMapper|\PHPUnit\Framework\MockObject\MockObject */
	private $violationMapper;

	/** @var AuditLogMapper|\PHPUnit\Framework\MockObject\MockObject */
	private $auditLogMapper;

	/** @var ProjectCheckIntegrationService|\PHPUnit\Framework\MockObject\MockObject */
	private $projectCheckService;

	/** @var IL10N|\PHPUnit\Framework\MockObject\MockObject */
	private $l10n;

	/** @var DailyWorkingHoursCalculator */
	private $dailyHoursCalculator;

	/** @var TimeZoneService */
	private $timeZoneService;

	protected function setUp(): void {
		parent::setUp();

		$this->timeEntryMapper = $this->createMock(TimeEntryMapper::class);
		$this->violationMapper = $this->createMock(ComplianceViolationMapper::class);
		$this->auditLogMapper = $this->createMock(AuditLogMapper::class);
		$this->projectCheckService = $this->createMock(ProjectCheckIntegrationService::class);
		$this->l10n = $this->createMock(IL10N::class);
		$complianceService = $this->createMock(ComplianceService::class);
		$complianceService->method('checkComplianceBeforeClockIn')->willReturn([]);
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(fn ($app, $key, $default) => match ($key) {
			'max_daily_hours' => '10',
			'min_rest_period' => '11',
			'app_timezone' => 'Europe/Berlin',
			default => $default
		});
		$config->method('getUserValue')->willReturn('');
		$userSettingsMapper = $this->createMock(UserSettingsMapper::class);
		$userSettingsMapper->method('getStringSetting')->willReturn('1');
		$userWorkingTimeModelMapper = $this->createMock(UserWorkingTimeModelMapper::class);
		$workingTimeModelMapper = $this->createMock(WorkingTimeModelMapper::class);
		$monthClosureGuard = $this->createMock(MonthClosureGuard::class);
		$db = $this->createMock(IDBConnection::class);
		$lockingProvider = $this->createMock(ILockingProvider::class);

		// TimeZoneService is intentionally instantiated for real here: it has
		// no side effects and its behaviour is part of the contract under test
		// (storage TZ resolution, now() in storage TZ, day windows). Using the
		// real object makes the suite assert that contract end-to-end.
		$dateTimeZone = $this->createMock(IDateTimeZone::class);
		$dateTimeZone->method('getTimeZone')->willReturn(new \DateTimeZone('Europe/Berlin'));
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn(null);
		$timeZoneService = new TimeZoneService($config, $dateTimeZone, $userSession, new NullLogger());
		$this->timeZoneService = $timeZoneService;
		$dailyHoursCalculator = new DailyWorkingHoursCalculator($this->timeEntryMapper, $timeZoneService);
		$this->dailyHoursCalculator = $dailyHoursCalculator;

		$timeCaptureMethodService = $this->createMock(TimeCaptureMethodService::class);
		$timeCaptureMethodService->method('assertClockStampingAllowed')->willReturnCallback(static function (): void {
		});

		$this->service = new TimeTrackingService(
			$this->timeEntryMapper,
			$this->violationMapper,
			$this->auditLogMapper,
			$this->projectCheckService,
			$complianceService,
			$this->l10n,
			$config,
			$userSettingsMapper,
			$userWorkingTimeModelMapper,
			$workingTimeModelMapper,
			$monthClosureGuard,
			$db,
			$lockingProvider,
			$timeZoneService,
			$dailyHoursCalculator,
			null,
			$timeCaptureMethodService,
		);

		$this->timeEntryMapper->method('findStalePausedAutomaticEntries')->willReturn([]);
	}

	/**
	 * Test that clocking in when already clocked in throws exception
	 */
	public function testClockInWhenAlreadyActiveThrowsException(): void {
		$userId = 'testuser';

		// Mock that user is already clocked in
		$this->timeEntryMapper->expects($this->once())
			->method('findActiveByUser')
			->with($userId)
			->willReturn($this->createMock(\OCA\ArbeitszeitCheck\Db\TimeEntry::class));

		$this->l10n->expects($this->once())
			->method('t')
			->with('User is already clocked in')
			->willReturn('User is already clocked in');

		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('User is already clocked in');

		$this->service->clockIn($userId);
	}

	/**
	 * Test successful clock in
	 */
	public function testClockInSuccess(): void {
		$userId = 'testuser';
		$projectId = 'proj123';
		$description = 'Working on project';

		$this->timeEntryMapper->expects($this->once())
			->method('findActiveByUser')
			->with($userId)
			->willReturn(null);

		$this->timeEntryMapper->expects($this->atLeastOnce())
			->method('findOnBreakByUser')
			->with($userId)
			->willReturn(null);

		$this->timeEntryMapper->method('getTotalHoursByUserAndDateRange')
			->willReturn(0.0);

		// Mock project validation
		$this->projectCheckService->expects($this->once())
			->method('userMayAttachProjectCheckProjectToOwnTime')
			->with($userId, $projectId)
			->willReturn(true);

		// Mock compliance check (no violations)
		$this->violationMapper->expects($this->never())
			->method('createViolation');

		// Mock time entry creation and saving
		$mockEntry = $this->createMock(\OCA\ArbeitszeitCheck\Db\TimeEntry::class);
		$this->timeEntryMapper->expects($this->once())
			->method('insert')
			->willReturn($mockEntry);

		// Mock audit logging
		$this->auditLogMapper->expects($this->once())
			->method('logAction')
			->with($userId, 'clock_in', 'time_entry', $this->anything(), null, $this->anything());

		$result = $this->service->clockIn($userId, $projectId, $description);

		$this->assertSame($mockEntry, $result);
	}

	/**
	 * Test clocking in with invalid project throws exception
	 */
	public function testClockInWithInvalidProjectThrowsException(): void {
		$userId = 'testuser';
		$projectId = '999';

		$this->projectCheckService->expects($this->once())
			->method('userMayAttachProjectCheckProjectToOwnTime')
			->with($userId, $projectId)
			->willReturn(false);

		$this->l10n->expects($this->once())
			->method('t')
			->with($this->stringContains('cannot clock in'))
			->willReturn('You cannot clock in on the selected project.');

		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('You cannot clock in on the selected project.');

		$this->service->clockIn($userId, $projectId);
	}

	/**
	 * Test getting current status
	 */
	public function testGetStatus(): void {
		$userId = 'testuser';

		// Mock active entry
		$mockEntry = new \OCA\ArbeitszeitCheck\Db\TimeEntry();
		$mockEntry->setId(1);
		$mockEntry->setUserId($userId);
		$mockEntry->setStatus(\OCA\ArbeitszeitCheck\Db\TimeEntry::STATUS_ACTIVE);
		$mockEntry->setStartTime(new \DateTime()); // avoid flakiness from "now - startTime" exceeding max daily hours
		$mockEntry->setEndTime(null);
		$mockEntry->setBreaks(json_encode([]));
		$mockEntry->setIsManualEntry(false);
		$mockEntry->setCreatedAt(new \DateTime());
		$mockEntry->setUpdatedAt(new \DateTime());

		$this->timeEntryMapper->expects($this->atLeastOnce())
			->method('findActiveByUser')
			->with($userId)
			->willReturn($mockEntry);

		$this->timeEntryMapper->expects($this->atLeastOnce())
			->method('findOverlapping')
			->willReturn([$mockEntry]);

		$result = $this->service->getStatus($userId);

		$this->assertEquals('active', $result['status']);
		$this->assertEquals(0.0, $result['working_today_hours']);
		$this->assertIsBool($result['at_daily_maximum']);
		$this->assertIsFloat($result['session_hours_on_calendar_today']);
	}

	/**
	 * Test getting status when not clocked in
	 */
	public function testGetStatusWhenNotActive(): void {
		$userId = 'testuser';

		// Mock no active entry
		$this->timeEntryMapper->expects($this->atLeastOnce())
			->method('findActiveByUser')
			->with($userId)
			->willReturn(null);

		// Mock no break entry
		$this->timeEntryMapper->expects($this->atLeastOnce())
			->method('findOnBreakByUser')
			->with($userId)
			->willReturn(null);

		$this->timeEntryMapper->expects($this->atLeastOnce())
			->method('findOverlapping')
			->willReturn([]);

		$result = $this->service->getStatus($userId);

		$this->assertEquals('clocked_out', $result['status']);
		$this->assertNull($result['current_entry']);
		$this->assertEquals(0.0, $result['working_today_hours']);
		$this->assertIsBool($result['at_daily_maximum']);
		$this->assertSame(0.0, $result['session_hours_on_calendar_today']);
	}

	public function testClockInResumesPausedEntryWhenPausedEntryExists(): void
	{
		$userId = 'testuser';

		$this->timeEntryMapper->expects($this->once())
			->method('findActiveByUser')
			->with($userId)
			->willReturn(null);

		$this->timeEntryMapper->expects($this->once())
			->method('findOnBreakByUser')
			->with($userId)
			->willReturn(null);

		$start = (new \DateTime())->setTime(9, 0, 0);
		$pausedAt = (new \DateTime())->setTime(12, 0, 0);

		$pausedEntry = new TimeEntry();
		$pausedEntry->setId(123);
		$pausedEntry->setUserId($userId);
		$pausedEntry->setStatus(TimeEntry::STATUS_PAUSED);
		$pausedEntry->setStartTime($start);
		$pausedEntry->setUpdatedAt($pausedAt);
		$pausedEntry->setBreaks(json_encode([[
			'start' => $start->format('c'),
			'end' => (clone $start)->modify('+15 minutes')->format('c'),
			'duration_minutes' => 15,
			'automatic' => false,
			'reason' => 'Manual break',
		]]));
		$pausedEntry->setIsManualEntry(false);
		$pausedEntry->setCreatedAt(new \DateTime());

		$this->timeEntryMapper->expects($this->once())
			->method('findPausedOrUnfinishedTodayByUser')
			->with(
				$userId,
				$this->isInstanceOf(\DateTime::class),
				$this->isInstanceOf(\DateTime::class)
			)
			->willReturn($pausedEntry);

		$this->timeEntryMapper->method('findOverlapping')->willReturn([]);

		$this->timeEntryMapper->expects($this->once())
			->method('update')
			->willReturnCallback(static function (TimeEntry $entry): TimeEntry {
				$entry->setId(999);
				return $entry;
			});

		$this->auditLogMapper->expects($this->once())->method('logAction')->with(
			$userId,
			'clock_in',
			'time_entry',
			999,
			null,
			$this->anything()
		);

		$result = $this->service->clockIn($userId);
		$this->assertSame(999, $result->getId());
		$this->assertSame(TimeEntry::STATUS_ACTIVE, $result->getStatus());
		$this->assertNotNull($result->getBreaks());
	}

	public function testClockInDoesNotResumePausedEntryWhenStampingDisabled(): void
	{
		$userId = 'testuser';

		$this->timeEntryMapper->method('findActiveByUser')->willReturn(null);
		$this->timeEntryMapper->method('findOnBreakByUser')->willReturn(null);

		$pausedEntry = new TimeEntry();
		$pausedEntry->setId(123);
		$pausedEntry->setUserId($userId);
		$pausedEntry->setStatus(TimeEntry::STATUS_PAUSED);
		$pausedEntry->setStartTime((new \DateTime())->setTime(9, 0, 0));
		$pausedEntry->setUpdatedAt(new \DateTime());
		$pausedEntry->setCreatedAt(new \DateTime());

		$this->timeEntryMapper->method('findPausedOrUnfinishedTodayByUser')->willReturn($pausedEntry);
		$this->timeEntryMapper->expects($this->never())->method('update');

		$timeCapture = $this->createMock(TimeCaptureMethodService::class);
		$timeCapture->expects($this->once())
			->method('assertClockStampingAllowed')
			->with($userId)
			->willThrowException(new \OCA\ArbeitszeitCheck\Exception\TimeCaptureForbiddenException(
				'Clock in/out (stamping) is not enabled for your account.',
				\OCA\ArbeitszeitCheck\Exception\TimeCaptureForbiddenException::CODE_CLOCK_STAMPING_DISABLED,
			));

		$ref = new \ReflectionClass($this->service);
		$prop = $ref->getProperty('timeCaptureMethodService');
		$prop->setAccessible(true);
		$prop->setValue($this->service, $timeCapture);

		$this->expectException(\OCA\ArbeitszeitCheck\Exception\TimeCaptureForbiddenException::class);
		$this->service->clockIn($userId);
	}

	public function testClockInWithPausedEntryFromPreviousDayStartsNewEntry(): void
	{
		$userId = 'testuser';

		$this->timeEntryMapper->method('findActiveByUser')->willReturn(null);
		$this->timeEntryMapper->method('findOnBreakByUser')->willReturn(null);
		$this->timeEntryMapper->method('findPausedOrUnfinishedTodayByUser')->willReturn(null);

		$startYesterday = (new \DateTime())->modify('-1 day')->setTime(9, 0, 0);
		$pausedOneHourAgo = (new \DateTime())->modify('-1 hour');

		$pausedEntry = new TimeEntry();
		$pausedEntry->setId(123);
		$pausedEntry->setUserId($userId);
		$pausedEntry->setStatus(TimeEntry::STATUS_PAUSED);
		$pausedEntry->setStartTime($startYesterday);
		$pausedEntry->setUpdatedAt($pausedOneHourAgo);
		$pausedEntry->setBreaks('');
		$pausedEntry->setIsManualEntry(false);
		$pausedEntry->setCreatedAt(new \DateTime());

		$this->timeEntryMapper->method('getTotalHoursByUserAndDateRange')->willReturn(0.0);
		$this->timeEntryMapper->expects($this->once())
			->method('insert')
			->willReturnCallback(static function (TimeEntry $entry): TimeEntry {
				$entry->setId(321);
				return $entry;
			});

		$result = $this->service->clockIn($userId);
		$this->assertSame(321, $result->getId());
		$this->assertSame(TimeEntry::STATUS_ACTIVE, $result->getStatus());
	}

	public function testClockInResumeFailsWhenMaxDailyHoursWouldBeExceeded(): void
	{
		$userId = 'testuser';

		$this->timeEntryMapper->method('findActiveByUser')->willReturn(null);
		$this->timeEntryMapper->method('findOnBreakByUser')->willReturn(null);
		$this->timeEntryMapper->method('findPausedOrUnfinishedTodayByUser')->willReturn(null);

		$start = (new \DateTime())->setTime(9, 0, 0);
		$pausedAt = (new \DateTime())->setTime(18, 0, 0); // 9 hours duration

		$pausedEntry = new TimeEntry();
		$pausedEntry->setId(123);
		$pausedEntry->setUserId($userId);
		$pausedEntry->setStatus(TimeEntry::STATUS_PAUSED);
		$pausedEntry->setStartTime($start);
		$pausedEntry->setUpdatedAt($pausedAt);
		$pausedEntry->setBreaks('');
		$pausedEntry->setIsManualEntry(false);
		$pausedEntry->setCreatedAt(new \DateTime());

		$tz = new \DateTimeZone('Europe/Berlin');
		$now = new \DateTime('now', $tz);
		// Fully completed span ending in the past so daily-hours clipping does not shrink it.
		$end = (clone $now)->modify('-30 minutes');
		$startHeavy = (clone $end)->modify('-10 hours -30 minutes');
		$heavy = new TimeEntry();
		$heavy->setId(1);
		$heavy->setUserId($userId);
		$heavy->setStatus(TimeEntry::STATUS_COMPLETED);
		$heavy->setStartTime($startHeavy);
		$heavy->setEndTime($end);
		$heavy->setBreaks(json_encode([]));
		$this->timeEntryMapper->method('findOverlapping')->willReturn([$heavy]);

		$this->l10n->method('t')->willReturnCallback(static fn ($s) => $s);

		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Maximum daily working hours');

		$this->service->clockIn($userId);
	}

	/**
	 * Auto-completion at the daily maximum must:
	 *  - cap the entry's end time so working hours == max,
	 *  - mark the entry as completed,
	 *  - record the audit-trail reason ENDED_REASON_AUTO_DAILY_MAX, and
	 *  - record the policy 'arbzg_daily_maximum'.
	 *
	 * Uses fixed storage-TZ instants (not wall-clock-relative offsets) so the
	 * suite is deterministic at any hour of the day.
	 */
	public function testCompleteEntryIfDailyMaximumReachedSetsAuditFields(): void
	{
		$userId = 'testuser';
		$tz = new \DateTimeZone('Europe/Berlin');

		// Same calendar day: 06:00–17:00 → 11 h on 2026-05-20, above the 10 h §3 cap.
		$start = new \DateTime('2026-05-20 06:00:00', $tz);
		$referenceNow = new \DateTime('2026-05-20 17:00:00', $tz);

		$entry = new TimeEntry();
		$entry->setId(42);
		$entry->setUserId($userId);
		$entry->setStatus(TimeEntry::STATUS_ACTIVE);
		$entry->setStartTime($start);
		$entry->setEndTime(null);
		$entry->setBreaks(json_encode([]));
		$entry->setIsManualEntry(false);
		$entry->setCreatedAt(clone $start);
		$entry->setUpdatedAt(clone $referenceNow);

		// No other overlapping entries on today's calendar day
		$this->timeEntryMapper->method('findOverlapping')->willReturn([$entry]);

		$capturedEntry = null;
		$this->timeEntryMapper->expects($this->once())
			->method('update')
			->willReturnCallback(static function (TimeEntry $arg) use (&$capturedEntry): TimeEntry {
				$capturedEntry = $arg;
				return $arg;
			});

		$this->auditLogMapper->expects($this->once())
			->method('logAction')
			->with(
				$userId,
				'time_entry_auto_completed_daily_max',
				'time_entry',
				42,
				$this->anything(),
				$this->anything(),
				'system'
			);

		$result = $this->service->completeEntryIfDailyMaximumReached($entry, $referenceNow);

		$this->assertTrue($result, 'Entry must be reported as auto-completed.');
		$this->assertInstanceOf(TimeEntry::class, $capturedEntry);
		/** @var TimeEntry $capturedEntry */
		$this->assertSame(TimeEntry::STATUS_COMPLETED, $capturedEntry->getStatus());
		$this->assertSame(TimeEntry::ENDED_REASON_AUTO_DAILY_MAX, $capturedEntry->getEndedReason());
		$this->assertSame('arbzg_daily_maximum', $capturedEntry->getPolicyApplied());
		$endTime = $capturedEntry->getEndTime();
		$this->assertInstanceOf(\DateTime::class, $endTime, 'End time must be set.');

		// ArbZG §3 is evaluated per calendar day — never via raw entry span alone.
		[$dayStart, $dayEnd] = $this->timeZoneService->dayWindowInStorage($referenceNow);
		$calendarDayHours = $this->dailyHoursCalculator->getEntryWorkingHoursOnCalendarDay(
			$capturedEntry,
			$dayStart,
			$dayEnd,
			$endTime,
		);
		$this->assertLessThanOrEqual(
			10.001,
			$calendarDayHours,
			'Calendar-day working time must respect ArbZG §3 daily cap.'
		);
	}

	/**
	 * Overnight sessions must cap only today's portion (midnight clip), not the
	 * full entry span from yesterday evening.
	 */
	public function testCompleteEntryIfDailyMaximumReachedCapsOvernightSessionOnTodayOnly(): void
	{
		$userId = 'testuser';
		$tz = new \DateTimeZone('Europe/Berlin');

		// Monday 22:00 – still running; "now" is Tuesday 12:00 → 12 h on Tuesday.
		$start = new \DateTime('2026-05-19 22:00:00', $tz);
		$referenceNow = new \DateTime('2026-05-20 12:00:00', $tz);

		$entry = new TimeEntry();
		$entry->setId(43);
		$entry->setUserId($userId);
		$entry->setStatus(TimeEntry::STATUS_ACTIVE);
		$entry->setStartTime($start);
		$entry->setEndTime(null);
		$entry->setBreaks(json_encode([]));
		$entry->setIsManualEntry(false);
		$entry->setCreatedAt(clone $start);
		$entry->setUpdatedAt(clone $referenceNow);

		$this->timeEntryMapper->method('findOverlapping')->willReturn([$entry]);
		$this->timeEntryMapper->expects($this->once())->method('update');
		$this->auditLogMapper->expects($this->once())->method('logAction');

		$result = $this->service->completeEntryIfDailyMaximumReached($entry, $referenceNow);

		$this->assertTrue($result);
		$endTime = $entry->getEndTime();
		$this->assertInstanceOf(\DateTime::class, $endTime);

		[$tueStart, $tueEnd] = $this->timeZoneService->dayWindowInStorage($referenceNow);
		$tuesdayHours = $this->dailyHoursCalculator->getEntryWorkingHoursOnCalendarDay(
			$entry,
			$tueStart,
			$tueEnd,
			$endTime,
		);
		$this->assertLessThanOrEqual(10.001, $tuesdayHours, 'Tuesday portion must respect §3 cap.');

		// Entry span crosses midnight — total row duration may exceed 10 h legally.
		$entrySpanHours = $entry->getWorkingDurationHours() ?? 0.0;
		$this->assertGreaterThan(10.0, $entrySpanHours, 'Overnight row span may exceed 10 h.');
	}

	/**
	 * Below the maximum, completeEntryIfDailyMaximumReached must NOT auto-complete.
	 */
	public function testCompleteEntryIfDailyMaximumReachedIsNoOpBelowMax(): void
	{
		$userId = 'testuser';
		$tz = new \DateTimeZone('Europe/Berlin');

		$start = new \DateTime('2026-05-20 08:00:00', $tz);
		$referenceNow = new \DateTime('2026-05-20 12:00:00', $tz); // 4 h on the day

		$entry = new TimeEntry();
		$entry->setId(7);
		$entry->setUserId($userId);
		$entry->setStatus(TimeEntry::STATUS_ACTIVE);
		$entry->setStartTime($start);
		$entry->setEndTime(null);
		$entry->setBreaks(json_encode([]));
		$entry->setIsManualEntry(false);
		$entry->setCreatedAt(clone $start);
		$entry->setUpdatedAt(clone $referenceNow);

		$this->timeEntryMapper->method('findOverlapping')->willReturn([$entry]);

		// Must not write to the database under the threshold.
		$this->timeEntryMapper->expects($this->never())->method('update');
		$this->auditLogMapper->expects($this->never())->method('logAction');

		$result = $this->service->completeEntryIfDailyMaximumReached($entry, $referenceNow);

		$this->assertFalse($result, 'Below the daily maximum, no auto-completion may occur.');
		$this->assertNull($entry->getEndedReason(), 'Reason must remain unset below the threshold.');
		$this->assertSame(TimeEntry::STATUS_ACTIVE, $entry->getStatus());
	}

	/**
	 * One-click recovery from a `paused` row must:
	 *  - flip status to COMPLETED,
	 *  - default end time to updated_at (moment the entry was frozen),
	 *  - record an audit-trail reason and policy so the row is auditable,
	 *  - persist via update() and log a `time_entry_paused_completed` audit event.
	 */
	public function testCompletePausedEntryUsesUpdatedAtAsDefaultEndTime(): void
	{
		$userId = 'testuser';
		$start = (new \DateTime())->modify('-1 day')->setTime(9, 0, 0);
		$pausedAt = (clone $start)->modify('+8 hours'); // 17:00 yesterday

		$entry = new TimeEntry();
		$entry->setId(77);
		$entry->setUserId($userId);
		$entry->setStatus(TimeEntry::STATUS_PAUSED);
		$entry->setStartTime($start);
		$entry->setEndTime(null);
		$entry->setBreaks(json_encode([]));
		$entry->setIsManualEntry(false);
		$entry->setCreatedAt(clone $start);
		$entry->setUpdatedAt($pausedAt);

		$this->timeEntryMapper->expects($this->once())
			->method('find')
			->with(77)
			->willReturn($entry);

		$this->timeEntryMapper->method('findByUserAndDateRange')->willReturn([]);

		$capturedEntry = null;
		$this->timeEntryMapper->expects($this->once())
			->method('update')
			->willReturnCallback(static function (TimeEntry $arg) use (&$capturedEntry): TimeEntry {
				$capturedEntry = $arg;
				return $arg;
			});

		$this->auditLogMapper->expects($this->once())
			->method('logAction')
			->with(
				$userId,
				'time_entry_paused_completed',
				'time_entry',
				77,
				$this->anything(),
				$this->anything()
			);

		$result = $this->service->completePausedEntry($userId, 77);

		$this->assertSame($entry, $result);
		$this->assertInstanceOf(TimeEntry::class, $capturedEntry);
		/** @var TimeEntry $capturedEntry */
		$this->assertSame(TimeEntry::STATUS_COMPLETED, $capturedEntry->getStatus());
		$this->assertNotNull($capturedEntry->getEndTime());
		$this->assertGreaterThanOrEqual($start, $capturedEntry->getEndTime(), 'End time must not be before start.');
		$this->assertNotNull($capturedEntry->getEndedReason(), 'Audit reason must be set for traceability.');
		$this->assertNotNull($capturedEntry->getPolicyApplied(), 'Policy must be set so audits can identify the recovery path.');
	}

	/**
	 * Legacy rows can be `paused` while already carrying an `end_time` (status
	 * mismatch). Completion must keep that end_time — not overwrite it with
	 * `updated_at`, which would distort payroll hours.
	 */
	public function testCompletePausedEntryPreservesExistingEndTime(): void
	{
		$userId = 'testuser';
		$start = (new \DateTime())->modify('-1 day')->setTime(9, 0, 0);
		$frozenEnd = (clone $start)->modify('+8 hours');
		$pausedAt = (clone $frozenEnd)->modify('+30 minutes');

		$entry = new TimeEntry();
		$entry->setId(78);
		$entry->setUserId($userId);
		$entry->setStatus(TimeEntry::STATUS_PAUSED);
		$entry->setStartTime($start);
		$entry->setEndTime($frozenEnd);
		$entry->setBreaks(json_encode([]));
		$entry->setIsManualEntry(false);
		$entry->setCreatedAt(clone $start);
		$entry->setUpdatedAt($pausedAt);

		$this->timeEntryMapper->method('find')->with(78)->willReturn($entry);
		$this->timeEntryMapper->method('findByUserAndDateRange')->willReturn([]);

		$capturedEntry = null;
		$this->timeEntryMapper->expects($this->once())
			->method('update')
			->willReturnCallback(static function (TimeEntry $arg) use (&$capturedEntry): TimeEntry {
				$capturedEntry = $arg;
				return $arg;
			});

		$this->auditLogMapper->expects($this->once())->method('logAction');

		$this->service->completePausedEntry($userId, 78);

		$this->assertInstanceOf(TimeEntry::class, $capturedEntry);
		/** @var TimeEntry $capturedEntry */
		$this->assertSame(TimeEntry::STATUS_COMPLETED, $capturedEntry->getStatus());
		$this->assertEquals($frozenEnd, $capturedEntry->getEndTime());
	}

	/**
	 * An explicit caller-supplied end time (e.g. from an admin or `occ` command)
	 * must win over the default updated_at, but the service must still enforce
	 * `end >= start` so a bad override cannot create a negative duration row.
	 */
	public function testCompletePausedEntryRespectsExplicitEndTime(): void
	{
		$userId = 'testuser';
		$start = (new \DateTime())->modify('-1 day')->setTime(9, 0, 0);
		$pausedAt = (clone $start)->modify('+2 hours');
		$explicit = (clone $start)->modify('+7 hours 30 minutes'); // 16:30

		$entry = new TimeEntry();
		$entry->setId(88);
		$entry->setUserId($userId);
		$entry->setStatus(TimeEntry::STATUS_PAUSED);
		$entry->setStartTime($start);
		$entry->setEndTime(null);
		$entry->setBreaks(json_encode([]));
		$entry->setIsManualEntry(false);
		$entry->setCreatedAt(clone $start);
		$entry->setUpdatedAt($pausedAt);

		$this->timeEntryMapper->method('find')->with(88)->willReturn($entry);
		$this->timeEntryMapper->method('findByUserAndDateRange')->willReturn([]);

		$capturedEntry = null;
		$this->timeEntryMapper->expects($this->once())
			->method('update')
			->willReturnCallback(static function (TimeEntry $arg) use (&$capturedEntry): TimeEntry {
				$capturedEntry = $arg;
				return $arg;
			});

		$this->auditLogMapper->expects($this->once())->method('logAction');

		$this->service->completePausedEntry($userId, 88, $explicit);

		$this->assertInstanceOf(TimeEntry::class, $capturedEntry);
		/** @var TimeEntry $capturedEntry */
		$this->assertSame(TimeEntry::STATUS_COMPLETED, $capturedEntry->getStatus());
		$captured = $capturedEntry->getEndTime();
		$this->assertNotNull($captured);
		// Daily-max adjustment may cap the end, but it must never come out earlier than the start.
		$this->assertGreaterThanOrEqual($start, $captured);
	}

	/**
	 * Completing an entry the caller does not own must be rejected with a
	 * business-rule error so the controller can map it to HTTP 403.
	 */
	public function testCompletePausedEntryRejectsOtherUsersEntry(): void
	{
		$entry = new TimeEntry();
		$entry->setId(99);
		$entry->setUserId('owner');
		$entry->setStatus(TimeEntry::STATUS_PAUSED);
		$entry->setStartTime(new \DateTime('-2 hours'));
		$entry->setUpdatedAt(new \DateTime('-1 hour'));
		$entry->setBreaks(json_encode([]));
		$entry->setIsManualEntry(false);
		$entry->setCreatedAt(new \DateTime('-2 hours'));

		$this->timeEntryMapper->method('find')->with(99)->willReturn($entry);
		$this->timeEntryMapper->expects($this->never())->method('update');
		$this->auditLogMapper->expects($this->never())->method('logAction');

		$this->l10n->method('t')->willReturnCallback(static fn ($s) => $s);

		$this->expectException(\OCA\ArbeitszeitCheck\Exception\BusinessRuleException::class);
		$this->expectExceptionMessage('Access denied');

		$this->service->completePausedEntry('intruder', 99);
	}

	/**
	 * Completing a non-paused entry must be rejected: this endpoint is the
	 * dedicated recovery path for the broken `paused` state and must not be
	 * abused to silently re-finalise already-completed rows.
	 */
	public function testCompletePausedEntryRejectsNonPausedStatus(): void
	{
		$entry = new TimeEntry();
		$entry->setId(101);
		$entry->setUserId('testuser');
		$entry->setStatus(TimeEntry::STATUS_ACTIVE);
		$entry->setStartTime(new \DateTime('-2 hours'));
		$entry->setBreaks(json_encode([]));
		$entry->setIsManualEntry(false);
		$entry->setCreatedAt(new \DateTime('-2 hours'));
		$entry->setUpdatedAt(new \DateTime('-1 hour'));

		$this->timeEntryMapper->method('find')->with(101)->willReturn($entry);
		$this->timeEntryMapper->expects($this->never())->method('update');
		$this->auditLogMapper->expects($this->never())->method('logAction');

		$this->l10n->method('t')->willReturnCallback(static fn ($s) => $s);

		$this->expectException(\OCA\ArbeitszeitCheck\Exception\BusinessRuleException::class);
		$this->expectExceptionMessage('not in a paused state');

		$this->service->completePausedEntry('testuser', 101);
	}

	/**
	 * A negative or zero entry ID must be rejected up front. Without this guard
	 * the mapper would issue a SELECT WHERE id=0 (or worse, attempt index lookup
	 * by 0/-1) and the user would receive an opaque NOT FOUND that obscures the
	 * actual programmer error.
	 */
	public function testCompletePausedEntryRejectsInvalidId(): void
	{
		$this->l10n->method('t')->willReturnCallback(static fn ($s) => $s);
		$this->timeEntryMapper->expects($this->never())->method('find');

		$this->expectException(\OCA\ArbeitszeitCheck\Exception\BusinessRuleException::class);
		$this->expectExceptionMessage('Invalid entry ID');

		$this->service->completePausedEntry('testuser', 0);
	}
}