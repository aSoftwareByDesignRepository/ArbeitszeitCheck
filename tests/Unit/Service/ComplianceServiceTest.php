<?php

declare(strict_types=1);

/**
 * Unit tests for ComplianceService
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service;

use OCA\ArbeitszeitCheck\Db\TimeEntry;
use OCA\ArbeitszeitCheck\Db\TimeEntryMapper;
use OCA\ArbeitszeitCheck\Db\ComplianceViolationMapper;
use OCA\ArbeitszeitCheck\Db\WorkingTimeModelMapper;
use OCA\ArbeitszeitCheck\Db\UserWorkingTimeModelMapper;
use OCA\ArbeitszeitCheck\Db\ComplianceViolation;
use OCA\ArbeitszeitCheck\Service\ComplianceService;
use OCA\ArbeitszeitCheck\Service\HolidayService;
use OCA\ArbeitszeitCheck\Service\NotificationService;
use OCA\ArbeitszeitCheck\Service\PermissionService;
use OCA\ArbeitszeitCheck\Service\TimeZoneService;
use OCP\IConfig;
use OCP\IDateTimeZone;
use OCP\IL10N;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Class ComplianceServiceTest
 */
class ComplianceServiceTest extends TestCase
{
	/** @var ComplianceService */
	private $service;

	/** @var TimeEntryMapper|\PHPUnit\Framework\MockObject\MockObject */
	private $timeEntryMapper;

	/** @var ComplianceViolationMapper|\PHPUnit\Framework\MockObject\MockObject */
	private $violationMapper;

	/** @var WorkingTimeModelMapper|\PHPUnit\Framework\MockObject\MockObject */
	private $workingTimeModelMapper;

	/** @var UserWorkingTimeModelMapper|\PHPUnit\Framework\MockObject\MockObject */
	private $userWorkingTimeModelMapper;

	/** @var IUserManager|\PHPUnit\Framework\MockObject\MockObject */
	private $userManager;

	/** @var IL10N|\PHPUnit\Framework\MockObject\MockObject */
	private $l10n;

	/** @var NotificationService|\PHPUnit\Framework\MockObject\MockObject */
	private $notificationService;

	/** @var HolidayService|\PHPUnit\Framework\MockObject\MockObject */
	private $holidayCalendarService;

	/** @var IConfig|\PHPUnit\Framework\MockObject\MockObject */
	private $config;
	/** @var PermissionService|\PHPUnit\Framework\MockObject\MockObject */
	private $permissionService;

	private TimeZoneService $timeZoneService;

	private function buildTimeZoneService(IConfig $config): TimeZoneService
	{
		$dateTimeZone = $this->createMock(IDateTimeZone::class);
		$dateTimeZone->method('getTimeZone')->willReturn(new \DateTimeZone('Europe/Berlin'));
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn(null);

		return new TimeZoneService($config, $dateTimeZone, $userSession, new NullLogger());
	}

	protected function setUp(): void
	{
		parent::setUp();

		$this->timeEntryMapper = $this->createMock(TimeEntryMapper::class);
		$this->violationMapper = $this->createMock(ComplianceViolationMapper::class);
		$this->workingTimeModelMapper = $this->createMock(WorkingTimeModelMapper::class);
		$this->userWorkingTimeModelMapper = $this->createMock(UserWorkingTimeModelMapper::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->notificationService = $this->createMock(NotificationService::class);
		$this->holidayCalendarService = $this->createMock(HolidayService::class);
		$this->config = $this->createMock(IConfig::class);
		$this->permissionService = $this->createMock(PermissionService::class);
		$this->permissionService->method('isUserAllowedByAccessGroups')->willReturn(true);
		$this->config->method('getAppValue')->willReturnCallback(static function (string $app, string $key, string $default = ''): string {
			return $default;
		});

		// Setup l10n mock to return translation keys
		$this->l10n->method('t')
			->willReturnCallback(function ($text) {
				return $text;
			});

		$this->timeZoneService = $this->buildTimeZoneService($this->config);
		$dailyHoursCalculator = new \OCA\ArbeitszeitCheck\Service\DailyWorkingHoursCalculator(
			$this->timeEntryMapper,
			$this->timeZoneService,
		);

		$this->service = new ComplianceService(
			$this->timeEntryMapper,
			$this->violationMapper,
			$this->workingTimeModelMapper,
			$this->userWorkingTimeModelMapper,
			$this->userManager,
			$this->l10n,
			$this->notificationService,
			$this->holidayCalendarService,
			$this->config,
			$this->permissionService,
			$this->timeZoneService,
			$dailyHoursCalculator,
		);
	}

	/**
	 * Test that checkComplianceBeforeClockIn returns no issues when compliant
	 */
	public function testCheckComplianceBeforeClockInCompliant(): void
	{
		$userId = 'testuser';

		// No previous entry (first clock-in): targeted queries return null.
		$this->timeEntryMapper->method('findLastCompletedBeforeTime')
			->with($userId, $this->isInstanceOf(\DateTime::class))
			->willReturn(null);
		$this->timeEntryMapper->method('findLastPausedWithinHours')
			->willReturn(null);

		$this->timeEntryMapper->method('findOverlapping')->willReturn([]);
		$this->timeEntryMapper->expects($this->once())
			->method('getTotalHoursByUserAndDateRange')
			->willReturn(240.0);

		$issues = $this->service->checkComplianceBeforeClockIn($userId);

		$this->assertIsArray($issues);
		$this->assertEmpty($issues, 'Should return no compliance issues when compliant');
	}

	/**
	 * Test that checkComplianceBeforeClockIn detects insufficient rest period
	 */
	public function testCheckComplianceBeforeClockInInsufficientRest(): void
	{
		$userId = 'testuser';

		// Previous entry ended less than 11 hours ago.
		$endTime = new \DateTime('now', new \DateTimeZone('Europe/Berlin'));
		$endTime->modify('-10 hours'); // Only 10 hours ago
		$lastEntry = new TimeEntry();
		$lastEntry->setId(1);
		$lastEntry->setUserId($userId);
		$lastEntry->setStartTime((clone $endTime)->modify('-8 hours'));
		$lastEntry->setEndTime($endTime);
		$lastEntry->setStatus(TimeEntry::STATUS_COMPLETED);
		$lastEntry->setIsManualEntry(false);
		$lastEntry->setCreatedAt(new \DateTime());
		$lastEntry->setUpdatedAt(new \DateTime());

		$this->timeEntryMapper->method('findLastCompletedBeforeTime')
			->with($userId, $this->isInstanceOf(\DateTime::class))
			->willReturn($lastEntry);

		$this->timeEntryMapper->method('findOverlapping')->willReturn([]);
		$this->timeEntryMapper->expects($this->once())
			->method('getTotalHoursByUserAndDateRange')
			->willReturn(240.0);

		$issues = $this->service->checkComplianceBeforeClockIn($userId);

		$this->assertNotEmpty($issues, 'Should detect insufficient rest period');
		$this->assertCount(1, $issues);
		$this->assertEquals(ComplianceViolation::TYPE_INSUFFICIENT_REST_PERIOD, $issues[0]['type']);
		$this->assertEquals(ComplianceViolation::SEVERITY_ERROR, $issues[0]['severity']);
		$this->assertStringContainsString('ended on', $issues[0]['message']);
	}

	/**
	 * Future-dated completed rows must not block clock-in (ArbZG §5 anchors on ended shifts only).
	 */
	public function testCheckComplianceBeforeClockInIgnoresFutureDatedLastEnd(): void
	{
		$userId = 'testuser';
		$tz = new \DateTimeZone('Europe/Berlin');

		// Real last shift ended 18 hours ago — rest long satisfied.
		$realEnd = new \DateTime('now', $tz);
		$realEnd->modify('-18 hours');
		$realEntry = new TimeEntry();
		$realEntry->setId(10);
		$realEntry->setUserId($userId);
		$realEntry->setStartTime((clone $realEnd)->modify('-7 hours 30 minutes'));
		$realEntry->setEndTime($realEnd);
		$realEntry->setStatus(TimeEntry::STATUS_COMPLETED);

		// findLastCompletedBeforeTime(now) skips a future "planned" end and returns the real one.
		$this->timeEntryMapper->method('findLastCompletedBeforeTime')
			->with($userId, $this->isInstanceOf(\DateTime::class))
			->willReturn($realEntry);

		$this->timeEntryMapper->method('findOverlapping')->willReturn([]);
		$this->timeEntryMapper->expects($this->once())
			->method('getTotalHoursByUserAndDateRange')
			->willReturn(240.0);

		$issues = $this->service->checkComplianceBeforeClockIn($userId);

		foreach ($issues as $issue) {
			$this->assertNotEquals(
				ComplianceViolation::TYPE_INSUFFICIENT_REST_PERIOD,
				$issue['type'],
				'Rest after an ended shift 18h ago must be satisfied; future-dated ends must not poison the check.'
			);
		}
	}

	/**
	 * Test that checkComplianceBeforeClockIn detects daily hours limit exceeded
	 */
	public function testCheckComplianceBeforeClockInDailyHoursExceeded(): void
	{
		$userId = 'testuser';

		// No previous completed or paused entry.
		$this->timeEntryMapper->method('findLastCompletedBeforeTime')
			->with($userId, $this->isInstanceOf(\DateTime::class))
			->willReturn(null);
		$this->timeEntryMapper->method('findLastPausedWithinHours')
			->willReturn(null);

		$tz = new \DateTimeZone('Europe/Berlin');
		$now = new \DateTime('now', $tz);
		$dayStart = (clone $now)->setTime(0, 0, 0);
		$hoursSinceMidnight = ($now->getTimestamp() - $dayStart->getTimestamp()) / 3600.0;
		if ($hoursSinceMidnight < 10.2) {
			$this->markTestSkipped('Need enough of the calendar day elapsed to build a >10h completed block ending in the past.');
		}

		// End in the past: future ends are clipped to "now" for §3 totals.
		$end = (clone $now)->modify('-5 minutes');
		$start = (clone $end)->modify('-10 hours -12 minutes');
		$heavy = new TimeEntry();
		$heavy->setId(99);
		$heavy->setUserId($userId);
		$heavy->setStatus(TimeEntry::STATUS_COMPLETED);
		$heavy->setStartTime($start);
		$heavy->setEndTime($end);
		$heavy->setBreaks(json_encode([]));

		$this->timeEntryMapper->method('findOverlapping')->willReturn([$heavy]);
		$this->timeEntryMapper->expects($this->once())
			->method('getTotalHoursByUserAndDateRange')
			->willReturn(240.0);

		$issues = $this->service->checkComplianceBeforeClockIn($userId);

		$this->assertNotEmpty($issues, 'Should detect daily hours limit exceeded');
		$this->assertCount(1, $issues);
		$this->assertEquals(ComplianceViolation::TYPE_DAILY_HOURS_LIMIT_EXCEEDED, $issues[0]['type']);
		$this->assertEquals(ComplianceViolation::SEVERITY_ERROR, $issues[0]['severity']);
	}

	/**
	 * Test that checkComplianceAfterClockOut detects missing 30-minute break
	 */
	public function testCheckComplianceAfterClockOutMissing30MinBreak(): void
	{
		$userId = 'testuser';
		$timeEntry = new TimeEntry();
		$timeEntry->setId(123);
		$timeEntry->setUserId($userId);
		$timeEntry->setStartTime(new \DateTime('2024-01-15 10:15:00')); // total 6h45m
		$timeEntry->setEndTime(new \DateTime('2024-01-15 17:00:00'));
		$timeEntry->setBreaks(json_encode([[
			'start' => '2024-01-15T13:00:00+00:00',
			'end' => '2024-01-15T13:15:00+00:00',
		]]));
		$timeEntry->setStatus(TimeEntry::STATUS_COMPLETED);
		$timeEntry->setIsManualEntry(false);
		$timeEntry->setCreatedAt(new \DateTime());
		$timeEntry->setUpdatedAt(new \DateTime());

		// Mock violation creation
		$violation = new ComplianceViolation();
		$violation->setId(456);
		$this->violationMapper->expects($this->once())
			->method('createViolation')
			->with(
				$userId,
				ComplianceViolation::TYPE_MISSING_BREAK,
				$this->stringContains('30-minute break'),
				$this->isInstanceOf(\DateTime::class),
				123,
				ComplianceViolation::SEVERITY_ERROR
			)
			->willReturn($violation);

		// Mock notification
		$this->notificationService->expects($this->once())
			->method('notifyComplianceViolation')
			->with($userId, $this->isType('array'));

		$this->service->checkComplianceAfterClockOut($timeEntry);
	}

	/**
	 * Test that checkComplianceAfterClockOut detects missing 45-minute break
	 */
	public function testCheckComplianceAfterClockOutMissing45MinBreak(): void
	{
		$userId = 'testuser';
		$timeEntry = new TimeEntry();
		$timeEntry->setId(123);
		$timeEntry->setUserId($userId);
		$timeEntry->setStartTime(new \DateTime('2024-01-15 07:00:00')); // total 10h
		$timeEntry->setEndTime(new \DateTime('2024-01-15 17:00:00'));
		$timeEntry->setBreaks(json_encode([[
			'start' => '2024-01-15T12:00:00+00:00',
			'end' => '2024-01-15T12:30:00+00:00',
		]]));
		$timeEntry->setStatus(TimeEntry::STATUS_COMPLETED);
		$timeEntry->setIsManualEntry(false);
		$timeEntry->setCreatedAt(new \DateTime());
		$timeEntry->setUpdatedAt(new \DateTime());

		// Mock violation creation
		$violation = new ComplianceViolation();
		$violation->setId(456);
		$this->violationMapper->expects($this->once())
			->method('createViolation')
			->with(
				$userId,
				ComplianceViolation::TYPE_MISSING_BREAK,
				$this->stringContains('45-minute break'),
				$this->isInstanceOf(\DateTime::class),
				123,
				ComplianceViolation::SEVERITY_ERROR
			)
			->willReturn($violation);

		// Mock notification
		$this->notificationService->expects($this->once())
			->method('notifyComplianceViolation')
			->with($userId, $this->isType('array'));

		$this->service->checkComplianceAfterClockOut($timeEntry);
	}

	/**
	 * Test that checkComplianceAfterClockOut detects excessive working hours
	 */
	public function testCheckComplianceAfterClockOutExcessiveHours(): void
	{
		$userId = 'testuser';
		$timeEntry = new TimeEntry();
		$timeEntry->setId(123);
		$timeEntry->setUserId($userId);
		$timeEntry->setStartTime(new \DateTime('2024-01-15 05:00:00')); // total 12h
		$timeEntry->setEndTime(new \DateTime('2024-01-15 17:00:00'));
		$timeEntry->setBreaks(json_encode([[
			'start' => '2024-01-15T12:00:00+00:00',
			'end' => '2024-01-15T13:00:00+00:00',
		]]));
		$timeEntry->setStatus(TimeEntry::STATUS_COMPLETED);
		$timeEntry->setIsManualEntry(false);
		$timeEntry->setCreatedAt(new \DateTime());
		$timeEntry->setUpdatedAt(new \DateTime());

		$this->timeEntryMapper->method('findOverlapping')->willReturn([$timeEntry]);
		$this->violationMapper->method('findByDateRange')->willReturn([]);

		// Mock violation creation (excessive hours + night work info)
		$violation = new ComplianceViolation();
		$violation->setId(456);
		$this->violationMapper->expects($this->exactly(2))
			->method('createViolation')
			->withConsecutive(
				[
					$userId,
					ComplianceViolation::TYPE_EXCESSIVE_WORKING_HOURS,
					$this->stringContains('Working hours on'),
					$this->isInstanceOf(\DateTime::class),
					123,
					ComplianceViolation::SEVERITY_ERROR
				],
				[
					$userId,
					ComplianceViolation::TYPE_NIGHT_WORK,
					$this->stringContains('Night work detected'),
					$this->isInstanceOf(\DateTime::class),
					123,
					ComplianceViolation::SEVERITY_INFO
				]
			)
			->willReturnOnConsecutiveCalls($violation, $violation);

		// Mock notification
		$this->notificationService->expects($this->once())
			->method('notifyComplianceViolation')
			->with($userId, $this->isType('array'));

		$this->service->checkComplianceAfterClockOut($timeEntry);
	}

	/**
	 * Wachdienst: a single 22:00–08:00 row must not trigger "excessive hours" when each calendar day is legal.
	 */
	public function testCheckComplianceAfterClockOutNoExcessiveHoursForOvernightShift(): void
	{
		$userId = 'guard';
		$tz = new \DateTimeZone('Europe/Berlin');
		$timeEntry = new TimeEntry();
		$timeEntry->setId(200);
		$timeEntry->setUserId($userId);
		$timeEntry->setStartTime(new \DateTime('2026-05-19 22:00:00', $tz));
		$timeEntry->setEndTime(new \DateTime('2026-05-20 08:00:00', $tz));
		$timeEntry->setBreaks(json_encode([]));
		$timeEntry->setStatus(TimeEntry::STATUS_COMPLETED);
		$timeEntry->setIsManualEntry(false);
		$timeEntry->setCreatedAt(new \DateTime());
		$timeEntry->setUpdatedAt(new \DateTime());

		$this->timeEntryMapper->method('findOverlapping')->willReturn([$timeEntry]);
		$this->violationMapper->method('findByDateRange')->willReturn([]);

		$calls = [];
		$this->violationMapper->method('createViolation')->willReturnCallback(function (...$args) use (&$calls): ComplianceViolation {
			$calls[] = $args;
			$v = new ComplianceViolation();
			$v->setId(count($calls));
			return $v;
		});

		$this->service->checkComplianceAfterClockOut($timeEntry);

		$excessive = array_values(array_filter(
			$calls,
			static fn (array $a): bool => $a[1] === ComplianceViolation::TYPE_EXCESSIVE_WORKING_HOURS
		));
		$this->assertSame([], $excessive, 'Legal overnight shift must not create excessive-hours violations.');
	}

	/**
	 * Test that checkComplianceAfterClockOut does not create violations when compliant
	 */
	public function testCheckComplianceAfterClockOutCompliant(): void
	{
		$userId = 'testuser';
		$timeEntry = new TimeEntry();
		$timeEntry->setId(123);
		$timeEntry->setUserId($userId);
		$timeEntry->setStartTime(new \DateTime('2024-01-15 08:00:00')); // total 8h45m
		$timeEntry->setEndTime(new \DateTime('2024-01-15 16:45:00'));
		$timeEntry->setBreaks(json_encode([[
			'start' => '2024-01-15T12:00:00+00:00',
			'end' => '2024-01-15T12:45:00+00:00',
		]]));
		$timeEntry->setStatus(TimeEntry::STATUS_COMPLETED);
		$timeEntry->setIsManualEntry(false);
		$timeEntry->setCreatedAt(new \DateTime());
		$timeEntry->setUpdatedAt(new \DateTime());

		// Should not create any violations
		$this->violationMapper->expects($this->never())
			->method('createViolation');

		$this->notificationService->expects($this->never())
			->method('notifyComplianceViolation');

		$this->service->checkComplianceAfterClockOut($timeEntry);
	}

	/**
	 * Test German public holiday detection
	 */
	public function testIsGermanPublicHoliday(): void
	{
		$this->holidayCalendarService->method('isHolidayForState')->willReturnCallback(
			static function (string $state, \DateTime $date): bool {
				$key = $state . '|' . $date->format('Y-m-d');
				$map = [
					'BY|2024-01-01' => true,
					'BE|2024-01-01' => true,
					'BW|2024-01-01' => true,

					'BY|2024-12-25' => true,
					'BE|2024-12-25' => true,

					'BY|2024-01-15' => false,
					'BE|2024-01-15' => false,

					'BY|2024-01-06' => true,
					'BE|2024-01-06' => false,
				];

				return $map[$key] ?? false;
			}
		);

		// Test New Year's Day (should be holiday in all states)
		$newYear = new \DateTime('2024-01-01');
		$this->assertTrue($this->service->isGermanPublicHoliday($newYear, 'BY'));
		$this->assertTrue($this->service->isGermanPublicHoliday($newYear, 'BE'));
		$this->assertTrue($this->service->isGermanPublicHoliday($newYear, 'BW'));

		// Test Christmas Day (should be holiday in all states)
		$christmas = new \DateTime('2024-12-25');
		$this->assertTrue($this->service->isGermanPublicHoliday($christmas, 'BY'));
		$this->assertTrue($this->service->isGermanPublicHoliday($christmas, 'BE'));

		// Test regular workday (should not be holiday)
		$regularDay = new \DateTime('2024-01-15'); // Monday
		$this->assertFalse($this->service->isGermanPublicHoliday($regularDay, 'BY'));
		$this->assertFalse($this->service->isGermanPublicHoliday($regularDay, 'BE'));

		// Test state-specific holiday (e.g., Epiphany in Bavaria)
		$epiphany = new \DateTime('2024-01-06');
		$this->assertTrue($this->service->isGermanPublicHoliday($epiphany, 'BY'));
		$this->assertFalse($this->service->isGermanPublicHoliday($epiphany, 'BE')); // Not a holiday in Berlin
	}

	/**
	 * Test Sunday work detection through checkComplianceAfterClockOut
	 */
	public function testCheckComplianceAfterClockOutSundayWork(): void
	{
		$userId = 'testuser';
		$timeEntry = new TimeEntry();

		// Mock time entry on Sunday (compliant hours and breaks)
		$sundayStart = new \DateTime('2024-01-07 08:00:00'); // Sunday
		$sundayEnd = new \DateTime('2024-01-07 17:00:00'); // Sunday

		$timeEntry->setId(123);
		$timeEntry->setUserId($userId);
		$timeEntry->setStartTime($sundayStart);
		$timeEntry->setEndTime($sundayEnd);
		$timeEntry->setBreaks(json_encode([[
			'start' => '2024-01-07T12:00:00+00:00',
			'end' => '2024-01-07T12:45:00+00:00',
		]]));
		$timeEntry->setStatus(TimeEntry::STATUS_COMPLETED);
		$timeEntry->setIsManualEntry(false);
		$timeEntry->setCreatedAt(new \DateTime());
		$timeEntry->setUpdatedAt(new \DateTime());

		// Mock violation creation for Sunday work
		$violation = new ComplianceViolation();
		$violation->setId(456);
		$this->violationMapper->expects($this->once())
			->method('createViolation')
			->with(
				$userId,
				ComplianceViolation::TYPE_SUNDAY_WORK,
				$this->stringContains('Sunday'),
				$sundayStart,
				123,
				ComplianceViolation::SEVERITY_WARNING
			)
			->willReturn($violation);

		$this->service->checkComplianceAfterClockOut($timeEntry);
	}

	/**
	 * Saturday 22:00 → Sunday 02:00: Sunday work must be recorded even though the shift started on Saturday.
	 */
	public function testCheckComplianceAfterClockOutSundayWorkWhenShiftStartedSaturday(): void
	{
		$userId = 'testuser';
		$timeEntry = new TimeEntry();
		$timeEntry->setId(777);
		$timeEntry->setUserId($userId);
		$timeEntry->setStartTime(new \DateTime('2024-01-06 22:00:00'));
		$timeEntry->setEndTime(new \DateTime('2024-01-07 02:00:00'));
		$timeEntry->setBreaks(json_encode([]));
		$timeEntry->setStatus(TimeEntry::STATUS_COMPLETED);
		$timeEntry->setIsManualEntry(false);
		$timeEntry->setCreatedAt(new \DateTime());
		$timeEntry->setUpdatedAt(new \DateTime());

		$calls = [];
		$this->violationMapper->method('createViolation')->willReturnCallback(function (...$args) use (&$calls): ComplianceViolation {
			$calls[] = $args;
			$v = new ComplianceViolation();
			$v->setId(count($calls));
			return $v;
		});

		$this->service->checkComplianceAfterClockOut($timeEntry);

		$sunday = array_values(array_filter(
			$calls,
			static fn (array $a): bool => $a[1] === ComplianceViolation::TYPE_SUNDAY_WORK
		));
		$this->assertCount(1, $sunday, 'Expected exactly one Sunday-work violation for a Sat→Sun night span');
		$this->assertSame(
			'2024-01-07 00:00:00',
			$sunday[0][3]->format('Y-m-d H:i:s'),
			'Violation timestamp for Sunday should anchor to the start of the Sunday calendar day'
		);
	}

	/**
	 * Public holiday only on the second calendar day of a span must still create a holiday violation.
	 */
	public function testCheckComplianceAfterClockOutHolidayWorkSecondCalendarDayOnly(): void
	{
		$this->holidayCalendarService->method('isHolidayForUser')
			->willReturnCallback(static function (string $uid, \DateTime $day): bool {
				return $day->format('Y-m-d') === '2025-01-02';
			});

		$userId = 'testuser';
		$timeEntry = new TimeEntry();
		$timeEntry->setId(778);
		$timeEntry->setUserId($userId);
		$timeEntry->setStartTime(new \DateTime('2025-01-01 20:00:00'));
		$timeEntry->setEndTime(new \DateTime('2025-01-02 04:00:00'));
		$timeEntry->setBreaks(json_encode([]));
		$timeEntry->setStatus(TimeEntry::STATUS_COMPLETED);
		$timeEntry->setIsManualEntry(false);
		$timeEntry->setCreatedAt(new \DateTime());
		$timeEntry->setUpdatedAt(new \DateTime());

		$calls = [];
		$this->violationMapper->method('createViolation')->willReturnCallback(function (...$args) use (&$calls): ComplianceViolation {
			$calls[] = $args;
			$v = new ComplianceViolation();
			$v->setId(count($calls));
			return $v;
		});

		$this->service->checkComplianceAfterClockOut($timeEntry);

		$holiday = array_values(array_filter(
			$calls,
			static fn (array $a): bool => $a[1] === ComplianceViolation::TYPE_HOLIDAY_WORK
		));
		$this->assertCount(1, $holiday, 'Expected exactly one public-holiday violation when only the second day is a holiday');
		$this->assertSame(
			'2025-01-02 00:00:00',
			$holiday[0][3]->format('Y-m-d H:i:s')
		);
	}

	/**
	 * Test night work detection through checkComplianceAfterClockOut
	 */
	public function testCheckComplianceAfterClockOutNightWork(): void
	{
		$userId = 'testuser';
		$timeEntry = new TimeEntry();

		// Mock time entry with night work (11 PM - 2 AM)
		$nightStart = new \DateTime('2024-01-15 23:00:00');
		$nightEnd = new \DateTime('2024-01-16 02:00:00');

		$timeEntry->setId(123);
		$timeEntry->setUserId($userId);
		$timeEntry->setStartTime($nightStart);
		$timeEntry->setEndTime($nightEnd);
		$timeEntry->setBreaks(json_encode([]));
		$timeEntry->setStatus(TimeEntry::STATUS_COMPLETED);
		$timeEntry->setIsManualEntry(false);
		$timeEntry->setCreatedAt(new \DateTime());
		$timeEntry->setUpdatedAt(new \DateTime());

		// Mock violation creation for night work
		$violation = new ComplianceViolation();
		$violation->setId(456);
		$this->violationMapper->expects($this->once())
			->method('createViolation')
			->with(
				$userId,
				ComplianceViolation::TYPE_NIGHT_WORK,
				$this->stringContains('Night work'),
				$nightEnd,
				123,
				ComplianceViolation::SEVERITY_INFO
			)
			->willReturn($violation);

		$this->service->checkComplianceAfterClockOut($timeEntry);
	}

	/**
	 * Regression test for the production ValueError observed on /api/clock/out:
	 *   "The arguments array must contain 1 items, 0 given"
	 *
	 * Root cause: night-work formatting used `sprintf($l10n->t('Night work detected: %.2f …'), $value)`
	 * which calls `t()` without parameters, leaving the L10NString to invoke
	 * `vsprintf($text, [])` on first cast to string. PHP 8 throws ValueError there.
	 *
	 * This test pins down the ARCHITECTURAL contract: any translation string with a
	 * placeholder must receive its values via the second argument of `t()` so the
	 * L10NString carries them into its internal vsprintf. If anyone reintroduces the
	 * outer-sprintf pattern, the captured `parameters` will be empty here and this
	 * assertion fails — long before production runs into the L10NString cast.
	 */
	public function testCheckNightWorkPassesPlaceholderValueAsTranslationParameter(): void
	{
		$userId = 'testuser';

		// Spans 22:00 → 03:00 next day (5h, 4h of which fall inside 23:00–06:00).
		$timeEntry = new TimeEntry();
		$timeEntry->setId(987);
		$timeEntry->setUserId($userId);
		$timeEntry->setStartTime(new \DateTime('2026-05-02 22:00:00'));
		$timeEntry->setEndTime(new \DateTime('2026-05-03 03:00:00'));
		$timeEntry->setBreaks(json_encode([]));
		$timeEntry->setStatus(TimeEntry::STATUS_COMPLETED);
		$timeEntry->setIsManualEntry(false);
		$timeEntry->setCreatedAt(new \DateTime());
		$timeEntry->setUpdatedAt(new \DateTime());

		// Capture every t() invocation so we can assert on the night-work call shape.
		// Also exercise vsprintf so a missing-parameter regression surfaces here too.
		$observed = [];
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')
			->willReturnCallback(function (string $text, array $parameters = []) use (&$observed): string {
				$observed[] = ['text' => $text, 'parameters' => $parameters];
				return $parameters === [] ? $text : vsprintf($text, $parameters);
			});

		// Rebuild the service so it picks up our instrumented IL10N.
		$tz = $this->buildTimeZoneService($this->config);
		$service = new ComplianceService(
			$this->timeEntryMapper,
			$this->violationMapper,
			$this->workingTimeModelMapper,
			$this->userWorkingTimeModelMapper,
			$this->userManager,
			$this->l10n,
			$this->notificationService,
			$this->holidayCalendarService,
			$this->config,
			$this->permissionService,
			$tz,
			new \OCA\ArbeitszeitCheck\Service\DailyWorkingHoursCalculator($this->timeEntryMapper, $tz),
		);

		$violation = new ComplianceViolation();
		$violation->setId(1);
		$this->violationMapper->expects($this->atLeastOnce())
			->method('createViolation')
			->willReturn($violation);

		$service->checkComplianceAfterClockOut($timeEntry);

		$nightCalls = array_values(array_filter(
			$observed,
			static fn (array $c): bool => str_starts_with($c['text'], 'Night work detected')
		));

		$this->assertNotEmpty($nightCalls, 'Expected at least one night-work translation lookup');
		foreach ($nightCalls as $call) {
			$this->assertNotEmpty(
				$call['parameters'],
				'Night-work translation must be invoked with parameters; outer sprintf() on a parameterless t() breaks the L10NString pipeline.'
			);
			$this->assertCount(1, $call['parameters'], 'Night-work translation expects exactly one positional parameter');
			$this->assertGreaterThan(
				0.0,
				(float)$call['parameters'][0],
				'Computed night hours for a 22:00→03:00 shift must be > 0'
			);
		}
	}

	/**
	 * Regression test for the early-morning shift bug:
	 *   A shift entirely inside the previous night (e.g. 02:00–04:00) belongs to the
	 *   night window that opened at 23:00 the day BEFORE. The previous implementation
	 *   only considered the night window starting on the shift's own date and thus
	 *   reported 0 hours of night work, suppressing the violation entirely.
	 */
	public function testCheckNightWorkDetectsEarlyMorningShiftFromPreviousNightWindow(): void
	{
		$userId = 'testuser';

		$timeEntry = new TimeEntry();
		$timeEntry->setId(555);
		$timeEntry->setUserId($userId);
		// Use a weekday so Sunday-work detection does not add a second violation.
		$timeEntry->setStartTime(new \DateTime('2026-05-06 02:00:00'));
		$timeEntry->setEndTime(new \DateTime('2026-05-06 04:00:00'));
		$timeEntry->setBreaks(json_encode([]));
		$timeEntry->setStatus(TimeEntry::STATUS_COMPLETED);
		$timeEntry->setIsManualEntry(false);
		$timeEntry->setCreatedAt(new \DateTime());
		$timeEntry->setUpdatedAt(new \DateTime());

		$violation = new ComplianceViolation();
		$violation->setId(456);

		$this->violationMapper->expects($this->once())
			->method('createViolation')
			->with(
				$userId,
				ComplianceViolation::TYPE_NIGHT_WORK,
				$this->stringContains('Night work'),
				$this->isInstanceOf(\DateTime::class),
				555,
				ComplianceViolation::SEVERITY_INFO
			)
			->willReturn($violation);

		$this->service->checkComplianceAfterClockOut($timeEntry);
	}

	/**
	 * Boundary safeguard: a shift that ends at exactly 23:00 must NOT generate a
	 * "Night work detected: 0.00 hours" violation. The old hour-based heuristic
	 * triggered on `endHour >= 23` and produced misleading zero-hour entries.
	 */
	public function testCheckNightWorkSkipsZeroOverlapBoundaryShift(): void
	{
		$userId = 'testuser';

		$timeEntry = new TimeEntry();
		$timeEntry->setId(444);
		$timeEntry->setUserId($userId);
		$timeEntry->setStartTime(new \DateTime('2026-05-02 14:00:00'));
		$timeEntry->setEndTime(new \DateTime('2026-05-02 23:00:00'));
		$timeEntry->setBreaks(json_encode([[
			'start' => '2026-05-02T18:00:00+00:00',
			'end'   => '2026-05-02T18:45:00+00:00',
		]]));
		$timeEntry->setStatus(TimeEntry::STATUS_COMPLETED);
		$timeEntry->setIsManualEntry(false);
		$timeEntry->setCreatedAt(new \DateTime());
		$timeEntry->setUpdatedAt(new \DateTime());

		// No night-work violation should be created for a shift that touches but does
		// not enter the 23:00–06:00 window. Other checks may still create violations,
		// so we filter rather than asserting "never".
		$calls = [];
		$this->violationMapper->method('createViolation')->willReturnCallback(function (...$args) use (&$calls): ComplianceViolation {
			$calls[] = $args;
			$violation = new ComplianceViolation();
			$violation->setId(count($calls));
			return $violation;
		});

		$this->service->checkComplianceAfterClockOut($timeEntry);

		$night = array_filter(
			$calls,
			static fn (array $a): bool => $a[1] === ComplianceViolation::TYPE_NIGHT_WORK
		);
		$this->assertSame(
			[],
			array_values($night),
			'A 14:00→23:00 shift must not produce a night-work violation'
		);
	}

	/**
	 * Resilience contract: a defect inside one compliance rule must NEVER prevent
	 * the remaining rules from running. We simulate a fatal Throwable from
	 * violationMapper::createViolation when called for the first violation type and
	 * assert that subsequent calls still happen.
	 */
	public function testCheckComplianceAfterClockOutContinuesWhenIndividualCheckFails(): void
	{
		$userId = 'testuser';

		// Sunday + night-spanning shift exceeds 10h with no breaks — guarantees that
		// at least three independent rules want to record violations:
		//   1) excessive working hours
		//   2) night work
		//   3) Sunday work
		$timeEntry = new TimeEntry();
		$timeEntry->setId(321);
		$timeEntry->setUserId($userId);
		$timeEntry->setStartTime(new \DateTime('2026-05-03 18:00:00')); // Sunday
		$timeEntry->setEndTime(new \DateTime('2026-05-04 06:00:00'));
		$timeEntry->setBreaks(json_encode([]));
		$timeEntry->setStatus(TimeEntry::STATUS_COMPLETED);
		$timeEntry->setIsManualEntry(false);
		$timeEntry->setCreatedAt(new \DateTime());
		$timeEntry->setUpdatedAt(new \DateTime());

		$callCount = 0;
		$this->violationMapper->method('createViolation')
			->willReturnCallback(function () use (&$callCount): ComplianceViolation {
				$callCount++;
				if ($callCount === 1) {
					// First check throws (e.g. simulated DB blip or a future bug).
					throw new \RuntimeException('Simulated downstream failure');
				}
				$violation = new ComplianceViolation();
				$violation->setId($callCount);
				return $violation;
			});

		// Must NOT propagate — clock-out callers depend on this method returning
		// cleanly even when an individual rule blows up.
		$this->service->checkComplianceAfterClockOut($timeEntry);

		$this->assertGreaterThan(
			1,
			$callCount,
			'Subsequent compliance rules must still run after one check fails'
		);
	}

	/**
	 * Test getComplianceStatus returns correct structure
	 */
	public function testGetComplianceStatus(): void
	{
		$userId = 'testuser';

		// Mock no violations (findByUser with false = unresolved only)
		$this->violationMapper->expects($this->once())
			->method('findByUser')
			->with($userId, false)
			->willReturn([]);

		$status = $this->service->getComplianceStatus($userId);

		$this->assertIsArray($status);
		$this->assertArrayHasKey('compliant', $status);
		$this->assertArrayHasKey('violation_count', $status);
		$this->assertArrayHasKey('critical_violations', $status);
		$this->assertArrayHasKey('warning_violations', $status);
		$this->assertArrayHasKey('info_violations', $status);
		$this->assertArrayHasKey('last_check', $status);
		$this->assertTrue($status['compliant'], 'Should be compliant when no violations');
		$this->assertEquals(0, $status['violation_count']);
		$this->assertEquals(0, $status['critical_violations']);
	}

	/**
	 * Test getComplianceStatus detects non-compliance
	 */
	public function testGetComplianceStatusNonCompliant(): void
	{
		$userId = 'testuser';

		// Mock violations
		$violation = new ComplianceViolation();
		$violation->setId(1);
		$violation->setUserId($userId);
		$violation->setViolationType(ComplianceViolation::TYPE_MISSING_BREAK);
		$violation->setSeverity(ComplianceViolation::SEVERITY_ERROR);

		$this->violationMapper->expects($this->once())
			->method('findByUser')
			->with($userId, false)
			->willReturn([$violation]);

		$status = $this->service->getComplianceStatus($userId);

		$this->assertFalse($status['compliant'], 'Should be non-compliant when violations exist');
		$this->assertEquals(1, $status['violation_count']);
		$this->assertEquals(1, $status['critical_violations']);
		$this->assertEquals(0, $status['warning_violations']);
		$this->assertEquals(0, $status['info_violations']);
	}

	/**
	 * Test generateComplianceReport returns correct structure
	 */
	public function testGenerateComplianceReport(): void
	{
		$startDate = new \DateTime('2024-01-01');
		$endDate = new \DateTime('2024-01-31');
		$userId = 'testuser';

		// Mock violations
		$violation1 = new ComplianceViolation();
		$violation1->setId(1);
		$violation1->setUserId($userId);
		$violation1->setViolationType(ComplianceViolation::TYPE_MISSING_BREAK);
		$violation1->setSeverity(ComplianceViolation::SEVERITY_ERROR);

		$violation2 = new ComplianceViolation();
		$violation2->setId(2);
		$violation2->setUserId($userId);
		$violation2->setViolationType(ComplianceViolation::TYPE_EXCESSIVE_WORKING_HOURS);
		$violation2->setSeverity(ComplianceViolation::SEVERITY_WARNING);

		$this->violationMapper->expects($this->once())
			->method('findByDateRange')
			->with($startDate, $endDate, $userId)
			->willReturn([$violation1, $violation2]);

		$report = $this->service->generateComplianceReport($startDate, $endDate, $userId);

		$this->assertIsArray($report);
		$this->assertArrayHasKey('period', $report);
		$this->assertArrayHasKey('total_violations', $report);
		$this->assertArrayHasKey('violations_by_type', $report);
		$this->assertArrayHasKey('violations_by_severity', $report);
		$this->assertArrayHasKey('violations_by_user', $report);
		$this->assertEquals(2, $report['total_violations']);
		$this->assertEquals(1, $report['violations_by_type'][ComplianceViolation::TYPE_MISSING_BREAK]);
		$this->assertEquals(1, $report['violations_by_type'][ComplianceViolation::TYPE_EXCESSIVE_WORKING_HOURS]);
		$this->assertEquals(1, $report['violations_by_severity'][ComplianceViolation::SEVERITY_ERROR]);
		$this->assertEquals(1, $report['violations_by_severity'][ComplianceViolation::SEVERITY_WARNING]);
	}

	public function testBlockingIssuesForCompletedEntryFlagsMissingThirtyMinuteBreak(): void
	{
		$timeEntry = new TimeEntry();
		$timeEntry->setUserId('testuser');
		$timeEntry->setStartTime(new \DateTime('2026-05-02 08:00:00'));
		$timeEntry->setEndTime(new \DateTime('2026-05-02 15:00:00'));
		$timeEntry->setBreaks(json_encode([]));
		$timeEntry->setStatus(TimeEntry::STATUS_COMPLETED);
		$timeEntry->setIsManualEntry(true);
		$timeEntry->setCreatedAt(new \DateTime());
		$timeEntry->setUpdatedAt(new \DateTime());

		$issues = $this->service->blockingIssuesForCompletedEntry($timeEntry);

		$this->assertNotEmpty($issues);
		$this->assertStringContainsString('30-minute', $issues[0]);
	}

	public function testBlockingIssuesForCompletedEntryAllowsCompliantBreaks(): void
	{
		$timeEntry = new TimeEntry();
		$timeEntry->setUserId('testuser');
		$timeEntry->setStartTime(new \DateTime('2026-05-02 08:00:00'));
		$timeEntry->setEndTime(new \DateTime('2026-05-02 15:00:00'));
		$timeEntry->setBreakStartTime(new \DateTime('2026-05-02 12:00:00'));
		$timeEntry->setBreakEndTime(new \DateTime('2026-05-02 12:35:00'));
		$timeEntry->setStatus(TimeEntry::STATUS_COMPLETED);
		$timeEntry->setIsManualEntry(true);
		$timeEntry->setCreatedAt(new \DateTime());
		$timeEntry->setUpdatedAt(new \DateTime());

		$this->assertSame([], $this->service->blockingIssuesForCompletedEntry($timeEntry));
	}
}
