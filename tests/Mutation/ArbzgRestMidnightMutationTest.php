<?php

declare(strict_types=1);

/**
 * Mutation-style source + behaviour contracts for ArbZG §5 rest vs midnight.
 */

namespace OCA\ArbeitszeitCheck\Tests\Mutation;

use OCA\ArbeitszeitCheck\Db\TimeEntry;
use OCA\ArbeitszeitCheck\Db\TimeEntryMapper;
use OCA\ArbeitszeitCheck\Db\ComplianceViolationMapper;
use OCA\ArbeitszeitCheck\Db\WorkingTimeModelMapper;
use OCA\ArbeitszeitCheck\Db\UserWorkingTimeModelMapper;
use OCA\ArbeitszeitCheck\Service\ComplianceService;
use OCA\ArbeitszeitCheck\Service\DailyWorkingHoursCalculator;
use OCA\ArbeitszeitCheck\Service\HolidayService;
use OCA\ArbeitszeitCheck\Service\NotificationService;
use OCA\ArbeitszeitCheck\Service\PermissionService;
use OCA\ArbeitszeitCheck\Service\TimeZoneService;
use OCA\ArbeitszeitCheck\Support\LaborLawProfileFactory;
use OCP\IConfig;
use OCP\IDateTimeZone;
use OCP\IL10N;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ArbzgRestMidnightMutationTest extends TestCase
{
	public function testStampPathSourceHasNoSameDayCalendarShortcut(): void
	{
		$src = file_get_contents(__DIR__ . '/../../lib/Service/ComplianceService.php');
		$this->assertNotFalse($src);
		$this->assertStringContainsString('always enforces elapsed rest', $src);
		$this->assertStringContainsString('isIntradayWorkInterruption', $src);
		$this->assertStringContainsString('Overnight Wachdienst ending after 00:00 must still enforce', $src);
		// Must not reintroduce the unsafe blanket same-day skip on stamp evaluation.
		$this->assertDoesNotMatchRegularExpression(
			'/function evaluateRestPeriodForClockIn.*?\$lastEndDay\s*===\s*\$nowDay/s',
			$src
		);
	}

	public function testOvernightMorningStartBlockedOnManualPath(): void
	{
		$service = $this->buildService();
		$tz = new \DateTimeZone('Europe/Berlin');
		$mapper = $this->createMock(TimeEntryMapper::class);

		$ref = new \ReflectionClass($service);
		$prop = $ref->getProperty('timeEntryMapper');
		$prop->setAccessible(true);
		$prop->setValue($service, $mapper);

		$last = new TimeEntry();
		$last->setId(1);
		$last->setUserId('n');
		$last->setStartTime(new \DateTime('2026-03-14 22:00:00', $tz));
		$last->setEndTime(new \DateTime('2026-03-15 06:00:00', $tz));
		$last->setStatus(TimeEntry::STATUS_COMPLETED);

		$start = new \DateTime('2026-03-15 10:00:00', $tz);
		$mapper->method('findLastCompletedBeforeTime')->willReturn($last);

		$result = $service->checkRestPeriodForStartTime('n', $start);
		$this->assertFalse($result['valid']);
	}

	public function testIntradaySplitStillAllowedOnManualPath(): void
	{
		$service = $this->buildService();
		$tz = new \DateTimeZone('Europe/Berlin');
		$mapper = $this->createMock(TimeEntryMapper::class);

		$ref = new \ReflectionClass($service);
		$prop = $ref->getProperty('timeEntryMapper');
		$prop->setAccessible(true);
		$prop->setValue($service, $mapper);

		$last = new TimeEntry();
		$last->setId(2);
		$last->setUserId('d');
		$last->setStartTime(new \DateTime('2026-03-15 09:00:00', $tz));
		$last->setEndTime(new \DateTime('2026-03-15 12:00:00', $tz));
		$last->setStatus(TimeEntry::STATUS_COMPLETED);

		$start = new \DateTime('2026-03-15 13:00:00', $tz);
		$mapper->method('findLastCompletedBeforeTime')->willReturn($last);

		$result = $service->checkRestPeriodForStartTime('d', $start);
		$this->assertTrue($result['valid']);
	}

	private function buildService(): ComplianceService
	{
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static fn (string $app, string $key, string $default = ''): string => $default
		);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(
			static function (string $text, $parameters = []) {
				$parameters = is_array($parameters) ? $parameters : [$parameters];
				return $parameters === [] ? $text : vsprintf($text, $parameters);
			}
		);
		$dateTimeZone = $this->createMock(IDateTimeZone::class);
		$dateTimeZone->method('getTimeZone')->willReturn(new \DateTimeZone('Europe/Berlin'));
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn(null);
		$tz = new TimeZoneService($config, $dateTimeZone, $userSession, new NullLogger());
		$mapper = $this->createMock(TimeEntryMapper::class);
		$permission = $this->createMock(PermissionService::class);
		$permission->method('isUserAllowedByAccessGroups')->willReturn(true);

		return new ComplianceService(
			$mapper,
			$this->createMock(ComplianceViolationMapper::class),
			$this->createMock(WorkingTimeModelMapper::class),
			$this->createMock(UserWorkingTimeModelMapper::class),
			$this->createMock(IUserManager::class),
			$l10n,
			$this->createMock(NotificationService::class),
			$this->createMock(HolidayService::class),
			$config,
			$permission,
			$tz,
			new DailyWorkingHoursCalculator($mapper, $tz),
			new LaborLawProfileFactory($config),
		);
	}
}
