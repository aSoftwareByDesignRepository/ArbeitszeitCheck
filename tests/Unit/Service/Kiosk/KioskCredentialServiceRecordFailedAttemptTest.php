<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service\Kiosk;

use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Db\AuditLogMapper;
use OCA\ArbeitszeitCheck\Db\KioskCred;
use OCA\ArbeitszeitCheck\Db\KioskCredMapper;
use OCA\ArbeitszeitCheck\Service\Kiosk\KioskCredentialService;
use OCA\ArbeitszeitCheck\Service\Kiosk\KioskDbLockPurger;
use OCA\ArbeitszeitCheck\Service\Kiosk\KioskSettingsService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IUserManager;
use OCP\Lock\ILockingProvider;
use PHPUnit\Framework\TestCase;

/**
 * Wrong PIN must persist failed_attempts via findById — if findById throws,
 * identify returns 500 and lockout never engages (production incident).
 */
class KioskCredentialServiceRecordFailedAttemptTest extends TestCase
{
	public function testRecordFailedAttemptIncrementsViaFindById(): void
	{
		$cred = new KioskCred();
		$cred->setId(42);
		$cred->setUserId('alice');
		$cred->setType('pin');
		$cred->setFailedAttempts(0);

		$fresh = new KioskCred();
		$fresh->setId(42);
		$fresh->setUserId('alice');
		$fresh->setType('pin');
		$fresh->setFailedAttempts(0);

		$credMapper = $this->createMock(KioskCredMapper::class);
		$credMapper->expects($this->once())->method('findById')->with(42)->willReturn($fresh);
		$credMapper->expects($this->once())->method('update')->with($this->callback(
			static function (KioskCred $c): bool {
				return $c->getFailedAttempts() === 1 && $c->getLockedUntil() === null;
			}
		));

		$locking = $this->createMock(ILockingProvider::class);
		$locking->expects($this->once())->method('acquireLock');
		$locking->expects($this->once())->method('releaseLock');

		$time = $this->createMock(ITimeFactory::class);
		$time->method('getDateTime')->willReturn(new \DateTime('2026-08-30 12:00:00'));

		$service = new KioskCredentialService(
			$credMapper,
			$this->createMock(KioskSettingsService::class),
			$this->createMock(IUserManager::class),
			$this->createMock(AuditLogMapper::class),
			$time,
			$locking,
			$this->createMock(KioskDbLockPurger::class),
		);

		$service->recordFailedAttempt($cred);
		$this->assertSame(1, $cred->getFailedAttempts());
	}

	public function testRecordFailedAttemptLocksAfterMaxFailures(): void
	{
		$cred = new KioskCred();
		$cred->setId(7);
		$cred->setFailedAttempts(Constants::KIOSK_MAX_FAILED_ATTEMPTS - 1);

		$fresh = new KioskCred();
		$fresh->setId(7);
		$fresh->setFailedAttempts(Constants::KIOSK_MAX_FAILED_ATTEMPTS - 1);

		$credMapper = $this->createMock(KioskCredMapper::class);
		$credMapper->method('findById')->willReturn($fresh);
		$credMapper->expects($this->once())->method('update')->with($this->callback(
			static function (KioskCred $c): bool {
				return $c->getFailedAttempts() === Constants::KIOSK_MAX_FAILED_ATTEMPTS
					&& $c->getLockedUntil() instanceof \DateTimeInterface;
			}
		));

		$locking = $this->createMock(ILockingProvider::class);
		$time = $this->createMock(ITimeFactory::class);
		$time->method('getDateTime')->willReturn(new \DateTime('2026-08-30 12:00:00'));

		$service = new KioskCredentialService(
			$credMapper,
			$this->createMock(KioskSettingsService::class),
			$this->createMock(IUserManager::class),
			$this->createMock(AuditLogMapper::class),
			$time,
			$locking,
			$this->createMock(KioskDbLockPurger::class),
		);

		$service->recordFailedAttempt($cred);
		$this->assertNotNull($cred->getLockedUntil());
	}
}
