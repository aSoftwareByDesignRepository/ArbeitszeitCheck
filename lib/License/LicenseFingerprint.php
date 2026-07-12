<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\License;

/**
 * Stable hash of an applied AZC2 wire key (payload + signature parts only).
 */
final class LicenseFingerprint
{
	public static function fromWireParts(string $payloadB64, string $signatureB64): string
	{
		return hash('sha256', $payloadB64 . '.' . $signatureB64);
	}
}
