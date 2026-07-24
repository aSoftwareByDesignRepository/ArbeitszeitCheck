<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Db;

use OCA\ArbeitszeitCheck\Db\TimeEntry;
use PHPUnit\Framework\TestCase;

class TimeEntrySummaryMinBreakTest extends TestCase
{
	public function testSummaryExposesStampedMinBreakMinutes(): void
	{
		$entry = new TimeEntry();
		$entry->setUserId('alice');
		$entry->setStartTime(new \DateTime('2026-05-20 08:00:00'));
		$entry->setEndTime(new \DateTime('2026-05-20 16:00:00'));
		$entry->setStatus(TimeEntry::STATUS_COMPLETED);
		$entry->setCountableMinBreakMinutes(10);

		$summary = $entry->getSummary();
		self::assertSame(10, $summary['minBreakMinutes']);
	}

	public function testSummaryFallsBackToDefaultWhenUnstampedAndNoContainer(): void
	{
		$entry = new TimeEntry();
		$entry->setUserId('');
		$entry->setStartTime(new \DateTime('2026-05-20 08:00:00'));
		$entry->setEndTime(new \DateTime('2026-05-20 16:00:00'));
		$entry->setStatus(TimeEntry::STATUS_COMPLETED);

		$summary = $entry->getSummary();
		self::assertSame(15, $summary['minBreakMinutes']);
	}
}
