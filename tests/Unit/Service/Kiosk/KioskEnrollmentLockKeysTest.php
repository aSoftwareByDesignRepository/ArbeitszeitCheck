<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service\Kiosk;

use OCA\ArbeitszeitCheck\Service\Kiosk\KioskEnrollmentLockKeys;
use PHPUnit\Framework\TestCase;

class KioskEnrollmentLockKeysTest extends TestCase
{
	public function testKeysFitFileLocksVarchar64(): void
	{
		$userKey = KioskEnrollmentLockKeys::forUser('admin');
		$termKey = KioskEnrollmentLockKeys::forTerminal('86464388-e0fd-481d-8eb6-51bb6ace83a4');

		$this->assertLessThanOrEqual(64, strlen($userKey));
		$this->assertLessThanOrEqual(64, strlen($termKey));
		$this->assertNotSame($userKey, $termKey);
	}

	public function testKeysAreStableAndDistinctPerSubject(): void
	{
		$this->assertSame(
			KioskEnrollmentLockKeys::forUser('alice'),
			KioskEnrollmentLockKeys::forUser('alice'),
		);
		$this->assertNotSame(
			KioskEnrollmentLockKeys::forUser('alice'),
			KioskEnrollmentLockKeys::forUser('bob'),
		);
		$this->assertNotSame(
			KioskEnrollmentLockKeys::forTerminal('term-a'),
			KioskEnrollmentLockKeys::forTerminal('term-b'),
		);
	}
}
