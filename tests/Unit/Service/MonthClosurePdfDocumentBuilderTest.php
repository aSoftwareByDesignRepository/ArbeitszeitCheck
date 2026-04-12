<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service;

use OCA\ArbeitszeitCheck\Db\MonthClosure;
use OCA\ArbeitszeitCheck\Service\MonthClosurePdfDocumentBuilder;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;

class MonthClosurePdfDocumentBuilderTest extends TestCase
{
	public function testPdfContainsMetadataNoEmbeddedJsonDump(): void
	{
		$l = $this->createMock(IL10N::class);
		$l->method('getLanguageCode')->willReturn('en');
		$l->method('t')->willReturnCallback(static function (string $text, array $parameters = []) {
			if ($text === 'month_closure_pdf_title') {
				return 'TITLE ' . ($parameters[0] ?? '');
			}
			if ($text === 'month_closure_pdf_holidays_detail') {
				return 'HOL ' . ($parameters[0] ?? '') . ' ' . ($parameters[1] ?? '');
			}
			if ($text === 'month_closure_pdf_footer') {
				return 'P' . ($parameters[0] ?? '') . '/' . ($parameters[1] ?? '') . ' ' . ($parameters[2] ?? '') . ' ' . ($parameters[3] ?? '');
			}
			if ($text === 'month_closure_pdf_integrity_note') {
				return 'INTEGRITY NOTE BODY';
			}

			return 'L';
		});

		$snap = [
			'schema' => 'arbeitszeitcheck.month_closure.v1',
			'year' => 2026,
			'month' => 3,
			'period' => ['start' => '2026-03-01', 'end' => '2026-03-31'],
			'report' => [
				'total_hours' => 1.0,
				'total_break_hours' => 0.0,
				'working_days' => 1,
				'total_overtime' => 0.0,
				'violations_count' => 0,
				'holiday_summary' => ['holiday_days' => 0, 'holiday_work_hours' => 0.0],
			],
			'time_entries' => [],
			'absences' => [],
		];
		$row = new MonthClosure();
		$row->setSnapshotHash(str_repeat('b', 64));
		$row->setPrevSnapshotHash(null);
		$row->setVersion(1);
		$row->setFinalizedAt(new \DateTime('2026-04-01 10:00:00', new \DateTimeZone('UTC')));
		$row->setFinalizedBy('testuser');

		$pdf = MonthClosurePdfDocumentBuilder::build($snap, $row, 'Test User', 'user1', $l, 'Test User (testuser)');

		$this->assertStringStartsWith("%PDF-1.4\n", $pdf);
		$this->assertStringContainsString('TITLE 2026-03', $pdf);
		$this->assertStringContainsString('2026-04-01T10:00:00Z', $pdf);
		$this->assertStringContainsString('bbbbbbbbbbbbbbbb', $pdf);
		$this->assertStringContainsString('/Lang (en-US)', $pdf);
		$this->assertStringContainsString('INTEGRITY NOTE BODY', $pdf);
		$this->assertStringNotContainsString('/BaseFont /Courier', $pdf);
		$this->assertStringNotContainsString('"time_entries"', $pdf);
		$this->assertStringNotContainsString('"schema"', $pdf);
		$this->assertMatchesRegularExpression('/P1\/\d+/', $pdf);
	}
}
