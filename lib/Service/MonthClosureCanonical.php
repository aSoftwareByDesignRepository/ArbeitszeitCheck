<?php

declare(strict_types=1);

/**
 * Canonical JSON for tamper-evident month snapshots.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Service;

final class MonthClosureCanonical
{
	public const SCHEMA_V1 = 'arbeitszeitcheck.month_closure.v1';

	/**
	 * Recursively ksort arrays for stable JSON encoding.
	 *
	 * @param mixed $data
	 * @return mixed
	 */
	public static function normalize($data)
	{
		if (!is_array($data)) {
			return $data;
		}
		$isAssoc = array_keys($data) !== range(0, count($data) - 1);
		if ($isAssoc) {
			ksort($data);
		}
		foreach ($data as $k => $v) {
			$data[$k] = self::normalize($v);
		}
		return $data;
	}

	public static function encode(array $payload): string
	{
		$normalized = self::normalize($payload);
		$json = json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
		return $json;
	}

	public static function hashChain(string $prevHash, string $userId, int $year, int $month, int $version, string $canonicalJson): string
	{
		$material = $prevHash . "\0" . $userId . "\0" . (string)$year . "\0" . (string)$month . "\0" . (string)$version . "\0" . $canonicalJson;
		return hash('sha256', $material, false);
	}
}
