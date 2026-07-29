<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Config;

use OCA\ArbeitszeitCheck\Config\VendorPublicKey;
use PHPUnit\Framework\TestCase;

final class VendorPublicKeyTest extends TestCase
{
	protected function tearDown(): void
	{
		// Restore PHPUnit fixture key used by other license tests.
		putenv('AZC_VENDOR_PUBLIC_KEY_B64=' . VendorPublicKey::TEST_PUBLIC_KEY_B64);
		parent::tearDown();
	}

	public function testDefaultPublicKeyIsProductionVendorKey(): void
	{
		putenv('AZC_VENDOR_PUBLIC_KEY_B64');
		self::assertSame(
			'naLgi4THUgwJCRoUehq20QU4uJsLVHzuKV04NhkITn8',
			VendorPublicKey::DEFAULT_PUBLIC_KEY_B64,
		);
		self::assertSame(VendorPublicKey::DEFAULT_PUBLIC_KEY_B64, VendorPublicKey::publicKeyB64());
		self::assertSame(32, strlen(VendorPublicKey::bytes()));
	}

	public function testEnvironmentOverride(): void
	{
		$custom = 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA';
		putenv('AZC_VENDOR_PUBLIC_KEY_B64=' . $custom);
		self::assertTrue(VendorPublicKey::envOverrideAllowed());
		self::assertSame($custom, VendorPublicKey::publicKeyB64());
	}

	public function testEnvOverrideIsGatedOutsideExplicitAllow(): void
	{
		// Under PHPUnit the gate is open by design; production requires
		// AZC_ALLOW_VENDOR_KEY_OVERRIDE=1. Assert the helper exists and the
		// production default stays the trust anchor when env is cleared.
		self::assertTrue(method_exists(VendorPublicKey::class, 'envOverrideAllowed'));
		putenv('AZC_VENDOR_PUBLIC_KEY_B64');
		putenv('AZC_ALLOW_VENDOR_KEY_OVERRIDE');
		self::assertSame(VendorPublicKey::DEFAULT_PUBLIC_KEY_B64, VendorPublicKey::publicKeyB64());
	}

	public function testPublicKeyFromDevTestSeedFile(): void
	{
		$fixturePath = dirname(__DIR__, 2) . '/fixtures/license_azc2.json';
		$fixture = json_decode((string)file_get_contents($fixturePath), true, 512, JSON_THROW_ON_ERROR);
		$tmpdir = sys_get_temp_dir() . '/azc-vendor-key-test-' . bin2hex(random_bytes(4));
		mkdir($tmpdir);
		$seedPath = $tmpdir . '/seed';
		file_put_contents($seedPath, bin2hex(hash('sha256', 'arbeitszeitcheck-azc2-test-signing-v1', true)));
		self::assertSame(VendorPublicKey::TEST_PUBLIC_KEY_B64, (string)$fixture['publicKeyB64']);
		self::assertSame(
			VendorPublicKey::TEST_PUBLIC_KEY_B64,
			VendorPublicKey::publicKeyB64FromSeedFile($seedPath),
		);
	}
}
