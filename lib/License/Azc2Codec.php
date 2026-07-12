<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\License;

use OCA\ArbeitszeitCheck\Config\VendorPublicKey;

/**
 * AZC2 wire format: canonical JSON bytes, base64url, Ed25519 verify.
 */
final class Azc2Codec
{
	public const FORMAT = 'AZC2';
	public const VERSION = 2;

	public const ERROR_INVALID_FORMAT = 'INVALID_FORMAT';
	public const ERROR_INVALID_SIGNATURE = 'INVALID_SIGNATURE';
	public const ERROR_EXPIRED = 'EXPIRED';
	public const ERROR_NO_PRODUCTS = 'NO_PRODUCTS';
	public const ERROR_INVALID_PAYLOAD = 'INVALID_PAYLOAD';
	public const ERROR_INSTANCE_MISMATCH = 'INSTANCE_MISMATCH';

	/**
	 * @return array{payload: array<string, mixed>, payloadBytes: string, payloadB64: string, signatureB64: string}|null
	 */
	public static function parseAndVerify(string $wireKey): ?array
	{
		$wireKey = trim($wireKey);
		$parts = explode('.', $wireKey);
		if (count($parts) !== 3 || $parts[0] !== self::FORMAT) {
			return null;
		}

		$payloadBytes = VendorPublicKey::base64urlDecode($parts[1]);
		$signature = VendorPublicKey::base64urlDecode($parts[2]);
		if ($payloadBytes === false || $signature === false) {
			return null;
		}
		if (strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
			return null;
		}

		$publicKey = VendorPublicKey::bytes();
		if (!sodium_crypto_sign_verify_detached($signature, $payloadBytes, $publicKey)) {
			return null;
		}

		try {
			/** @var array<string, mixed>|null $payload */
			$payload = json_decode($payloadBytes, true, 16, JSON_THROW_ON_ERROR);
		} catch (\JsonException) {
			return null;
		}
		if (!is_array($payload)) {
			return null;
		}

		if (!self::validatePayloadFields($payload)) {
			return null;
		}

		$canonical = self::canonicalJson($payload);
		if (!hash_equals($canonical, $payloadBytes)) {
			return null;
		}

		return [
			'payload' => $payload,
			'payloadBytes' => $payloadBytes,
			'payloadB64' => $parts[1],
			'signatureB64' => $parts[2],
		];
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	public static function validatePayloadFields(array $payload): bool
	{
		if (($payload['v'] ?? null) !== self::VERSION) {
			return false;
		}
		$customerId = $payload['customerId'] ?? '';
		if (!is_string($customerId) || !preg_match('/^[a-z0-9-]{3,64}$/', $customerId)) {
			return false;
		}
		foreach (['issuedAt', 'validUntil'] as $dateField) {
			$val = $payload[$dateField] ?? '';
			if (!is_string($val) || !self::isValidYmd($val)) {
				return false;
			}
		}
		$issued = $payload['issuedAt'];
		$until = $payload['validUntil'];
		if ($until < $issued) {
			return false;
		}
		$mobile = $payload['mobileSeats'] ?? -1;
		$terminal = $payload['terminalDevices'] ?? -1;
		if (!is_int($mobile) || !is_int($terminal)) {
			return false;
		}
		if ($mobile < 0 || $mobile > 10000 || $terminal < 0 || $terminal > 1000) {
			return false;
		}
		if ($mobile + $terminal <= 0) {
			return false;
		}
		if (array_key_exists('bundle', $payload) && $payload['bundle'] !== true) {
			return false;
		}
		if (array_key_exists('instanceId', $payload)) {
			$instanceId = $payload['instanceId'];
			if (!is_string($instanceId) || !preg_match('/^[a-z0-9-]{3,64}$/', $instanceId)) {
				return false;
			}
		}
		return true;
	}

	public static function isExpired(array $payload, ?\DateTimeImmutable $today = null): bool
	{
		$today ??= new \DateTimeImmutable('today');
		$until = \DateTimeImmutable::createFromFormat('Y-m-d', (string)$payload['validUntil']);
		if ($until === false) {
			return true;
		}
		return $until < $today;
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	public static function canonicalJson(array $payload): string
	{
		$ordered = [
			'v' => (int)$payload['v'],
			'customerId' => (string)$payload['customerId'],
			'issuedAt' => (string)$payload['issuedAt'],
			'validUntil' => (string)$payload['validUntil'],
			'mobileSeats' => (int)$payload['mobileSeats'],
			'terminalDevices' => (int)$payload['terminalDevices'],
		];
		if (!empty($payload['instanceId']) && is_string($payload['instanceId'])) {
			$ordered['instanceId'] = $payload['instanceId'];
		}
		if (!empty($payload['bundle'])) {
			$ordered['bundle'] = true;
		}
		$json = json_encode($ordered, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if ($json === false) {
			throw new \RuntimeException('AZC2 canonical JSON encode failed.');
		}
		return $json;
	}

	public static function classifyApplyError(string $wireKey): string
	{
		$wireKey = trim($wireKey);
		$parts = explode('.', $wireKey);
		if (count($parts) !== 3 || $parts[0] !== self::FORMAT) {
			return self::ERROR_INVALID_FORMAT;
		}

		$payloadBytes = VendorPublicKey::base64urlDecode($parts[1]);
		$signature = VendorPublicKey::base64urlDecode($parts[2]);
		if ($payloadBytes === false || $signature === false || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
			return self::ERROR_INVALID_FORMAT;
		}

		$publicKey = VendorPublicKey::bytes();
		if (!sodium_crypto_sign_verify_detached($signature, $payloadBytes, $publicKey)) {
			return self::ERROR_INVALID_SIGNATURE;
		}

		try {
			/** @var array<string, mixed> $payload */
			$payload = json_decode($payloadBytes, true, 16, JSON_THROW_ON_ERROR);
		} catch (\JsonException) {
			return self::ERROR_INVALID_PAYLOAD;
		}

		if (!self::validatePayloadFields($payload)) {
			$mobile = (int)($payload['mobileSeats'] ?? 0);
			$terminal = (int)($payload['terminalDevices'] ?? 0);
			if ($mobile + $terminal <= 0) {
				return self::ERROR_NO_PRODUCTS;
			}
			return self::ERROR_INVALID_PAYLOAD;
		}

		if (!hash_equals(self::canonicalJson($payload), $payloadBytes)) {
			return self::ERROR_INVALID_PAYLOAD;
		}

		if (self::isExpired($payload)) {
			return self::ERROR_EXPIRED;
		}

		return '';
	}

	private static function isValidYmd(string $value): bool
	{
		$dt = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
		return $dt !== false && $dt->format('Y-m-d') === $value;
	}
}
