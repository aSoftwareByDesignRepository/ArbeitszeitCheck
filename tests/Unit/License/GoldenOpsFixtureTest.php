<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\License;

use OCA\ArbeitszeitCheck\Config\VendorPublicKey;
use OCA\ArbeitszeitCheck\License\Azc2Codec;
use PHPUnit\Framework\TestCase;

/**
 * Phase 0.3 — ops golden AZC2 wire must verify byte-identically in the consumer.
 */
final class GoldenOpsFixtureTest extends TestCase
{
	public function testOpsGoldenWireVerifies(): void
	{
		$opsFixture = dirname(__DIR__, 4) . '/sbdlicenseops/tests/fixtures/license_azc2_golden.json';
		self::assertFileExists($opsFixture);
		$data = json_decode((string)file_get_contents($opsFixture), true, 512, JSON_THROW_ON_ERROR);
		putenv('AZC_VENDOR_PUBLIC_KEY_B64=' . (string)$data['publicKeyB64']);
		try {
			$parsed = Azc2Codec::parseAndVerify((string)$data['wireKey']);
			self::assertNotNull($parsed);
			self::assertSame($data['payload'], $parsed['payload']);
			self::assertSame($data['payloadB64'], $parsed['payloadB64']);
			self::assertSame($data['signatureB64'], $parsed['signatureB64']);
		} finally {
			// Restore suite default — clearing the env falls back to production key
			// and poisons later tests that expect TEST_PUBLIC_KEY_B64.
			putenv('AZC_VENDOR_PUBLIC_KEY_B64=' . VendorPublicKey::TEST_PUBLIC_KEY_B64);
		}
	}

	public function testOpsBundleGoldenWireVerifies(): void
	{
		$opsFixture = dirname(__DIR__, 4) . '/sbdlicenseops/tests/fixtures/license_azc2_bundle_golden.json';
		self::assertFileExists($opsFixture);
		$data = json_decode((string)file_get_contents($opsFixture), true, 512, JSON_THROW_ON_ERROR);
		putenv('AZC_VENDOR_PUBLIC_KEY_B64=' . (string)$data['publicKeyB64']);
		try {
			$parsed = Azc2Codec::parseAndVerify((string)$data['wireKey']);
			self::assertNotNull($parsed);
			self::assertTrue(($parsed['payload']['bundle'] ?? false) === true);
			self::assertSame(2, (int)($parsed['payload']['terminalDevices'] ?? 0));
			self::assertArrayNotHasKey('product', $parsed['payload']);
		} finally {
			putenv('AZC_VENDOR_PUBLIC_KEY_B64=' . VendorPublicKey::TEST_PUBLIC_KEY_B64);
		}
	}

	public function testForeignDkc2PrefixRejected(): void
	{
		$opsFixture = dirname(__DIR__, 4) . '/sbdlicenseops/tests/fixtures/license_dkc2_golden.json';
		self::assertFileExists($opsFixture);
		$data = json_decode((string)file_get_contents($opsFixture), true, 512, JSON_THROW_ON_ERROR);
		putenv('AZC_VENDOR_PUBLIC_KEY_B64=' . (string)$data['publicKeyB64']);
		try {
			self::assertNull(Azc2Codec::parseAndVerify((string)$data['wireKey']));
			self::assertSame(Azc2Codec::ERROR_INVALID_FORMAT, Azc2Codec::classifyApplyError((string)$data['wireKey']));
		} finally {
			putenv('AZC_VENDOR_PUBLIC_KEY_B64=' . VendorPublicKey::TEST_PUBLIC_KEY_B64);
		}
	}
}
