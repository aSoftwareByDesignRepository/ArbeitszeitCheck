<?php

declare(strict_types=1);

/**
 * Tiny PDF (PDF 1.4) with Helvetica text — no external dependencies.
 * Supports multiple pages when content exceeds one page.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Service;

final class MinimalPdfBuilder
{
	/** First page: title at 760; body lines start at 735 */
	private const Y_FIRST_BODY = 735;

	/** Continuation pages: first line */
	private const Y_CONT_START = 760;

	private const Y_MIN = 56;

	private const LINE_HEIGHT = 12;

	private const FONT_TITLE = 11;

	private const FONT_BODY = 9;

	/** Body lines that fit on page 1 (below title). */
	private const LINES_PAGE_FIRST = 52;

	/** Body lines on continuation pages (full height). */
	private const LINES_PAGE_NEXT = 58;

	/**
	 * @param string[] $lines UTF-8 lines (will be escaped for PDF WinAnsi subset)
	 */
	public static function build(string $title, array $lines): string
	{
		$pages = self::chunkLinesIntoPages($lines);
		$nPages = count($pages);
		if ($nPages === 0) {
			$pages = [['first' => true, 'lines' => []]];
			$nPages = 1;
		}

		$totalObjects = 2 + 2 * $nPages + 1;
		$fontObj = $totalObjects;

		$objects = [];
		$objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
		$kids = [];
		for ($p = 0; $p < $nPages; $p++) {
			$kids[] = (3 + 2 * $p) . ' 0 R';
		}
		$objects[] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $nPages . ' >>';

		for ($p = 0; $p < $nPages; $p++) {
			$pageNum = 3 + 2 * $p;
			$contentNum = 4 + 2 * $p;
			$chunk = $pages[$p];
			$stream = self::buildPageStream($title, $chunk['first'], $chunk['lines']);
			$streamLen = strlen($stream);
			$objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents ' . $contentNum . ' 0 R /Resources << /Font << /F1 ' . $fontObj . ' 0 R >> >> >>';
			$objects[] = "<< /Length {$streamLen} >>\nstream\n{$stream}\nendstream";
		}

		// WinAnsiEncoding: required so 0xE4–0xFC etc. map to äöüß (StandardEncoding would show .notdef / “tofu”).
		$objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';

		return self::assemblePdf($objects);
	}

	/**
	 * @param array<int, array{first: bool, lines: string[]}> $pages
	 */
	private static function chunkLinesIntoPages(array $lines): array
	{
		$pages = [];
		$n = count($lines);
		if ($n === 0) {
			return [['first' => true, 'lines' => []]];
		}

		$offset = 0;
		$first = true;
		while ($offset < $n) {
			$take = $first ? self::LINES_PAGE_FIRST : self::LINES_PAGE_NEXT;
			$pages[] = [
				'first' => $first,
				'lines' => array_slice($lines, $offset, $take),
			];
			$offset += $take;
			$first = false;
		}

		return $pages;
	}

	/**
	 * @param string[] $chunkLines
	 */
	private static function buildPageStream(string $title, bool $isFirstPage, array $chunkLines): string
	{
		$streamParts = [];
		if ($isFirstPage) {
			$streamParts[] = 'BT /F1 ' . self::FONT_TITLE . ' Tf 56 760 Td (' . self::escapePdfText($title) . ') Tj ET';
			$y = self::Y_FIRST_BODY;
		} else {
			$y = self::Y_CONT_START;
		}

		foreach ($chunkLines as $line) {
			$streamParts[] = 'BT /F1 ' . self::FONT_BODY . ' Tf 56 ' . $y . ' Td (' . self::escapePdfText($line) . ') Tj ET';
			$y -= self::LINE_HEIGHT;
			if ($y < self::Y_MIN) {
				break;
			}
		}

		return implode("\n", $streamParts);
	}

	/**
	 * @param string[] $objects 1-based object bodies (index 0 = object 1)
	 */
	private static function assemblePdf(array $objects): string
	{
		$pdf = "%PDF-1.4\n";
		$offsets = [];
		foreach ($objects as $i => $obj) {
			$offsets[$i + 1] = strlen($pdf);
			$pdf .= ($i + 1) . " 0 obj\n" . $obj . "\nendobj\n";
		}
		$xrefPos = strlen($pdf);
		$pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
		$pdf .= "0000000000 65535 f \n";
		for ($n = 1; $n <= count($objects); $n++) {
			$pdf .= sprintf("%010d 00000 n \n", $offsets[$n]);
		}
		$pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
		$pdf .= "startxref\n{$xrefPos}\n%%EOF";

		return $pdf;
	}

	/**
	 * Encode UTF-8 for PDF standard Type1 fonts (WinAnsi / Windows-1252).
	 * Does not use TRANSLIT — German umlauts and common punctuation stay correct.
	 *
	 * @internal Also used by structured PDF builders (month closure).
	 */
	public static function escapePdfText(string $s): string
	{
		$latin = self::utf8ToWindows1252($s);
		$out = '';
		for ($i = 0, $len = strlen($latin); $i < $len; $i++) {
			$c = $latin[$i];
			$o = ord($c);
			if ($o === 40 || $o === 41 || $o === 92) {
				$out .= '\\' . $c;
			} elseif ($o >= 32 && $o <= 126) {
				$out .= $c;
			} elseif ($o >= 128 && $o <= 255) {
				$out .= $c;
			} else {
				$out .= '?';
			}
		}

		return $out;
	}

	/**
	 * Convert UTF-8 to Windows-1252 bytes for PDF standard fonts (WinAnsi).
	 * Uses mb_convert_encoding first (reliable for German on typical PHP builds), then iconv,
	 * then a codepoint walk so ä/ö/ü/ß never silently disappear when libc iconv misbehaves.
	 */
	private static function utf8ToWindows1252(string $s): string
	{
		if ($s === '') {
			return '';
		}
		if (class_exists(\Normalizer::class)) {
			$norm = \Normalizer::normalize($s, \Normalizer::FORM_C);
			if (is_string($norm)) {
				$s = $norm;
			}
		}
		// Prefer punctuation that exists in WinAnsi / reads clearly in PDFs
		$s = strtr($s, [
			"\u{2013}" => '-',
			"\u{2014}" => '-',
			"\u{201C}" => '"',
			"\u{201D}" => '"',
			"\u{201E}" => '"',
			"\u{2026}" => '...',
			"\u{00A0}" => ' ',
		]);
		if (function_exists('mb_convert_encoding')) {
			$w = @mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
			if ($w !== false && $w !== '') {
				return $w;
			}
		}
		$i = @iconv('UTF-8', 'Windows-1252//IGNORE', $s);
		if ($i !== false && $i !== '') {
			return $i;
		}

		return self::utf8ToWindows1252ByCodepoint($s);
	}

	/**
	 * Last resort: map Unicode scalar values to Windows-1252 bytes (German + Latin-1 + common CP1252 punctuation).
	 */
	private static function utf8ToWindows1252ByCodepoint(string $s): string
	{
		if (!function_exists('mb_strlen') || !function_exists('mb_substr') || !function_exists('mb_ord')) {
			return @iconv('UTF-8', 'Windows-1252//IGNORE', $s) ?: '';
		}
		$out = '';
		$len = mb_strlen($s, 'UTF-8');
		for ($i = 0; $i < $len; $i++) {
			$ch = mb_substr($s, $i, 1, 'UTF-8');
			$cp = mb_ord($ch, 'UTF-8');
			if ($cp === false) {
				$out .= '?';
				continue;
			}
			$b = self::unicodeScalarToWindows1252Byte($cp);
			$out .= $b ?? '?';
		}

		return $out;
	}

	private static function unicodeScalarToWindows1252Byte(int $cp): ?string
	{
		if ($cp >= 0x20 && $cp <= 0x7E) {
			return chr($cp);
		}
		// ISO-8859-1 / Latin-1 block = identical bytes in Windows-1252 (incl. German umlauts)
		if ($cp >= 0xA0 && $cp <= 0xFF) {
			return chr($cp);
		}
		static $cp1252Unicode = [
			0x20AC => "\x80",
			0x201A => "\x82",
			0x0192 => "\x83",
			0x201E => "\x84",
			0x2026 => "\x85",
			0x2020 => "\x86",
			0x2021 => "\x87",
			0x02C6 => "\x88",
			0x2030 => "\x89",
			0x0160 => "\x8A",
			0x2039 => "\x8B",
			0x0152 => "\x8C",
			0x017D => "\x8E",
			0x2018 => "\x91",
			0x2019 => "\x92",
			0x201C => "\x93",
			0x201D => "\x94",
			0x2022 => "\x95",
			0x2013 => "\x96",
			0x2014 => "\x97",
			0x02DC => "\x98",
			0x2122 => "\x99",
			0x0161 => "\x9A",
			0x203A => "\x9B",
			0x0153 => "\x9C",
			0x017E => "\x9E",
			0x0178 => "\x9F",
		];

		return $cp1252Unicode[$cp] ?? null;
	}
}
