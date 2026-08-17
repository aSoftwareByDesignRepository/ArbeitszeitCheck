<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service;

use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Db\TimeEntry;
use OCA\ArbeitszeitCheck\Db\TimeEntryMapper;
use OCA\ArbeitszeitCheck\Service\ProjectCheckLaborTimeSyncService;
use OCA\ArbeitszeitCheck\Service\TimeZoneService;
use OCP\App\IAppManager;
use OCP\IConfig;
use OCP\IDateTimeZone;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Billing sync must use instance-level ProjectCheck install, not the current
 * user (cron / group-restricted admins have no session user).
 */
final class ProjectCheckLaborTimeSyncServiceTest extends TestCase
{
	private function timeZoneService(): TimeZoneService
	{
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static fn (string $app, string $key, $default = '') => $default
		);
		$dateTimeZone = $this->createMock(IDateTimeZone::class);
		$dateTimeZone->method('getTimeZone')->willReturn(new \DateTimeZone('Europe/Berlin'));
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn(null);
		return new TimeZoneService($config, $dateTimeZone, $userSession, new NullLogger());
	}

	public function testSyncNoopsWhenProjectCheckIsNotInstalledEvenIfCurrentUserWouldHaveIt(): void
	{
		$appManager = $this->createMock(IAppManager::class);
		$appManager->expects($this->once())
			->method('isInstalled')
			->with(Constants::APP_ID_PROJECTCHECK)
			->willReturn(false);
		$appManager->expects($this->never())->method('isEnabledForUser');

		$timeEntryMapper = $this->createMock(TimeEntryMapper::class);
		$timeEntryMapper->expects($this->never())->method('update');

		$service = new ProjectCheckLaborTimeSyncService(
			$appManager,
			$timeEntryMapper,
			$this->timeZoneService(),
			$this->createMock(IConfig::class),
			$this->createMock(LoggerInterface::class),
			new \stdClass(),
		);

		$entry = $this->createMock(TimeEntry::class);
		$result = $service->syncFromTimeEntry($entry, 'admin');
		$this->assertTrue($result['success']);
		$this->assertNull($result['projectCheckTimeEntryId']);
	}

	public function testDeleteNoopsWhenProjectCheckIsNotInstalled(): void
	{
		$appManager = $this->createMock(IAppManager::class);
		$appManager->expects($this->once())
			->method('isInstalled')
			->with(Constants::APP_ID_PROJECTCHECK)
			->willReturn(false);
		$appManager->expects($this->never())->method('isEnabledForUser');

		$service = new ProjectCheckLaborTimeSyncService(
			$appManager,
			$this->createMock(TimeEntryMapper::class),
			$this->timeZoneService(),
			$this->createMock(IConfig::class),
			$this->createMock(LoggerInterface::class),
			new \stdClass(),
		);

		$service->onTimeEntryDeleted([
			'projectCheckTimeEntryId' => 9,
			'userId' => 'bob',
		], 'admin');
	}

	public function testSyncUsesInstalledCheckNotEnabledForUserWhenAppIsPresent(): void
	{
		$appManager = $this->createMock(IAppManager::class);
		$appManager->expects($this->once())
			->method('isInstalled')
			->with(Constants::APP_ID_PROJECTCHECK)
			->willReturn(true);
		$appManager->expects($this->never())->method('isEnabledForUser');

		$service = new ProjectCheckLaborTimeSyncService(
			$appManager,
			$this->createMock(TimeEntryMapper::class),
			$this->timeZoneService(),
			$this->createMock(IConfig::class),
			$this->createMock(LoggerInterface::class),
			null,
		);

		$entry = $this->createMock(TimeEntry::class);
		$result = $service->syncFromTimeEntry($entry, 'cron');
		$this->assertTrue($result['success']);
	}
}
