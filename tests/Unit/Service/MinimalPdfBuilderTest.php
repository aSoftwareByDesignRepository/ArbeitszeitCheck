<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service;

use OCA\ArbeitszeitCheck\Service\MinimalPdfBuilder;
use PHPUnit\Framework\TestCase;

class MinimalPdfBuilderTest extends TestCase
{
	public function testEscapePdfTextPreservesGermanUmlauts(): void
	{
		$s = 'Kurzübersicht Überstunden Verstöße Größe';
		$esc = MinimalPdfBuilder::escapePdfText($s);
		$back = @iconv('Windows-1252', 'UTF-8', $esc);
		$this->assertIsString($back);
		$this->assertSame($s, $back);
	}

	public function testEscapePdfTextEscapesParenthesesAndBackslash(): void
	{
		$esc = MinimalPdfBuilder::escapePdfText('a(b)\\c');
		$this->assertStringContainsString('\\(', $esc);
		$this->assertStringContainsString('\\)', $esc);
		$this->assertStringContainsString('\\\\', $esc);
	}

	public function testEscapePdfTextReplacesUnmappableWithQuestionMark(): void
	{
		$esc = MinimalPdfBuilder::escapePdfText("ASCII \u{1F600}");
		$this->assertStringContainsString('?', $esc);
	}

	public function testBuildPdfDeclaresWinAnsiEncodingForHelvetica(): void
	{
		$pdf = MinimalPdfBuilder::build('Titel', ['Zeile äöüß']);
		$this->assertStringContainsString('/Encoding /WinAnsiEncoding', $pdf);
	}
}
