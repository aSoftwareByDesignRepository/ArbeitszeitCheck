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
use OCA\ArbeitszeitCheck\Service\TimeTrackingService;
use OCA\ArbeitszeitCheck\Service\ProjectCheckIntegrationService;
use OCA\ArbeitszeitCheck\Service\MonthClosureGuard;
use OCA\ArbeitszeitCheck\Db\TimeEntry;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\Lock\ILockingProvider;
use PHPUnit\Framework\TestCase;

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
			$lockingProvider
		);
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
			->method('projectExists')
			->with($projectId)
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
		$projectId = 'invalid123';

		// Mock that user is not clocked in
		$this->timeEntryMapper->expects($this->once())
			->method('findActiveByUser')
			->with($userId)
			->willReturn(null);

		// Mock that user is not on break
		$this->timeEntryMapper->expects($this->once())
			->method('findOnBreakByUser')
			->with($userId)
			->willReturn(null);

		// Mock project validation - project doesn't exist
		$this->projectCheckService->expects($this->once())
			->method('projectExists')
			->with($projectId)
			->willReturn(false);

		$this->l10n->expects($this->once())
			->method('t')
			->with('Selected project does not exist')
			->willReturn('Selected project does not exist');

		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Selected project does not exist');

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
			->method('findByUserAndDateRange')
			->willReturn([]);

		$result = $this->service->getStatus($userId);

		$this->assertEquals('active', $result['status']);
		$this->assertEquals(0.0, $result['working_today_hours']);
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
			->method('findByUserAndDateRange')
			->willReturn([]);

		$result = $this->service->getStatus($userId);

		$this->assertEquals('clocked_out', $result['status']);
		$this->assertNull($result['current_entry']);
		$this->assertEquals(0.0, $result['working_today_hours']);
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
			->with($userId)
			->willReturn($pausedEntry);

		$this->timeEntryMapper->expects($this->once())
			->method('getTotalHoursByUserAndDateRange')
			->willReturn(0.0);

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

		// already worked max hours in completed entries today
		$this->timeEntryMapper->method('getTotalHoursByUserAndDateRange')->willReturn(10.0);

		$this->l10n->method('t')->willReturnCallback(static fn ($s) => $s);

		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Maximum daily working hours');

		$this->service->clockIn($userId);
	}
}