<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service\Kiosk;

use OCA\ArbeitszeitCheck\Db\KioskTerminalMapper;
use OCA\ArbeitszeitCheck\Kiosk\KioskCrypto;
use OCA\ArbeitszeitCheck\Service\Kiosk\KioskException;
use OCA\ArbeitszeitCheck\Service\Kiosk\KioskTerminalService;
use OCA\ArbeitszeitCheck\Service\LicenseService;
use OCA\ArbeitszeitCheck\Service\TerminalDeviceService;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class KioskTerminalServiceTest extends TestCase
{
	private KioskTerminalMapper&MockObject $terminalMapper;
	private TerminalDeviceService&MockObject $terminalDeviceService;
	private LicenseService&MockObject $licenseService;
	private ITimeFactory&MockObject $timeFactory;
	private KioskTerminalService $service;

	protected function setUp(): void
	{
		$this->terminalMapper = $this->createMock(KioskTerminalMapper::class);
		$this->terminalDeviceService = $this->createMock(TerminalDeviceService::class);
		$this->licenseService = $this->createMock(LicenseService::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->timeFactory->method('getDateTime')->willReturn(new \DateTime('2026-06-10 12:00:00'));
		$this->service = new KioskTerminalService(
			$this->terminalMapper,
			$this->terminalDeviceService,
			$this->licenseService,
			$this->timeFactory,
		);
	}

	public function testCreatePendingRequiresTerminalLicense(): void
	{
		$this->licenseService->method('isTerminalPlanActive')->willReturn(false);
		$this->expectException(KioskException::class);
		$this->service->createPendingTerminal('Hall', 'admin');
	}

	public function testCreatePendingReservesDeviceSlot(): void
	{
		$this->licenseService->method('isTerminalPlanActive')->willReturn(true);
		$this->terminalDeviceService->method('hasCapacity')->willReturn(true);
		$this->terminalDeviceService->expects(self::once())->method('reserveSlot')->with('Hall');
		$this->terminalDeviceService->expects(self::once())->method('linkToKioskTerminal');
		$this->terminalMapper->expects(self::once())->method('insert');

		$result = $this->service->createPendingTerminal('Hall', 'admin');
		self::assertSame('pending', $result['terminal']->getStatus());
		self::assertNotEmpty($result['pairingCode']);
	}

	public function testPairingCodeVerificationIsCaseInsensitive(): void
	{
		$this->licenseService->method('isTerminalPlanActive')->willReturn(true);
		$this->terminalDeviceService->method('hasCapacity')->willReturn(true);

		$code = 'AB12CD34';
		$terminal = new \OCA\ArbeitszeitCheck\Db\KioskTerminal();
		$terminal->setTerminalId('tid-1');
		$terminal->setLabel('Hall');
		$terminal->setPairingCodeHash(KioskCrypto::hashSecret($code));
		$terminal->setPairingExpiresAt(new \DateTime('2026-06-10 13:00:00'));
		$terminal->setStatus('pending');

		$this->terminalMapper->method('findPendingPairing')->willReturn([$terminal]);
		$this->terminalMapper->expects(self::once())->method('update');
		$device = new \OCA\ArbeitszeitCheck\Db\TerminalDevice();
		$this->terminalDeviceService->method('findByKioskTerminalId')->willReturn($device);
		$this->terminalDeviceService->expects(self::never())->method('reserveSlot');

		$result = $this->service->pair('ab12cd34', '');
		self::assertSame('tid-1', $result['terminalId']);
		self::assertNotEmpty($result['terminalToken']);
	}

	public function testExpireStalePendingTerminalsRevokesExpiredRows(): void
	{
		$expired = new \OCA\ArbeitszeitCheck\Db\KioskTerminal();
		$expired->setTerminalId('tid-expired');
		$expired->setStatus('pending');
		$expired->setPairingExpiresAt(new \DateTime('2026-06-10 11:00:00'));

		$fresh = new \OCA\ArbeitszeitCheck\Db\KioskTerminal();
		$fresh->setTerminalId('tid-fresh');
		$fresh->setStatus('pending');
		$fresh->setPairingExpiresAt(new \DateTime('2026-06-10 13:00:00'));

		$this->terminalMapper->method('findPendingPairing')->willReturn([$expired, $fresh]);
		$this->terminalMapper->expects(self::once())->method('update')->with($expired);
		$this->terminalDeviceService->expects(self::once())
			->method('revokeByKioskTerminalId')
			->with('tid-expired');

		self::assertSame(1, $this->service->expireStalePendingTerminals());
		self::assertSame('revoked', $expired->getStatus());
	}

	public function testRevokeFreesTerminalDeviceSlot(): void
	{
		$terminal = new \OCA\ArbeitszeitCheck\Db\KioskTerminal();
		$terminal->setTerminalId('tid-revoke');
		$terminal->setStatus('active');
		$terminal->setTokenHash(KioskCrypto::hashSecret('token'));

		$this->terminalMapper->method('findByTerminalId')->with('tid-revoke')->willReturn($terminal);
		$this->terminalMapper->expects(self::once())->method('update');
		$this->terminalDeviceService->expects(self::once())
			->method('revokeByKioskTerminalId')
			->with('tid-revoke');

		$this->service->revoke('tid-revoke');
	}
}
