<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service\Kiosk;

use OCA\ArbeitszeitCheck\Db\AuditLogMapper;
use OCA\ArbeitszeitCheck\Db\KioskCredMapper;
use OCA\ArbeitszeitCheck\Db\KioskEnrollment;
use OCA\ArbeitszeitCheck\Db\KioskEnrollmentMapper;
use OCA\ArbeitszeitCheck\Db\KioskSessionMapper;
use OCA\ArbeitszeitCheck\Db\KioskTerminal;
use OCA\ArbeitszeitCheck\Service\Kiosk\KioskAuthService;
use OCA\ArbeitszeitCheck\Service\Kiosk\KioskCredentialService;
use OCA\ArbeitszeitCheck\Service\Kiosk\KioskException;
use OCA\ArbeitszeitCheck\Service\Kiosk\KioskSettingsService;
use OCA\ArbeitszeitCheck\Service\PermissionService;
use OCA\ArbeitszeitCheck\Service\TimeCaptureMethodService;
use OCA\ArbeitszeitCheck\Service\TimeTrackingService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IUserManager;
use OCP\Lock\ILockingProvider;
use PHPUnit\Framework\TestCase;

class KioskAuthServiceEnrollmentGateTest extends TestCase
{
	public function testIdentifyRfidThrowsWhenEnrollmentActiveBeforeCredentialLookup(): void
	{
		$enrollmentMapper = $this->createMock(KioskEnrollmentMapper::class);
		$enrollment = new KioskEnrollment();
		$enrollment->setTerminalId('term-1');
		$enrollment->setUserId('alice');
		$enrollmentMapper->expects($this->once())
			->method('findActiveByTerminalId')
			->willReturn($enrollment);

		$sessionMapper = $this->createMock(KioskSessionMapper::class);
		$sessionMapper->expects($this->once())->method('deleteExpiredForTerminal');
		$sessionMapper->expects($this->never())->method('insert');

		$credService = $this->createMock(KioskCredentialService::class);
		$credService->expects($this->never())->method('findCredByRfidUid');

		$locking = $this->createMock(ILockingProvider::class);
		$locking->expects($this->never())->method('acquireLock');

		$time = $this->createMock(ITimeFactory::class);
		$time->method('getDateTime')->willReturn(new \DateTime('2026-07-18 12:00:00'));

		$service = new KioskAuthService(
			$credService,
			$this->createMock(KioskCredMapper::class),
			$enrollmentMapper,
			$sessionMapper,
			$this->createMock(KioskSettingsService::class),
			$this->createMock(PermissionService::class),
			$this->createMock(TimeCaptureMethodService::class),
			$this->createMock(TimeTrackingService::class),
			$this->createMock(IUserManager::class),
			$this->createMock(AuditLogMapper::class),
			$time,
			$locking,
		);

		$terminal = new KioskTerminal();
		$terminal->setTerminalId('term-1');

		$this->expectException(KioskException::class);
		$this->expectExceptionMessage('ENROLLMENT_ACTIVE');
		$service->identify($terminal, 'rfid', '04AABBCCDD', null, null);
	}
}
