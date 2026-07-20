<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service\Kiosk;

use OCA\ArbeitszeitCheck\Service\Kiosk\KioskCredentialLockKeys;
use PHPUnit\Framework\TestCase;

class KioskCredentialLockKeysTest extends TestCase
{
	public function testKeysFitFileLocksVarchar64(): void
	{
		$rfid = KioskCredentialLockKeys::forRfidAssign('admin');
		$pin = KioskCredentialLockKeys::forPinGenerate('admin');
		$cred = KioskCredentialLockKeys::forCredLockout(1_234_567_890);

		$this->assertLessThanOrEqual(64, strlen($rfid));
		$this->assertLessThanOrEqual(64, strlen($pin));
		$this->assertLessThanOrEqual(64, strlen($cred));
	}

	public function testKeysAreStableAndDistinct(): void
	{
		$this->assertSame(
			KioskCredentialLockKeys::forRfidAssign('alice'),
			KioskCredentialLockKeys::forRfidAssign('alice'),
		);
		$this->assertNotSame(
			KioskCredentialLockKeys::forRfidAssign('alice'),
			KioskCredentialLockKeys::forRfidAssign('bob'),
		);
		$this->assertNotSame(
			KioskCredentialLockKeys::forRfidAssign('alice'),
			KioskCredentialLockKeys::forPinGenerate('alice'),
		);
		$this->assertNotSame(
			KioskCredentialLockKeys::forCredLockout(1),
			KioskCredentialLockKeys::forCredLockout(2),
		);
	}

	/**
	 * Guard against regressing to the over-long sha256 keys that truncated in
	 * oc_file_locks and left exclusive locks stuck (KIOSK_BUSY on next assign).
	 */
	public function testLegacyLongKeyShapesExceedLimit(): void
	{
		$legacyRfid = 'arbeitszeitcheck/kiosk_rfid_assign/' . hash('sha256', 'admin');
		$legacyPin = 'arbeitszeitcheck/kiosk_pin_gen/' . hash('sha256', 'admin');
		$this->assertGreaterThan(64, strlen($legacyRfid));
		$this->assertGreaterThan(64, strlen($legacyPin));
		$this->assertLessThanOrEqual(64, strlen(KioskCredentialLockKeys::forRfidAssign('admin')));
		$this->assertLessThanOrEqual(64, strlen(KioskCredentialLockKeys::forPinGenerate('admin')));
	}
}
