<?php

declare(strict_types=1);

/**
 * FIFO vacation allocation: annual entitlement + opening carryover (Resturlaub),
 * with carryover usable only for working days on/before the configured expiry in each calendar year.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Service;

use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Db\Absence;
use OCA\ArbeitszeitCheck\Db\AbsenceMapper;
use OCA\ArbeitszeitCheck\Db\UserSettingsMapper;
use OCA\ArbeitszeitCheck\Db\UserWorkingTimeModelMapper;
use OCA\ArbeitszeitCheck\Db\VacationYearBalanceMapper;
use OCA\ArbeitszeitCheck\Support\VacationYearWindow;
use OCP\IConfig;

class VacationAllocationService
{
	/**
	 * Synthetic absence id for the prospective chunk in FIFO merge (must not collide with real rows).
	 */
	private const PROSPECTIVE_ABSENCE_PLACEHOLDER_ID = 2147483647;

	public function __construct(
		private IConfig $config,
		private AbsenceMapper $absenceMapper,
		private UserWorkingTimeModelMapper $userWorkingTimeModelMapper,
		private UserSettingsMapper $userSettingsMapper,
		private VacationYearBalanceMapper $vacationYearBalanceMapper,
		private HolidayService $holidayCalendarService,
		private VacationEntitlementEngine $vacationEntitlementEngine,
		private EntitlementSnapshotService $entitlementSnapshotService,
		private VacationProrationService $vacationProrationService,
		private VacationYearWindowResolver $yearWindowResolver,
	) {
	}

	/**
	 * Resolved expiry calendar date for year Y (last day on which carryover can be used for that year's balance).
	 */
	public function getCarryoverExpiryDateForYear(int $year): \DateTimeImmutable
	{
		$month = $this->getExpiryMonth();
		$day = $this->getExpiryDay();
		if (!checkdate($month, $day, $year)) {
			$day = (int)(new \DateTimeImmutable("$year-$month-01"))->format('t');
		}
		$d = \DateTimeImmutable::createFromFormat('Y-n-j', "$year-$month-$day");
		if ($d === false) {
			return new \DateTimeImmutable("$year-03-31");
		}
		return $d->setTime(0, 0, 0);
	}

	public function getExpiryMonth(): int
	{
		$v = (int)$this->config->getAppValue('arbeitszeitcheck', Constants::CONFIG_VACATION_CARRYOVER_EXPIRY_MONTH, '3');
		return max(1, min(12, $v));
	}

	public function getExpiryDay(): int
	{
		$v = (int)$this->config->getAppValue('arbeitszeitcheck', Constants::CONFIG_VACATION_CARRYOVER_EXPIRY_DAY, '31');
		return max(1, min(31, $v));
	}

	/**
	 * Optional global cap on opening carryover days (null = unlimited).
	 */
	public function getMaxCarryoverOpeningCap(): ?float
	{
		$raw = trim((string)$this->config->getAppValue('arbeitszeitcheck', Constants::CONFIG_VACATION_CARRYOVER_MAX_DAYS, ''));
		if ($raw === '') {
			return null;
		}
		$v = (float)str_replace(',', '.', $raw);
		if (!is_finite($v)) {
			return null;
		}
		return max(0.0, min(366.0, $v));
	}

	/**
	 * Clamp stored opening carryover to configured max (and 0–366).
	 */
	public function applyCapToOpeningBalance(float $carryoverDays): float
	{
		$v = max(0.0, min(366.0, $carryoverDays));
		$cap = $this->getMaxCarryoverOpeningCap();
		if ($cap !== null) {
			$v = min($v, $cap);
		}
		return $v;
	}

	/**
	 * Whether carryover from year Y's opening balance can still be used for new requests (date-only, server "today").
	 */
	public function isCarryoverUsableForNewRequests(int $year, ?\DateTimeInterface $asOf = null): bool
	{
		$asOf = $asOf ?? new \DateTime('today');
		$expiry = $this->getCarryoverExpiryDateForYear($year);
		$asDate = $asOf instanceof \DateTimeInterface
			? (new \DateTime($asOf->format('Y-m-d')))->setTime(0, 0, 0)
			: (clone $asOf)->setTime(0, 0, 0);
		$exp = new \DateTime($expiry->format('Y-m-d'));
		$exp->setTime(0, 0, 0);
		return $asDate <= $exp;
	}

	/**
	 * Whether a **prospective** vacation chunk (validate/create/update/approve) may still draw from the
	 * carryover pool for year Y.
	 *
	 * After the carryover deadline, new submissions cannot use carryover; requests **created on or before**
	 * the deadline may still do so when approved later (grandfathering). Purely prospective rows with no
	 * creation date (stats-only) use the deadline only.
	 */
	public function canProspectiveUseCarryoverPool(int $year, ?\DateTimeInterface $requestCreatedAt, \DateTimeInterface $validationDate): bool
	{
		if ($this->isCarryoverUsableForNewRequests($year, $validationDate)) {
			return true;
		}
		if ($requestCreatedAt === null) {
			return false;
		}
		$expiry = $this->getCarryoverExpiryDateForYear($year);
		$created = new \DateTime($requestCreatedAt->format('Y-m-d'));
		$created->setTime(0, 0, 0);
		$exp = new \DateTime($expiry->format('Y-m-d'));
		$exp->setTime(0, 0, 0);
		return $created <= $exp;
	}

	/**
	 * Annual vacation entitlement as a **float** (2 decimals, 0..366),
	 * matching {@see VacationEntitlementEngine::roundDays()} — the single
	 * canonical rounding policy (GAP-01 / REQ-ENT-12). Previously this
	 * method cast to int, which silently dropped half-days from
	 * tariff-driven entitlement and disagreed with
	 * {@see self::computeYearAllocation()} (which already used the float).
	 * The engine already rounds `days` at the boundary, so we just clamp
	 * defensively in case the engine contract regresses; this clamp is
	 * the same one the engine itself uses.
	 */
	public function getAnnualEntitlementDays(string $userId, ?\DateTimeInterface $asOfDate = null): float
	{
		try {
			$asOf = $asOfDate ?? new \DateTimeImmutable('today');
			$resolved = $this->vacationEntitlementEngine->computeForDate($userId, $asOf);
			$days = (float)($resolved['days'] ?? 0.0);
			if (!is_finite($days)) {
				$days = 0.0;
			}
			$days = round(max(0.0, min(366.0, $days)), 2, PHP_ROUND_HALF_UP);

			if ($this->yearWindowResolver->isAnniversaryMode()) {
				$window = $this->yearWindowResolver->resolveForUser($userId, $asOf);
				if ($window->missingEmploymentStart) {
					return 0.0;
				}
				// Anniversary window already represents one full entitlement year — no calendar Zwölftelung.
				return $days;
			}

			// Reduce to the part of the year actually covered by employment
			// (Zwölftelung / daily) when an employment period is configured.
			$proration = $this->vacationProrationService->prorateForYear($userId, (int)$asOf->format('Y'), $days);
			return (float)$proration['days'];
		} catch (\Throwable $e) {
			\OCP\Log\logger('arbeitszeitcheck')->error(
				'VacationAllocationService::getAnnualEntitlementDays: engine failed, using safe default',
				['exception' => $e, 'app' => 'arbeitszeitcheck']
			);
			return round((float)Constants::DEFAULT_VACATION_DAYS_PER_YEAR, 2, PHP_ROUND_HALF_UP);
		}
	}

	/**
	 * Split working days in calendar year Y for an absence into (before/on expiry) vs (after expiry).
	 *
	 * @return array{before: float, after: float}
	 */
	public function splitWorkingDaysForYearBeforeAfterExpiry(
		string $userId,
		\DateTime $absStart,
		\DateTime $absEnd,
		int $year,
	): array {
		$expiry = $this->getCarryoverExpiryDateForYear($year);
		return $this->splitWorkingDaysForYearSegment($userId, $absStart, $absEnd, $year, $expiry);
	}

	/**
	 * @return array{before: float, after: float}
	 */
	public function splitWorkingDaysForYearSegment(
		string $userId,
		\DateTime $absStart,
		\DateTime $absEnd,
		int $year,
		\DateTimeImmutable $expiryDate,
	): array {
		$yStart = new \DateTime("$year-01-01");
		$yEnd = new \DateTime("$year-12-31");
		return $this->splitWorkingDaysForRangeSegment($userId, $absStart, $absEnd, $yStart, $yEnd, $expiryDate);
	}

	/**
	 * Clip absence to [rangeStart, rangeEnd] inclusive, then split by carryover expiry.
	 *
	 * @return array{before: float, after: float}
	 */
	public function splitWorkingDaysForRangeSegment(
		string $userId,
		\DateTimeInterface $absStart,
		\DateTimeInterface $absEnd,
		\DateTimeInterface $rangeStart,
		\DateTimeInterface $rangeEndInclusive,
		\DateTimeImmutable $expiryDate,
	): array {
		$rStart = \DateTime::createFromInterface(
			\DateTimeImmutable::createFromInterface($rangeStart)->setTime(0, 0, 0)
		);
		$rEnd = \DateTime::createFromInterface(
			\DateTimeImmutable::createFromInterface($rangeEndInclusive)->setTime(0, 0, 0)
		);
		$segStart = \DateTime::createFromInterface(
			\DateTimeImmutable::createFromInterface($absStart)->setTime(0, 0, 0)
		);
		$segEnd = \DateTime::createFromInterface(
			\DateTimeImmutable::createFromInterface($absEnd)->setTime(0, 0, 0)
		);
		if ($segStart < $rStart) {
			$segStart = clone $rStart;
		}
		if ($segEnd > $rEnd) {
			$segEnd = clone $rEnd;
		}
		if ($segStart > $segEnd) {
			return ['before' => 0.0, 'after' => 0.0];
		}

		$expiry = new \DateTime($expiryDate->format('Y-m-d'));
		$expiry->setTime(0, 0, 0);

		$beforeEnd = $segEnd <= $expiry ? clone $segEnd : clone $expiry;
		$before = 0.0;
		if ($segStart <= $beforeEnd) {
			$before = $this->holidayCalendarService->computeWorkingDaysForUser($userId, $segStart, $beforeEnd);
		}

		$afterStart = (clone $expiry)->modify('+1 day');
		if ($segStart > $afterStart) {
			$afterStart = clone $segStart;
		}
		$after = 0.0;
		if ($afterStart <= $segEnd) {
			$after = $this->holidayCalendarService->computeWorkingDaysForUser($userId, $afterStart, $segEnd);
		}

		return ['before' => $before, 'after' => $after];
	}


	/**
	 * FIFO allocation for a vacation year window. Optionally exclude an absence (edit)
	 * and/or inject a prospective absence (create/validate).
	 *
	 * @return array<string, mixed>
	 *
	 * @param bool $persistEntitlementSnapshot When true, store/revise the entitlement snapshot row (audit).
	 *        Set false for read-only display paths (e.g. dashboard stats, widgets) to avoid write amplification,
	 *        lock churn, and failure modes unrelated to the allocation math.
	 */
	public function computeYearAllocation(
		string $userId,
		int $year,
		?int $excludeAbsenceId = null,
		?\DateTime $prospectiveStart = null,
		?\DateTime $prospectiveEnd = null,
		?\DateTimeInterface $asOf = null,
		?\DateTimeInterface $prospectiveRequestCreatedAt = null,
		bool $persistEntitlementSnapshot = true,
	): array {
		$asOf = $asOf ?? new \DateTime('today');
		$window = $this->resolveWindowForAllocation($userId, $year, $asOf);
		return $this->computeAllocationForWindow(
			$userId,
			$window,
			$excludeAbsenceId,
			$prospectiveStart,
			$prospectiveEnd,
			$asOf,
			$prospectiveRequestCreatedAt,
			$persistEntitlementSnapshot,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function computeAllocationForWindow(
		string $userId,
		VacationYearWindow $window,
		?int $excludeAbsenceId = null,
		?\DateTime $prospectiveStart = null,
		?\DateTime $prospectiveEnd = null,
		?\DateTimeInterface $asOf = null,
		?\DateTimeInterface $prospectiveRequestCreatedAt = null,
		bool $persistEntitlementSnapshot = true,
	): array {
		$asOf = $asOf ?? new \DateTime('today');
		$year = $window->balanceYearKey;
		$rangeStart = $window->startInclusive;
		$rangeEndInclusive = $window->lastInclusiveDay();

		$entitlementResolved = $this->vacationEntitlementEngine->computeForDate($userId, $asOf);
		$fullYearEntitlement = (float)$entitlementResolved['days'];

		if ($window->missingEmploymentStart) {
			$proration = [
				'days' => 0.0,
				'prorated' => false,
				'employed_in_year' => false,
				'method' => 'anniversary',
				'error' => Constants::VAC_YEAR_MISSING_START,
			];
			$annualEntitlement = 0.0;
			$fullYearEntitlement = 0.0;
		} elseif ($window->mode === VacationYearWindow::MODE_ANNIVERSARY) {
			$proration = [
				'days' => round($fullYearEntitlement, 2),
				'prorated' => false,
				'employed_in_year' => true,
				'method' => 'anniversary_full',
				'window_start' => $rangeStart->format('Y-m-d'),
				'window_end_inclusive' => $rangeEndInclusive->format('Y-m-d'),
			];
			$annualEntitlement = round($fullYearEntitlement, 2);
		} else {
			$proration = $this->vacationProrationService->prorateForYear($userId, $year, $fullYearEntitlement);
			$annualEntitlement = (float)$proration['days'];
		}

		$carryoverOpening = $this->vacationYearBalanceMapper->getCarryoverDays($userId, $year);
		$carryoverOpening = $this->applyCapToOpeningBalance($carryoverOpening);

		$expiry = $this->getCarryoverExpiryDateForYear($year);
		$carryoverExpiresOn = $carryoverOpening > 0.0001 ? $expiry->format('Y-m-d') : null;

		$list = $window->mode === VacationYearWindow::MODE_CALENDAR
			? $this->absenceMapper->findVacationApprovedOverlappingYear($userId, $year)
			: $this->absenceMapper->findVacationApprovedOverlappingRange(
				$userId,
				$rangeStart,
				$rangeEndInclusive
			);
		$merged = [];
		foreach ($list as $a) {
			if ($excludeAbsenceId !== null && $a->getId() === $excludeAbsenceId) {
				continue;
			}
			$merged[] = $a;
		}
		if ($prospectiveStart !== null && $prospectiveEnd !== null) {
			$p = new Absence();
			$p->setId(self::PROSPECTIVE_ABSENCE_PLACEHOLDER_ID);
			$p->setStartDate(clone $prospectiveStart);
			$p->setEndDate(clone $prospectiveEnd);
			$merged[] = $p;
		}
		usort($merged, function (Absence $a, Absence $b) {
			$as = $a->getStartDate();
			$bs = $b->getStartDate();
			if ($as == $bs) {
				return $a->getId() <=> $b->getId();
			}
			return $as <=> $bs;
		});

		$carryoverPool = $carryoverOpening;
		$annualPool = $annualEntitlement;
		$usedTotal = 0.0;
		$valid = true;
		$shortfall = 0.0;

		foreach ($merged as $absence) {
			$start = $absence->getStartDate();
			$end = $absence->getEndDate();
			if (!$start || !$end) {
				continue;
			}
			$split = $this->splitWorkingDaysForRangeSegment(
				$userId,
				$start,
				$end,
				$rangeStart,
				$rangeEndInclusive,
				$expiry
			);
			$wdBefore = $split['before'];
			$wdAfter = $split['after'];
			$chunk = $wdBefore + $wdAfter;
			$usedTotal += $chunk;

			$isProspective = ($absence->getId() === self::PROSPECTIVE_ABSENCE_PLACEHOLDER_ID);
			if ($isProspective && !$this->canProspectiveUseCarryoverPool($year, $prospectiveRequestCreatedAt, $asOf)) {
				$need = $chunk;
				$fromA = min($annualPool, $need);
				$annualPool -= $fromA;
				$need -= $fromA;
				if ($need > 0.0001) {
					$valid = false;
					$shortfall += $need;
				}
				continue;
			}

			$need = $wdBefore;
			$fromC = min($carryoverPool, $need);
			$carryoverPool -= $fromC;
			$need -= $fromC;
			$fromA = min($annualPool, $need);
			$annualPool -= $fromA;
			$need -= $fromA;
			if ($need > 0.0001) {
				$valid = false;
				$shortfall += $need;
			}

			$need2 = $wdAfter;
			$fromA2 = min($annualPool, $need2);
			$annualPool -= $fromA2;
			$need2 -= $fromA2;
			if ($need2 > 0.0001) {
				$valid = false;
				$shortfall += $need2;
			}
		}

		$carryoverRem = max(0.0, $carryoverPool);
		$annualRem = max(0.0, $annualPool);

		$carryoverUsable = $carryoverRem;
		if (!$this->isCarryoverUsableForNewRequests($year, $asOf)) {
			$carryoverUsable = 0.0;
		}

		$totalForNew = $annualRem + $carryoverUsable;

		$entitlementTrace = (array)$entitlementResolved['trace'];
		if (!empty($proration['prorated']) || !($proration['employed_in_year'] ?? true) || isset($proration['error'])) {
			$entitlementTrace['proration'] = $proration;
		}
		if ($window->mode === VacationYearWindow::MODE_ANNIVERSARY) {
			$entitlementTrace['vacation_year_window'] = $window->toArray();
		}

		$result = [
			'year' => $year,
			'entitlement' => $annualEntitlement,
			'entitlement_full_year' => round($fullYearEntitlement, 2),
			'proration' => $proration,
			'carryover_opening' => round($carryoverOpening, 4),
			'carryover_remaining_after_approved' => round($carryoverRem, 4),
			'annual_remaining_after_approved' => round($annualRem, 4),
			'total_remaining_for_new_requests' => round(max(0.0, $totalForNew), 4),
			'carryover_expires_on' => $carryoverExpiresOn,
			'carryover_usable_for_new_requests' => round($carryoverUsable, 4),
			'used_total_working_days' => round($usedTotal, 4),
			'allocation_valid' => $valid && !$window->missingEmploymentStart,
			'shortfall' => round($shortfall, 4),
			'entitlement_source' => $entitlementResolved['source'],
			'entitlement_rule_set_id' => $entitlementResolved['ruleSetId'],
			'entitlement_trace' => $entitlementTrace,
			'vacation_year_mode' => $window->mode,
			'vacation_year_label' => $window->label,
			'vacation_year_start' => $rangeStart->format('Y-m-d'),
			'vacation_year_end_inclusive' => $rangeEndInclusive->format('Y-m-d'),
			'vacation_year_error' => $window->missingEmploymentStart ? Constants::VAC_YEAR_MISSING_START : null,
		];

		if ($persistEntitlementSnapshot) {
			$fingerprint = 'invalid';
			try {
				$enc = json_encode(
					[$entitlementResolved['source'], $entitlementResolved['ruleSetId'], $entitlementTrace, $annualEntitlement],
					JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
				);
				$fingerprint = hash('sha256', $enc);
			} catch (\JsonException) {
				$fingerprint = hash('sha256', (string)$annualEntitlement . '|' . (string)$entitlementResolved['source']);
			}
			$this->entitlementSnapshotService->store(
				$userId,
				$year,
				$asOf,
				$annualEntitlement,
				(string)$entitlementResolved['source'],
				$entitlementResolved['ruleSetId'],
				$entitlementTrace,
				'system',
				$fingerprint
			);
		}

		return $result;
	}

	private function resolveWindowForAllocation(
		string $userId,
		int $year,
		\DateTimeInterface $asOf,
	): VacationYearWindow {
		if (!$this->yearWindowResolver->isAnniversaryMode()) {
			return $this->yearWindowResolver->resolveCalendarYear($year);
		}

		$asOfWindow = $this->yearWindowResolver->resolveForUser($userId, $asOf);
		if ($asOfWindow->missingEmploymentStart) {
			return $asOfWindow;
		}
		if ($asOfWindow->balanceYearKey === $year) {
			return $asOfWindow;
		}

		return $this->yearWindowResolver->resolveAnniversaryForUserBalanceYear($userId, $year);
	}
}
