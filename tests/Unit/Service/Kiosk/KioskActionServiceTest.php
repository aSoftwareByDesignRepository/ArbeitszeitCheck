<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service\Kiosk;

use OCA\ArbeitszeitCheck\Db\AuditLogMapper;
use OCA\ArbeitszeitCheck\Db\KioskSession;
use OCA\ArbeitszeitCheck\Db\KioskSessionMapper;
use OCA\ArbeitszeitCheck\Db\KioskTerminal;
use OCA\ArbeitszeitCheck\Service\Kiosk\KioskActionService;
use OCA\ArbeitszeitCheck\Service\Kiosk\KioskAuthService;
use OCA\ArbeitszeitCheck\Service\Kiosk\KioskException;
use OCA\ArbeitszeitCheck\Service\TimeTrackingService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;

final class KioskActionServiceTest extends TestCase
{
	public function testPerformActionReChecksUserEligibility(): void
	{
		$terminal = new KioskTerminal();
		$terminal->setTerminalId('tid-1');

		$session = new KioskSession();
		$session->setId(9);
		$session->setUserId('alice');

		$auth = $this->createMock(KioskAuthService::class);
		$auth->method('validateSession')->willReturn($session);
		$auth->expects(self::once())
			->method('assertUserEligibleForAction')
			->with('alice')
			->willThrowException(new KioskException('KIOSK_USER_NOT_ALLOWED'));

		$sessionMapper = $this->createMock(KioskSessionMapper::class);
		$sessionMapper->expects(self::never())->method('claimUnused');

		$service = new KioskActionService(
			$auth,
			$sessionMapper,
			$this->createMock(TimeTrackingService::class),
			$this->createMock(AuditLogMapper::class),
			$this->createMock(IL10N::class),
			$this->createMock(ITimeFactory::class),
		);

		$this->expectException(KioskException::class);
		$service->performAction($terminal, 'session-token', 'clock_in');
	}

	public function testPerformActionClaimsSessionBeforeClockIn(): void
	{
		$terminal = new KioskTerminal();
		$terminal->setTerminalId('tid-1');

		$session = new KioskSession();
		$session->setId(9);
		$session->setUserId('alice');

		$auth = $this->createMock(KioskAuthService::class);
		$auth->method('validateSession')->willReturn($session);
		$auth->method('assertUserEligibleForAction');

		$now = new \DateTime('2026-06-10 12:00:00');
		$timeFactory = $this->createMock(ITimeFactory::class);
		$timeFactory->method('getDateTime')->willReturn($now);

		$sessionMapper = $this->createMock(KioskSessionMapper::class);
		$sessionMapper->expects(self::once())
			->method('claimUnused')
			->with($session, $now)
			->willReturn(true);

		$tracking = $this->createMock(TimeTrackingService::class);
		$tracking->expects(self::once())->method('clockIn')->with('alice');

		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);

		$service = new KioskActionService(
			$auth,
			$sessionMapper,
			$tracking,
			$this->createMock(AuditLogMapper::class),
			$l10n,
			$timeFactory,
		);

		$result = $service->performAction($terminal, 'session-token', 'clock_in');
		self::assertSame('working', $result['newStatus']);
	}

	public function testPerformActionRejectsAlreadyUsedSession(): void
	{
		$terminal = new KioskTerminal();
		$terminal->setTerminalId('tid-1');

		$session = new KioskSession();
		$session->setId(9);
		$session->setUserId('alice');

		$auth = $this->createMock(KioskAuthService::class);
		$auth->method('validateSession')->willReturn($session);
		$auth->method('assertUserEligibleForAction');

		$sessionMapper = $this->createMock(KioskSessionMapper::class);
		$sessionMapper->method('claimUnused')->willReturn(false);

		$tracking = $this->createMock(TimeTrackingService::class);
		$tracking->expects(self::never())->method('clockIn');

		$service = new KioskActionService(
			$auth,
			$sessionMapper,
			$tracking,
			$this->createMock(AuditLogMapper::class),
			$this->createMock(IL10N::class),
			$this->createMock(ITimeFactory::class),
		);

		$this->expectException(KioskException::class);
		$service->performAction($terminal, 'session-token', 'clock_in');
	}

	public function testPerformActionReleasesClaimWhenClockInRejected(): void
	{
		$terminal = new KioskTerminal();
		$terminal->setTerminalId('tid-1');

		$session = new KioskSession();
		$session->setId(9);
		$session->setUserId('alice');

		$auth = $this->createMock(KioskAuthService::class);
		$auth->method('validateSession')->willReturn($session);
		$auth->method('assertUserEligibleForAction');

		$now = new \DateTime('2026-06-10 12:00:00');
		$timeFactory = $this->createMock(ITimeFactory::class);
		$timeFactory->method('getDateTime')->willReturn($now);

		$sessionMapper = $this->createMock(KioskSessionMapper::class);
		$sessionMapper->expects(self::once())
			->method('claimUnused')
			->with($session, $now)
			->willReturn(true);
		$sessionMapper->expects(self::once())
			->method('releaseClaim')
			->with($session, $now)
			->willReturn(true);

		$tracking = $this->createMock(TimeTrackingService::class);
		$tracking->expects(self::once())
			->method('clockIn')
			->willThrowException(new \OCA\ArbeitszeitCheck\Exception\BusinessRuleException('already in'));

		$service = new KioskActionService(
			$auth,
			$sessionMapper,
			$tracking,
			$this->createMock(AuditLogMapper::class),
			$this->createMock(IL10N::class),
			$timeFactory,
		);

		$this->expectException(KioskException::class);
		$this->expectExceptionMessage('KIOSK_ACTION_INVALID');
		$service->performAction($terminal, 'session-token', 'clock_in');
	}

	public function testPerformActionReleasesClaimOnUnexpectedFailure(): void
	{
		$terminal = new KioskTerminal();
		$terminal->setTerminalId('tid-1');

		$session = new KioskSession();
		$session->setId(9);
		$session->setUserId('alice');

		$auth = $this->createMock(KioskAuthService::class);
		$auth->method('validateSession')->willReturn($session);
		$auth->method('assertUserEligibleForAction');

		$now = new \DateTime('2026-06-10 12:00:00');
		$timeFactory = $this->createMock(ITimeFactory::class);
		$timeFactory->method('getDateTime')->willReturn($now);

		$sessionMapper = $this->createMock(KioskSessionMapper::class);
		$sessionMapper->expects(self::once())
			->method('claimUnused')
			->with($session, $now)
			->willReturn(true);
		$sessionMapper->expects(self::once())
			->method('releaseClaim')
			->with($session, $now)
			->willReturn(true);

		$tracking = $this->createMock(TimeTrackingService::class);
		$tracking->expects(self::once())
			->method('clockIn')
			->willThrowException(new \RuntimeException('db down'));

		$service = new KioskActionService(
			$auth,
			$sessionMapper,
			$tracking,
			$this->createMock(AuditLogMapper::class),
			$this->createMock(IL10N::class),
			$timeFactory,
		);

		$this->expectException(\RuntimeException::class);
		$service->performAction($terminal, 'session-token', 'clock_in');
	}

	public function testPerformActionReleasesClaimWhenStampingDisabledMidMutation(): void
	{
		$terminal = new KioskTerminal();
		$terminal->setTerminalId('tid-1');

		$session = new KioskSession();
		$session->setId(9);
		$session->setUserId('alice');

		$auth = $this->createMock(KioskAuthService::class);
		$auth->method('validateSession')->willReturn($session);
		$auth->method('assertUserEligibleForAction');

		$now = new \DateTime('2026-06-10 12:00:00');
		$timeFactory = $this->createMock(ITimeFactory::class);
		$timeFactory->method('getDateTime')->willReturn($now);

		$sessionMapper = $this->createMock(KioskSessionMapper::class);
		$sessionMapper->method('claimUnused')->willReturn(true);
		$sessionMapper->expects(self::once())->method('releaseClaim')->with($session, $now);

		$tracking = $this->createMock(TimeTrackingService::class);
		$tracking->method('clockIn')->willThrowException(
			new \OCA\ArbeitszeitCheck\Exception\TimeCaptureForbiddenException(
				'off',
				\OCA\ArbeitszeitCheck\Exception\TimeCaptureForbiddenException::CODE_CLOCK_STAMPING_DISABLED,
			),
		);

		$service = new KioskActionService(
			$auth,
			$sessionMapper,
			$tracking,
			$this->createMock(AuditLogMapper::class),
			$this->createMock(IL10N::class),
			$timeFactory,
		);

		try {
			$service->performAction($terminal, 'session-token', 'clock_in');
			self::fail('Expected KioskException');
		} catch (KioskException $e) {
			self::assertSame('KIOSK_CLOCK_STAMPING_DISABLED', $e->getErrorCode());
		}
	}
}
