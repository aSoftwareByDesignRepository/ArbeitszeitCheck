<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\BackgroundJob;

use OCA\ArbeitszeitCheck\BackgroundJob\OvertimeTrafficLightJob;
use OCA\ArbeitszeitCheck\Service\NotificationService;
use OCA\ArbeitszeitCheck\Service\OvertimeDisplayService;
use OCA\ArbeitszeitCheck\Service\OvertimeNotificationMailService;
use OCA\ArbeitszeitCheck\Service\OvertimeTrafficLightService;
use OCA\ArbeitszeitCheck\Service\PermissionService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;

class OvertimeTrafficLightJobTest extends TestCase
{
	public function testRunReturnsEarlyWhenTrafficLightDisabled(): void
	{
		$trafficLight = $this->createMock(OvertimeTrafficLightService::class);
		$trafficLight->expects($this->once())->method('isEnabled')->willReturn(false);

		$userManager = $this->createMock(IUserManager::class);
		$userManager->expects($this->never())->method('callForAllUsers');

		$job = new OvertimeTrafficLightJob(
			$this->createMock(ITimeFactory::class),
			$this->createMock(OvertimeDisplayService::class),
			$trafficLight,
			$this->createMock(NotificationService::class),
			$this->createMock(OvertimeNotificationMailService::class),
			$userManager,
			$this->createMock(IConfig::class),
			$this->createMock(PermissionService::class),
			$this->createMock(LoggerInterface::class),
		);

		$ref = new ReflectionClass($job);
		$method = $ref->getMethod('run');
		$method->setAccessible(true);
		$method->invoke($job, null);
	}

	public function testRunScansUsersWhenEnabled(): void
	{
		$trafficLight = $this->createMock(OvertimeTrafficLightService::class);
		$trafficLight->expects($this->once())->method('isEnabled')->willReturn(true);
		$trafficLight->method('getThresholds')->willReturn([
			'yellow' => 10.0,
			'red' => 20.0,
		]);

		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturn('');

		$userManager = $this->createMock(IUserManager::class);
		$userManager->expects($this->once())->method('callForAllUsers');

		$job = new OvertimeTrafficLightJob(
			$this->createMock(ITimeFactory::class),
			$this->createMock(OvertimeDisplayService::class),
			$trafficLight,
			$this->createMock(NotificationService::class),
			$this->createMock(OvertimeNotificationMailService::class),
			$userManager,
			$config,
			$this->createMock(PermissionService::class),
			$this->createMock(LoggerInterface::class),
		);

		$ref = new ReflectionClass($job);
		$method = $ref->getMethod('run');
		$method->setAccessible(true);
		$method->invoke($job, null);
	}
}
