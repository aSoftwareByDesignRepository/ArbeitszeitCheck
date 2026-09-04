<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\Util;

use OCA\ArbeitszeitCheck\Util\HalfDayVacationShortcut;
use PHPUnit\Framework\TestCase;

class HalfDayVacationShortcutTest extends TestCase
{
	public function testWeekdayKeepsToday(): void
	{
		$wed = new \DateTimeImmutable('2026-08-12'); // Wednesday
		$anchor = HalfDayVacationShortcut::anchorDate($wed);
		$this->assertSame('2026-08-12', $anchor->format('Y-m-d'));
		$this->assertTrue(HalfDayVacationShortcut::isSameCalendarDay($anchor, $wed));
	}

	public function testSaturdayJumpsToMonday(): void
	{
		$sat = new \DateTimeImmutable('2026-08-15');
		$anchor = HalfDayVacationShortcut::anchorDate($sat);
		$this->assertSame('2026-08-17', $anchor->format('Y-m-d'));
		$this->assertSame(1, (int)$anchor->format('N'));
		$this->assertFalse(HalfDayVacationShortcut::isSameCalendarDay($anchor, $sat));
	}

	public function testSundayJumpsToMonday(): void
	{
		$sun = new \DateTimeImmutable('2026-08-16');
		$anchor = HalfDayVacationShortcut::anchorDate($sun);
		$this->assertSame('2026-08-17', $anchor->format('Y-m-d'));
	}
}
