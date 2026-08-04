<?php

declare(strict_types=1);

/**
 * Orchestrates premium surcharge classification for a user/period.
 *
 * Orthogonal to OvertimeService Saldo / Auszahlung.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Service;

use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Db\TimeEntry;
use OCA\ArbeitszeitCheck\Db\TimeEntryMapper;
use OCA\ArbeitszeitCheck\Db\UserWorkingTimeModelMapper;
use OCA\ArbeitszeitCheck\Db\WorkingTimeModelMapper;
use OCA\ArbeitszeitCheck\Support\PremiumPolicy;
use OCA\ArbeitszeitCheck\Support\PremiumSurchargeClassifier;
use OCA\ArbeitszeitCheck\Support\WeekdaySchedule;
use OCP\IConfig;

class PremiumSurchargeService
{
	public function __construct(
		private readonly IConfig $config,
		private readonly TimeEntryMapper $timeEntryMapper,
		private readonly HolidayService $holidayService,
		private readonly UserWorkingTimeModelMapper $userWorkingTimeModelMapper,
		private readonly WorkingTimeModelMapper $workingTimeModelMapper,
		private readonly PremiumSurchargeClassifier $classifier = new PremiumSurchargeClassifier(),
	) {
	}

	public function isEnabled(): bool
	{
		return $this->config->getAppValue(
			'arbeitszeitcheck',
			Constants::CONFIG_PREMIUM_SURCHARGES_ENABLED,
			'0'
		) === '1';
	}

	public function getPolicy(): ?PremiumPolicy
	{
		$raw = $this->config->getAppValue('arbeitszeitcheck', Constants::CONFIG_PREMIUM_POLICY_JSON, '');
		if ($raw === '') {
			return null;
		}
		try {
			$decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
		} catch (\JsonException) {
			return null;
		}
		if (!is_array($decoded)) {
			return null;
		}

		return PremiumPolicy::tryFromArray($decoded);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getPolicyArrayOrDefault(): array
	{
		$policy = $this->getPolicy();
		return $policy !== null ? $policy->toArray() : PremiumPolicy::atStarterPreset();
	}

	/**
	 * Summarise premiums for a user in [start, end] (inclusive calendar days).
	 *
	 * @return array<string, mixed>
	 */
	public function summariseForUser(string $userId, \DateTimeInterface $start, \DateTimeInterface $end): array
	{
		if (!$this->isEnabled()) {
			return [
				'enabled' => false,
				'buckets' => [],
				'total_classified_hours' => 0.0,
				'total_valued_hours' => 0.0,
				'stacking' => null,
				'note' => 'premium_disabled',
			];
		}

		$policy = $this->getPolicy();
		if ($policy === null || $policy->getEnabledCategories() === []) {
			return [
				'enabled' => true,
				'buckets' => [],
				'total_classified_hours' => 0.0,
				'total_valued_hours' => 0.0,
				'stacking' => $policy?->getStacking(),
				'note' => 'premium_policy_empty',
			];
		}

		$rangeStart = \DateTime::createFromInterface(
			\DateTimeImmutable::createFromInterface($start)->setTime(0, 0, 0)
		);
		$rangeEndExclusive = \DateTime::createFromInterface(
			\DateTimeImmutable::createFromInterface($end)->setTime(0, 0, 0)->modify('+1 day')
		);

		$entries = $this->timeEntryMapper->findByUserAndDateRange($userId, $rangeStart, $rangeEndExclusive);
		$intervals = [];
		foreach ($entries as $entry) {
			if ($entry->getStatus() !== TimeEntry::STATUS_COMPLETED || $entry->getEndTime() === null) {
				continue;
			}
			foreach ($this->workIntervalsFromEntry($entry) as $iv) {
				$intervals[] = $iv;
			}
		}

		[$schedule, $dailyFallback] = $this->resolveDailyTarget($userId);

		$result = $this->classifier->classify(
			$intervals,
			$policy,
			fn (\DateTimeImmutable $day): bool => $this->holidayService->getHolidayWeightForUser($userId, \DateTime::createFromImmutable($day)) >= 1.0,
			function (\DateTimeImmutable $day) use ($schedule, $dailyFallback): float {
				if ($schedule !== null) {
					$n = (int)$day->format('N');
					$map = [1 => 'mon', 2 => 'tue', 3 => 'wed', 4 => 'thu', 5 => 'fri', 6 => 'sat', 7 => 'sun'];
					return $schedule->netHoursForWeekday($map[$n] ?? 'mon');
				}
				// Legacy: weekends contribute 0 daily OT threshold (Sat/Sun not work days).
				$n = (int)$day->format('N');
				return ($n >= 6) ? 0.0 : $dailyFallback;
			},
		);

		$result['enabled'] = true;
		$result['period_start'] = $rangeStart->format('Y-m-d');
		$result['period_end'] = \DateTimeImmutable::createFromInterface($end)->format('Y-m-d');
		$result['currency_mode'] = 'hours_only';
		$result['orthogonal_to_saldo'] = true;

		return $result;
	}

	/**
	 * @return list<array{0: \DateTimeImmutable, 1: \DateTimeImmutable}>
	 */
	public function workIntervalsFromEntry(TimeEntry $entry): array
	{
		$start = \DateTimeImmutable::createFromMutable($entry->getStartTime());
		$end = \DateTimeImmutable::createFromMutable($entry->getEndTime());
		if ($end <= $start) {
			return [];
		}

		$breaks = $this->breakIntervals($entry, $start->getTimezone());
		if ($breaks === []) {
			return [[$start, $end]];
		}

		$work = [];
		$cursor = $start;
		foreach ($breaks as [$bStart, $bEnd]) {
			if ($bEnd <= $cursor || $bStart >= $end) {
				continue;
			}
			$bs = $bStart < $cursor ? $cursor : $bStart;
			$be = $bEnd > $end ? $end : $bEnd;
			if ($cursor < $bs) {
				$work[] = [$cursor, $bs];
			}
			$cursor = $be > $cursor ? $be : $cursor;
		}
		if ($cursor < $end) {
			$work[] = [$cursor, $end];
		}

		return $work;
	}

	/**
	 * @return list<array{0: \DateTimeImmutable, 1: \DateTimeImmutable}>
	 */
	private function breakIntervals(TimeEntry $entry, \DateTimeZone $tz): array
	{
		$out = [];
		$raw = $entry->getBreaks();
		if (is_string($raw) && $raw !== '') {
			$decoded = json_decode($raw, true);
			if (is_array($decoded)) {
				foreach ($decoded as $break) {
					if (!is_array($break) || !isset($break['start'], $break['end'])) {
						continue;
					}
					try {
						$bs = (new \DateTimeImmutable((string)$break['start']))->setTimezone($tz);
						$be = (new \DateTimeImmutable((string)$break['end']))->setTimezone($tz);
					} catch (\Exception) {
						continue;
					}
					if ($be > $bs) {
						$out[] = [$bs, $be];
					}
				}
			}
		}
		$bStart = $entry->getBreakStartTime();
		$bEnd = $entry->getBreakEndTime();
		if ($bStart !== null && $bEnd !== null && $bEnd > $bStart) {
			$out[] = [
				\DateTimeImmutable::createFromMutable($bStart),
				\DateTimeImmutable::createFromMutable($bEnd),
			];
		}
		usort($out, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

		return $out;
	}

	/**
	 * @return array{0: ?WeekdaySchedule, 1: float}
	 */
	private function resolveDailyTarget(string $userId): array
	{
		$daily = 8.0;
		$schedule = null;
		$userModel = $this->userWorkingTimeModelMapper->findCurrentByUser($userId);
		if ($userModel) {
			try {
				$model = $this->workingTimeModelMapper->find($userModel->getWorkingTimeModelId());
				$schedule = $model->getWeekdaySchedule();
				$daily = $schedule !== null
					? $schedule->averageDailyNetHours()
					: (float)$model->getDailyHours();
			} catch (\Throwable) {
				// defaults
			}
		}

		return [$schedule, $daily];
	}
}
