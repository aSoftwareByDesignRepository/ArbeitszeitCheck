<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Support;

use OCA\ArbeitszeitCheck\Config\VendorPublicKey;
use OCA\ArbeitszeitCheck\License\Azc2Codec;

/**
 * Signs AZC2 payloads with the PHPUnit/dev Ed25519 seed (see VendorPublicKeyTest).
 */
final class Azc2TestSigning
{
	public static function signPayload(array $payload): string
	{
		$seed = hash('sha256', 'arbeitszeitcheck-azc2-test-signing-v1', true);
		$keypair = sodium_crypto_sign_seed_keypair($seed);
		$secretKey = sodium_crypto_sign_secretkey($keypair);
		$json = Azc2Codec::canonicalJson($payload);
		$signature = sodium_crypto_sign_detached($json, $secretKey);

		return Azc2Codec::FORMAT . '.'
			. VendorPublicKey::base64urlEncode($json) . '.'
			. VendorPublicKey::base64urlEncode($signature);
	}
}
