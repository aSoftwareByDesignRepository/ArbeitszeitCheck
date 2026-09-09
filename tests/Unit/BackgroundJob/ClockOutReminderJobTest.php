<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\BackgroundJob;

use OCA\ArbeitszeitCheck\BackgroundJob\ClockOutReminderJob;
use OCA\ArbeitszeitCheck\Db\TimeEntryMapper;
use OCA\ArbeitszeitCheck\Db\UserSettingsMapper;
use OCA\ArbeitszeitCheck\Service\NotificationService;
use OCA\ArbeitszeitCheck\Service\PermissionService;
use OCA\ArbeitszeitCheck\Service\TimeZoneService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\IDateTimeZone;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ReflectionClass;

class ClockOutReminderJobTest extends TestCase
{
	private function timeZoneService(): TimeZoneService
	{
		$tzConfig = $this->createMock(IConfig::class);
		$tzConfig->method('getAppValue')->willReturnCallback(static fn ($app, $key, $default) => match ($key) {
			'app_timezone' => 'UTC',
			default => $default,
		});
		$tzDateTime = $this->createMock(IDateTimeZone::class);
		$tzDateTime->method('getTimeZone')->willReturn(new \DateTimeZone('UTC'));
		$tzUserSession = $this->createMock(IUserSession::class);
		$tzUserSession->method('getUser')->willReturn(null);
		return new TimeZoneService($tzConfig, $tzDateTime, $tzUserSession, new NullLogger());
	}

	/** @return array{0:string,1:bool} timezone + whether inside business hours */
	private function pickTimezone(bool $wantBusinessHours): array
	{
		$candidates = [
			'UTC',
			'Europe/Berlin',
			'America/New_York',
			'America/Los_Angeles',
			'Pacific/Honolulu',
			'Asia/Tokyo',
			'Asia/Dhaka',
			'Pacific/Auckland',
			'Atlantic/Azores',
			'Pacific/Kiritimati',
			'America/Adak',
			'Asia/Kathmandu',
		];
		foreach ($candidates as $tz) {
			$hour = (int)(new \DateTimeImmutable('now', new \DateTimeZone($tz)))->format('G');
			$inside = $hour >= 6 && $hour < 22;
			if ($inside === $wantBusinessHours) {
				return [$tz, $inside];
			}
		}
		$this->markTestSkipped('No timezone places wall clock in desired business-hours window');
	}

	public function testRunReturnsOutsideBusinessHoursWithoutScanningUsers(): void
	{
		[$tz] = $this->pickTimezone(false);
		$previousTz = date_default_timezone_get();
		date_default_timezone_set($tz);
		try {
			$userManager = $this->createMock(IUserManager::class);
			$userManager->expects($this->never())->method('callForAllUsers');

			$job = new ClockOutReminderJob(
				$this->createMock(ITimeFactory::class),
				$this->createMock(TimeEntryMapper::class),
				$this->createMock(UserSettingsMapper::class),
				$this->createMock(NotificationService::class),
				$userManager,
				$this->createMock(IConfig::class),
				$this->createMock(LoggerInterface::class),
				$this->createMock(PermissionService::class),
				$this->timeZoneService(),
			);

			$ref = new ReflectionClass($job);
			$method = $ref->getMethod('run');
			$method->setAccessible(true);
			$method->invoke($job, null);
			$this->assertTrue(true);
		} finally {
			date_default_timezone_set($previousTz);
		}
	}

	public function testRunInvokesDuringBusinessHours(): void
	{
		[$tz] = $this->pickTimezone(true);
		$previousTz = date_default_timezone_get();
		date_default_timezone_set($tz);
		try {
			$logger = $this->createMock(LoggerInterface::class);
			$logger->expects($this->atLeastOnce())->method('info');

			$userManager = $this->createMock(IUserManager::class);
			$userManager->expects($this->once())->method('callForAllUsers');

			$job = new ClockOutReminderJob(
				$this->createMock(ITimeFactory::class),
				$this->createMock(TimeEntryMapper::class),
				$this->createMock(UserSettingsMapper::class),
				$this->createMock(NotificationService::class),
				$userManager,
				$this->createMock(IConfig::class),
				$logger,
				$this->createMock(PermissionService::class),
				$this->timeZoneService(),
			);

			$ref = new ReflectionClass($job);
			$method = $ref->getMethod('run');
			$method->setAccessible(true);
			$method->invoke($job, null);
		} finally {
			date_default_timezone_set($previousTz);
		}
	}
}
