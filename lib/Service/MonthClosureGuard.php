<?php

declare(strict_types=1);

/**
 * Central guard: block mutations when a calendar month is finalized.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Service;

use OCA\ArbeitszeitCheck\Db\Absence;
use OCA\ArbeitszeitCheck\Db\TimeEntry;

class MonthClosureGuard
{
	private MonthClosureService $monthClosureService;

	public function __construct(MonthClosureService $monthClosureService)
	{
		$this->monthClosureService = $monthClosureService;
	}

	public function assertTimeEntryMutable(TimeEntry $entry): void
	{
		$start = $entry->getStartTime();
		$end = $entry->getEndTime() ?? $start;
		if ($start === null) {
			return;
		}
		$this->monthClosureService->assertDateRangeMutable($entry->getUserId(), $start, $end);
	}

	public function assertAbsenceMutable(Absence $absence): void
	{
		$start = $absence->getStartDate();
		$end = $absence->getEndDate();
		$this->monthClosureService->assertDateRangeMutable($absence->getUserId(), $start, $end);
	}

	/**
	 * @param \DateTime $day Any instant on that calendar day
	 */
	public function assertUserDayMutable(string $userId, \DateTime $day): void
	{
		$s = clone $day;
		$s->setTime(0, 0, 0);
		$e = clone $day;
		$e->setTime(23, 59, 59);
		$this->monthClosureService->assertDateRangeMutable($userId, $s, $e);
	}
}
