<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service\Kiosk;

use OCA\ArbeitszeitCheck\Db\AuditLogMapper;
use OCA\ArbeitszeitCheck\Db\KioskCred;
use OCA\ArbeitszeitCheck\Db\KioskCredMapper;
use OCA\ArbeitszeitCheck\Db\KioskEnrollmentMapper;
use OCA\ArbeitszeitCheck\Db\KioskSessionMapper;
use OCA\ArbeitszeitCheck\Db\KioskTerminal;
use OCA\ArbeitszeitCheck\Exception\TimeCaptureForbiddenException;
use OCA\ArbeitszeitCheck\Service\Kiosk\KioskAuthService;
use OCA\ArbeitszeitCheck\Service\Kiosk\KioskCredentialService;
use OCA\ArbeitszeitCheck\Service\Kiosk\KioskException;
use OCA\ArbeitszeitCheck\Service\Kiosk\KioskSettingsService;
use OCA\ArbeitszeitCheck\Service\PermissionService;
use OCA\ArbeitszeitCheck\Service\TimeCaptureMethodService;
use OCA\ArbeitszeitCheck\Service\TimeTrackingService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Lock\ILockingProvider;
use PHPUnit\Framework\TestCase;

class KioskAuthServicePinIdentifyTest extends TestCase
{
	public function testIdentifyPinMapsClockStampingDisabledToKioskException(): void
	{
		$cred = new KioskCred();
		$cred->setId(7);
		$cred->setUserId('alice');
		$cred->setType('pin');

		$credMapper = $this->createMock(KioskCredMapper::class);
		$credMapper->method('findByUserAndType')->with('alice', 'pin')->willReturn($cred);

		$credService = $this->createMock(KioskCredentialService::class);
		$credService->method('isLocked')->willReturn(false);
		$credService->method('verifyPin')->willReturn(true);
		$credService->expects($this->once())->method('assertUserKioskAllowed')->with('alice');

		$permission = $this->createMock(PermissionService::class);
		$permission->method('isUserAllowedByAccessGroups')->willReturn(true);

		$timeCapture = $this->createMock(TimeCaptureMethodService::class);
		$timeCapture->method('assertClockStampingAllowed')
			->willThrowException(new TimeCaptureForbiddenException(
				'stamping off',
				TimeCaptureForbiddenException::CODE_CLOCK_STAMPING_DISABLED,
			));

		$sessionMapper = $this->createMock(KioskSessionMapper::class);
		$sessionMapper->expects($this->once())->method('deleteExpiredForTerminal');
		$sessionMapper->expects($this->never())->method('deleteUnusedForTerminal');
		$sessionMapper->expects($this->never())->method('insert');

		$time = $this->createMock(ITimeFactory::class);
		$time->method('getDateTime')->willReturn(new \DateTime('2026-07-20 12:00:00'));

		$service = new KioskAuthService(
			$credService,
			$credMapper,
			$this->createMock(KioskEnrollmentMapper::class),
			$sessionMapper,
			$this->createMock(KioskSettingsService::class),
			$permission,
			$timeCapture,
			$this->createMock(TimeTrackingService::class),
			$this->createMock(IUserManager::class),
			$this->createMock(AuditLogMapper::class),
			$time,
			$this->createMock(ILockingProvider::class),
		);

		$terminal = new KioskTerminal();
		$terminal->setTerminalId('term-1');

		try {
			$service->identify($terminal, 'pin', null, 'alice', '123456');
			$this->fail('Expected KioskException');
		} catch (KioskException $e) {
			$this->assertSame('KIOSK_CLOCK_STAMPING_DISABLED', $e->getErrorCode());
		}
	}

	public function testIdentifyPinSucceedsWhenEligible(): void
	{
		$cred = new KioskCred();
		$cred->setId(7);
		$cred->setUserId('alice');
		$cred->setType('pin');

		$credMapper = $this->createMock(KioskCredMapper::class);
		$credMapper->method('findByUserAndType')->with('alice', 'pin')->willReturn($cred);

		$credService = $this->createMock(KioskCredentialService::class);
		$credService->method('isLocked')->willReturn(false);
		$credService->method('verifyPin')->willReturn(true);
		$credService->expects($this->once())->method('assertUserKioskAllowed')->with('alice');
		$credService->expects($this->once())->method('resetFailedAttempts');

		$permission = $this->createMock(PermissionService::class);
		$permission->method('isUserAllowedByAccessGroups')->willReturn(true);

		$timeCapture = $this->createMock(TimeCaptureMethodService::class);
		$timeCapture->expects($this->once())->method('assertClockStampingAllowed')->with('alice');

		$sessionMapper = $this->createMock(KioskSessionMapper::class);
		$sessionMapper->expects($this->once())->method('deleteExpiredForTerminal');
		$sessionMapper->expects($this->once())->method('deleteUnusedForTerminal')->with('term-1');
		$sessionMapper->expects($this->once())->method('insert');

		$user = $this->createMock(IUser::class);
		$user->method('getDisplayName')->willReturn('Alice Example');
		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('get')->with('alice')->willReturn($user);

		$tracking = $this->createMock(TimeTrackingService::class);
		$tracking->method('getStatus')->willReturn([
			'status' => 'clocked_out',
			'working_today_hours' => 1.5,
		]);

		$audit = $this->createMock(AuditLogMapper::class);
		$audit->expects($this->once())->method('logAction');

		$time = $this->createMock(ITimeFactory::class);
		$time->method('getDateTime')->willReturn(new \DateTime('2026-07-20 12:00:00'));

		$locking = $this->createMock(ILockingProvider::class);
		$locking->expects($this->once())->method('acquireLock');
		$locking->expects($this->once())->method('releaseLock');

		$service = new KioskAuthService(
			$credService,
			$credMapper,
			$this->createMock(KioskEnrollmentMapper::class),
			$sessionMapper,
			$this->createMock(KioskSettingsService::class),
			$permission,
			$timeCapture,
			$tracking,
			$userManager,
			$audit,
			$time,
			$locking,
		);

		$terminal = new KioskTerminal();
		$terminal->setTerminalId('term-1');

		$result = $service->identify($terminal, 'pin', null, 'alice', '123456');
		$this->assertSame('alice', $result['userId']);
		$this->assertSame('Alice Example', $result['displayName']);
		$this->assertSame('off', $result['status']);
		$this->assertSame(['clock_in'], $result['allowedActions']);
		$this->assertNotSame('', $result['sessionToken']);
		$this->assertArrayHasKey('sessionExpiresAt', $result);
		$this->assertNotFalse(\DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $result['sessionExpiresAt']));
	}
}
