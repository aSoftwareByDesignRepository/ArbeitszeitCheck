<?php

declare(strict_types=1);

/**
 * Vacation approval FOR UPDATE must follow vacation-year windows (anniversary-aware).
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service;

use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Service\UserEmploymentSettingsService;
use OCA\ArbeitszeitCheck\Service\VacationYearWindowResolver;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;

class AbsenceVacationApprovalScopeLockTest extends TestCase
{
	public function testLockVacationApprovalScopeUsesWindowsOverlappingRange(): void
	{
		$src = file_get_contents(dirname(__DIR__, 2) . '/../lib/Service/AbsenceService.php');
		$this->assertNotFalse($src);
		$this->assertMatchesRegularExpression(
			'/function lockVacationApprovalScope\(.*?windowsOverlappingRange\(/s',
			$src
		);
		$this->assertStringContainsString('lastInclusiveDay()', $src);
		$this->assertStringContainsString('hire-anniversary windows', $src);
	}

	public function testAnniversaryWindowUnionCoversCrossCalendarYearRequest(): void
	{
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static function (string $app, string $key, $default = '') {
				if ($key === Constants::CONFIG_VACATION_YEAR_MODE) {
					return Constants::VACATION_YEAR_MODE_ANNIVERSARY;
				}
				return $default;
			}
		);
		$employment = $this->createMock(UserEmploymentSettingsService::class);
		$employment->method('getEmploymentStart')
			->with('alice')
			->willReturn(new \DateTimeImmutable('2026-07-01'));
		$resolver = new VacationYearWindowResolver($config, $employment);

		$windows = $resolver->windowsOverlappingRange(
			'alice',
			new \DateTimeImmutable('2026-12-20'),
			new \DateTimeImmutable('2027-01-10')
		);
		$this->assertCount(1, $windows);
		$this->assertSame('2026-07-01', $windows[0]->startInclusive->format('Y-m-d'));
		$this->assertSame('2027-06-30', $windows[0]->lastInclusiveDay()->format('Y-m-d'));

		$scopeStart = $windows[0]->startInclusive;
		$scopeEnd = $windows[0]->lastInclusiveDay();
		foreach ($windows as $window) {
			if ($window->startInclusive < $scopeStart) {
				$scopeStart = $window->startInclusive;
			}
			if ($window->lastInclusiveDay() > $scopeEnd) {
				$scopeEnd = $window->lastInclusiveDay();
			}
		}
		// Calendar-year lock would have been 2026-01-01..2027-12-31 and miss the
		// shared hire-year pool spanning only Jul→Jun — assert we stay on hire bounds.
		$this->assertSame('2026-07-01', $scopeStart->format('Y-m-d'));
		$this->assertSame('2027-06-30', $scopeEnd->format('Y-m-d'));
	}
}
