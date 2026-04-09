<?php

declare(strict_types=1);

/**
 * Tiny PDF (PDF 1.4) with Helvetica text — no external dependencies.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Service;

final class MinimalPdfBuilder
{
	/**
	 * @param string[] $lines UTF-8 lines (will be escaped for PDF WinAnsi subset)
	 */
	public static function build(string $title, array $lines): string
	{
		$escapedTitle = self::escapePdfText($title);
		$streamParts = [];
		$streamParts[] = 'BT /F1 11 Tf 56 760 Td (' . $escapedTitle . ') Tj ET';
		$y = 735;
		foreach ($lines as $line) {
			$escaped = self::escapePdfText($line);
			$streamParts[] = 'BT /F1 9 Tf 56 ' . $y . ' Td (' . $escaped . ') Tj ET';
			$y -= 12;
			if ($y < 56) {
				break;
			}
		}
		$stream = implode("\n", $streamParts);
		$streamLen = strlen($stream);

		$objects = [];
		$objects[] = "<< /Type /Catalog /Pages 2 0 R >>";
		$objects[] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
		$page = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>";
		$objects[] = $page;
		$objects[] = "<< /Length {$streamLen} >>\nstream\n{$stream}\nendstream";
		$objects[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";

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

	private static function escapePdfText(string $s): string
	{
		$latin = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $s) ?: $s;
		$out = '';
		for ($i = 0, $len = strlen($latin); $i < $len; $i++) {
			$c = $latin[$i];
			$o = ord($c);
			if ($o === 40 || $o === 41 || $o === 92) {
				$out .= '\\' . $c;
			} elseif ($o >= 32 && $o <= 126) {
				$out .= $c;
			} else {
				$out .= '?';
			}
		}
		return $out;
	}
}
