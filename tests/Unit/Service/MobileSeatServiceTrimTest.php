<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service;

use OCA\ArbeitszeitCheck\Db\MobileSeat;
use OCA\ArbeitszeitCheck\Db\MobileSeatMapper;
use OCA\ArbeitszeitCheck\Service\LicenseService;
use OCA\ArbeitszeitCheck\Service\MobileSeatService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IDBConnection;
use OCP\IUserManager;
use OCP\Lock\ILockingProvider;
use PHPUnit\Framework\TestCase;

class MobileSeatServiceTrimTest extends TestCase
{
	public function testTrimToLimitRemovesNewestAssignments(): void
	{
		$seatA = new MobileSeat();
		$seatA->setUserId('alice');
		$seatB = new MobileSeat();
		$seatB->setUserId('bob');
		$seatC = new MobileSeat();
		$seatC->setUserId('carol');

		$mapper = $this->createMock(MobileSeatMapper::class);
		$mapper->method('findAllOrdered')->willReturn([$seatA, $seatB, $seatC]);
		$mapper->expects(self::exactly(2))
			->method('delete')
			->with(self::logicalOr($seatB, $seatC));

		$service = new MobileSeatService(
			$mapper,
			$this->createMock(LicenseService::class),
			$this->createMock(IUserManager::class),
			$this->createMock(ITimeFactory::class),
			$this->createMock(IDBConnection::class),
			$this->createMock(ILockingProvider::class),
		);

		self::assertSame(2, $service->trimToLimit(1));
	}
}
