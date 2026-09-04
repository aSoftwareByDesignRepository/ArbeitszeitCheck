<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Util;

/**
 * Minimal RFC 5545 iCalendar serialisation helpers.
 *
 * Security goal: make outbound calendar payloads auditable and deterministic.
 * In particular:
 * - Escape the RFC 5545 TEXT characters (backslash, semicolon, comma, newline).
 * - Fold long "content lines" at UTF-8 character boundaries with a hard 75-octet limit.
 *   Continuation lines start with one leading space.
 */
final class Rfc5545Ics
{
	/**
	 * Escape a TEXT value (RFC 5545 §3.3.11):
	 * backslash, semicolon, comma, newline.
	 */
	public static function escapeText(string $value): string
	{
		// Normalize line endings first so we only handle one newline representation.
		$value = str_replace(["\r\n", "\r"], "\n", $value);

		return str_replace(
			['\\', ';', ',', "\n"],
			['\\\\', '\\;', '\\,', '\\n'],
			$value
		);
	}

	/**
	 * Fold content lines to RFC 5545 (§3.1).
	 *
	 * @param list<string> $lines Physical content lines without CRLF.
	 * @return string Complete folded payload including CRLF and a final CRLF.
	 */
	public static function fold(array $lines): string
	{
		$out = [];
		foreach ($lines as $line) {
			foreach (self::foldLine($line) as $piece) {
				$out[] = $piece;
			}
		}
		return implode("\r\n", $out) . "\r\n";
	}

	/**
	 * Fold a single line at UTF-8 boundaries.
	 *
	 * @return list<string>
	 */
	public static function foldLine(string $line): array
	{
		if (strlen($line) <= 75) {
			return [$line];
		}

		$pictures = [];
		$current = '';
		$byteLimit = 75;

		$charCount = mb_strlen($line, 'UTF-8');
		for ($i = 0; $i < $charCount; $i++) {
			$char = mb_substr($line, $i, 1, 'UTF-8');

			// $current and $char are bytes-safe as we always measure with strlen.
			$wouldExceed = strlen($current) + strlen($char) > $byteLimit;
			if ($current !== '' && $wouldExceed) {
				$pictures[] = $current;
				$current = ' ' . $char; // continuation lines start with a single space
				continue;
			}

			$current .= $char;
		}

		if ($current !== '') {
			$pictures[] = $current;
		}

		return $pictures;
	}

	private function __construct()
	{
	}
}

