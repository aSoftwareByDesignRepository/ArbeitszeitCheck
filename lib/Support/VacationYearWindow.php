<?php

declare(strict_types=1);

/**
 * Resolved vacation year window (calendar or employment anniversary).
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Support;

/**
 * Half-open period [startInclusive, endExclusive).
 * balanceYearKey is the int used with at_vacation_year_balance.year.
 */
final class VacationYearWindow
{
	public const MODE_CALENDAR = 'calendar';
	public const MODE_ANNIVERSARY = 'anniversary';

	public function __construct(
		public readonly string $mode,
		public readonly int $balanceYearKey,
		public readonly \DateTimeImmutable $startInclusive,
		public readonly \DateTimeImmutable $endExclusive,
		public readonly string $label,
		public readonly bool $missingEmploymentStart = false,
	) {
	}

	public function lastInclusiveDay(): \DateTimeImmutable
	{
		return $this->endExclusive->modify('-1 day');
	}

	public function contains(\DateTimeInterface $day): bool
	{
		$d = \DateTimeImmutable::createFromInterface($day)->setTime(0, 0, 0);
		return $d >= $this->startInclusive && $d < $this->endExclusive;
	}

	/**
	 * @return array{mode: string, balance_year: int, start: string, end_inclusive: string, label: string, missing_employment_start: bool}
	 */
	public function toArray(): array
	{
		return [
			'mode' => $this->mode,
			'balance_year' => $this->balanceYearKey,
			'start' => $this->startInclusive->format('Y-m-d'),
			'end_inclusive' => $this->lastInclusiveDay()->format('Y-m-d'),
			'label' => $this->label,
			'missing_employment_start' => $this->missingEmploymentStart,
		];
	}
}
