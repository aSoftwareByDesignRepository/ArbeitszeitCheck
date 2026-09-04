<?php
declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit;

use OCA\ArbeitszeitCheck\Constants;
use PHPUnit\Framework\TestCase;

class KioskPairingTtlTest extends TestCase
{
	public function testPairingTtlIsTenMinutesNotFourteenDays(): void
	{
		$this->assertSame(600, Constants::KIOSK_PAIRING_TTL_SECONDS);
		$this->assertLessThan(3600, Constants::KIOSK_PAIRING_TTL_SECONDS);
	}

	public function testAdminUiCopyMatchesTenMinuteTtlNotFourteenDays(): void
	{
		$tpl = file_get_contents(dirname(__DIR__, 2) . '/templates/admin-kiosk.php');
		$this->assertNotFalse($tpl);
		$this->assertStringNotContainsString('within 14 days', $tpl);
		$this->assertStringContainsString('within 10 minutes', $tpl);
	}
}
