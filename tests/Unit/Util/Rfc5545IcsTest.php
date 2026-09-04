<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Util;

use OCA\ArbeitszeitCheck\Util\Rfc5545Ics;
use PHPUnit\Framework\TestCase;

final class Rfc5545IcsTest extends TestCase
{
	public function testEscapeTextEscapesRfc5545SpecialCharacters(): void
	{
		$input = "a\\b;c,d\ne";
		$escaped = Rfc5545Ics::escapeText($input);
		self::assertSame("a\\\\b\\;c\\,d\\ne", $escaped);
	}

	public function testFoldLineAsciiRespects75OctetLimitAndAddsLeadingSpace(): void
	{
		$line = str_repeat('a', 80);
		$pieces = Rfc5545Ics::foldLine($line);

		self::assertGreaterThan(1, count($pieces));
		self::assertLessThanOrEqual(75, strlen($pieces[0]));
		self::assertStringStartsWith(' ', $pieces[1]);

		foreach ($pieces as $piece) {
			self::assertLessThanOrEqual(75, strlen($piece));
		}

		$reconstructed = $pieces[0];
		for ($i = 1; $i < count($pieces); $i++) {
			$reconstructed .= ltrim($pieces[$i], ' ');
		}
		self::assertSame($line, $reconstructed);
	}

	public function testFoldLineUtf8DoesNotCorruptCharacters(): void
	{
		// 4-byte emoji sequence. The folding code must not split in the middle
		// of UTF-8 scalars.
		$line = str_repeat('😀', 30); // bytes > 75
		$pieces = Rfc5545Ics::foldLine($line);

		self::assertGreaterThan(1, count($pieces));

		// Byte lengths must stay within the hard RFC 5545 limit.
		foreach ($pieces as $piece) {
			self::assertLessThanOrEqual(75, strlen($piece));
		}

		$reconstructed = $pieces[0];
		for ($i = 1; $i < count($pieces); $i++) {
			$reconstructed .= ltrim($pieces[$i], ' ');
		}

		self::assertSame($line, $reconstructed);
	}
}

