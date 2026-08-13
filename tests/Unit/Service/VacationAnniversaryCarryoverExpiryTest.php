<?php

declare(strict_types=1);

/**
 * Q2 anniversary carryover expiry (N months after window start).
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service;

use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Db\AbsenceMapper;
use OCA\ArbeitszeitCheck\Db\UserSettingsMapper;
use OCA\ArbeitszeitCheck\Db\UserWorkingTimeModelMapper;
use OCA\ArbeitszeitCheck\Db\VacationYearBalanceMapper;
use OCA\ArbeitszeitCheck\Service\EntitlementSnapshotService;
use OCA\ArbeitszeitCheck\Service\HolidayService;
use OCA\ArbeitszeitCheck\Service\UserEmploymentSettingsService;
use OCA\ArbeitszeitCheck\Service\VacationAllocationService;
use OCA\ArbeitszeitCheck\Service\VacationEntitlementEngine;
use OCA\ArbeitszeitCheck\Service\VacationProrationService;
use OCA\ArbeitszeitCheck\Service\VacationYearWindowResolver;
use OCA\ArbeitszeitCheck\Support\VacationYearWindow;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;

class VacationAnniversaryCarryoverExpiryTest extends TestCase
{
	private function service(IConfig $config): VacationAllocationService
	{
		$modeConfig = $this->createMock(IConfig::class);
		$modeConfig->method('getAppValue')->willReturn(Constants::VACATION_YEAR_MODE_CALENDAR);
		$employment = $this->createMock(UserEmploymentSettingsService::class);
		$employment->method('getEmploymentStart')->willReturn(null);
		$resolver = new VacationYearWindowResolver($modeConfig, $employment);

		return new VacationAllocationService(
			$config,
			$this->createMock(AbsenceMapper::class),
			$this->createMock(UserWorkingTimeModelMapper::class),
			$this->createMock(UserSettingsMapper::class),
			$this->createMock(VacationYearBalanceMapper::class),
			$this->createMock(HolidayService::class),
			$this->createMock(VacationEntitlementEngine::class),
			$this->createMock(EntitlementSnapshotService::class),
			$this->createMock(VacationProrationService::class),
			$resolver,
			null,
		);
	}

	public function testCalendarExpiryUsesMonthDay(): void
	{
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnMap([
			['arbeitszeitcheck', Constants::CONFIG_VACATION_CARRYOVER_EXPIRY_MONTH, '3', '3'],
			['arbeitszeitcheck', Constants::CONFIG_VACATION_CARRYOVER_EXPIRY_DAY, '31', '31'],
		]);
		$s = $this->service($config);
		$window = new VacationYearWindow(
			VacationYearWindow::MODE_CALENDAR,
			2026,
			new \DateTimeImmutable('2026-01-01'),
			new \DateTimeImmutable('2027-01-01'),
			'2026',
			false,
		);
		$this->assertSame('2026-03-31', $s->getCarryoverExpiryDateForWindow($window)->format('Y-m-d'));
	}

	public function testAnniversaryExpiryIsNMonthsAfterStart(): void
	{
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnMap([
			['arbeitszeitcheck', Constants::CONFIG_VACATION_CARRYOVER_EXPIRY_MONTH, '3', '3'],
			['arbeitszeitcheck', Constants::CONFIG_VACATION_CARRYOVER_EXPIRY_DAY, '31', '31'],
		]);
		$s = $this->service($config);
		$window = new VacationYearWindow(
			VacationYearWindow::MODE_ANNIVERSARY,
			2025,
			new \DateTimeImmutable('2025-07-01'),
			new \DateTimeImmutable('2026-07-01'),
			'2025-07-01 → 2026-06-30',
			false,
		);
		$this->assertSame('2025-09-30', $s->getCarryoverExpiryDateForWindow($window)->format('Y-m-d'));
		$this->assertTrue($s->isCarryoverUsableForWindow($window, new \DateTimeImmutable('2025-09-30')));
		$this->assertFalse($s->isCarryoverUsableForWindow($window, new \DateTimeImmutable('2025-10-01')));
	}

	public function testAnniversaryJan1WithDefaultThreeMonthsMatchesMarch31(): void
	{
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnMap([
			['arbeitszeitcheck', Constants::CONFIG_VACATION_CARRYOVER_EXPIRY_MONTH, '3', '3'],
			['arbeitszeitcheck', Constants::CONFIG_VACATION_CARRYOVER_EXPIRY_DAY, '31', '15'],
		]);
		$s = $this->service($config);
		$window = new VacationYearWindow(
			VacationYearWindow::MODE_ANNIVERSARY,
			2026,
			new \DateTimeImmutable('2026-01-01'),
			new \DateTimeImmutable('2027-01-01'),
			'2026-01-01 → 2026-12-31',
			false,
		);
		$this->assertSame('2026-03-31', $s->getCarryoverExpiryDateForWindow($window)->format('Y-m-d'));
	}
}
