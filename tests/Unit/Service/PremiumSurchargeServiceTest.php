<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service;

use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Db\TimeEntry;
use OCA\ArbeitszeitCheck\Db\TimeEntryMapper;
use OCA\ArbeitszeitCheck\Db\UserWorkingTimeModelMapper;
use OCA\ArbeitszeitCheck\Db\WorkingTimeModelMapper;
use OCA\ArbeitszeitCheck\Service\HolidayService;
use OCA\ArbeitszeitCheck\Service\PremiumSurchargeService;
use OCA\ArbeitszeitCheck\Support\PremiumPolicy;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;

class PremiumSurchargeServiceTest extends TestCase
{
	private function service(IConfig $config, ?TimeEntryMapper $entries = null): PremiumSurchargeService
	{
		return new PremiumSurchargeService(
			$config,
			$entries ?? $this->createMock(TimeEntryMapper::class),
			$this->createMock(HolidayService::class),
			$this->createMock(UserWorkingTimeModelMapper::class),
			$this->createMock(WorkingTimeModelMapper::class),
		);
	}

	private function completedEntry(\DateTime $start, \DateTime $end, ?string $breaksJson = null): TimeEntry
	{
		$entry = new TimeEntry();
		$entry->setUserId('u1');
		$entry->setStatus(TimeEntry::STATUS_COMPLETED);
		$entry->setStartTime($start);
		$entry->setEndTime($end);
		$entry->setBreaks($breaksJson);
		$entry->setCreatedAt(clone $start);
		$entry->setUpdatedAt(clone $end);

		return $entry;
	}

	public function testDisabledReturnsStableShapeWithoutTouchingEntries(): void
	{
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static function (string $app, string $key, $default = '') {
				if ($key === Constants::CONFIG_PREMIUM_SURCHARGES_ENABLED) {
					return '0';
				}
				return $default;
			}
		);
		$entries = $this->createMock(TimeEntryMapper::class);
		$entries->expects($this->never())->method('findByUserAndDateRange');

		$result = $this->service($config, $entries)->summariseForUser(
			'u1',
			new \DateTime('2026-08-01'),
			new \DateTime('2026-08-31')
		);

		$this->assertFalse($result['enabled']);
		$this->assertSame([], $result['buckets']);
		$this->assertSame('premium_disabled', $result['note']);
		$this->assertSame(0.0, $result['total_classified_hours']);
	}

	public function testEnabledClassifiesCompletedSundayEntry(): void
	{
		$config = $this->createMock(IConfig::class);
		$policy = PremiumPolicy::atStarterPreset();
		$config->method('getAppValue')->willReturnCallback(
			static function (string $app, string $key, $default = '') use ($policy) {
				if ($key === Constants::CONFIG_PREMIUM_SURCHARGES_ENABLED) {
					return '1';
				}
				if ($key === Constants::CONFIG_PREMIUM_POLICY_JSON) {
					return json_encode($policy, JSON_THROW_ON_ERROR);
				}
				return $default;
			}
		);

		$tz = new \DateTimeZone('Europe/Vienna');
		$entry = $this->completedEntry(
			new \DateTime('2026-08-09 10:00:00', $tz),
			new \DateTime('2026-08-09 12:00:00', $tz)
		);

		$entries = $this->createMock(TimeEntryMapper::class);
		$entries->method('findByUserAndDateRange')->willReturn([$entry]);

		$holidays = $this->createMock(HolidayService::class);
		$holidays->method('getHolidayWeightForUser')->willReturn(0.0);

		$svc = new PremiumSurchargeService(
			$config,
			$entries,
			$holidays,
			$this->createMock(UserWorkingTimeModelMapper::class),
			$this->createMock(WorkingTimeModelMapper::class),
		);

		$result = $svc->summariseForUser('u1', new \DateTime('2026-08-01'), new \DateTime('2026-08-31'));
		$this->assertTrue($result['enabled']);
		$this->assertTrue($result['orthogonal_to_saldo']);
		$sunday = null;
		foreach ($result['buckets'] as $b) {
			if ($b['id'] === 'sunday') {
				$sunday = $b;
			}
		}
		$this->assertNotNull($sunday);
		$this->assertEqualsWithDelta(2.0, $sunday['hours'], 0.01);
	}

	public function testBreaksAreExcludedFromWorkIntervals(): void
	{
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturn('0');
		$svc = $this->service($config);

		$tz = new \DateTimeZone('Europe/Vienna');
		$entry = $this->completedEntry(
			new \DateTime('2026-08-03 08:00:00', $tz),
			new \DateTime('2026-08-03 17:00:00', $tz),
			json_encode([
				['start' => '2026-08-03T12:00:00+02:00', 'end' => '2026-08-03T12:45:00+02:00'],
			], JSON_THROW_ON_ERROR)
		);

		$intervals = $svc->workIntervalsFromEntry($entry);
		$this->assertCount(2, $intervals);
		$total = 0;
		foreach ($intervals as [$a, $b]) {
			$total += $b->getTimestamp() - $a->getTimestamp();
		}
		// 9h clocked − 45 min break = 8.25 h
		$this->assertSame(8 * 3600 + 15 * 60, $total);
	}
}
