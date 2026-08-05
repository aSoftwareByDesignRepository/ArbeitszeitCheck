<?php

declare(strict_types=1);

/**
 * Admin vacation layers: Bachus days-always input (even in hours org unit).
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\Templates;

use PHPUnit\Framework\TestCase;

class AdminVacationLayersUnitCopyContractTest extends TestCase
{
	public function testBootstrapExposesDaysAlwaysInputContract(): void
	{
		$src = file_get_contents(dirname(__DIR__, 3) . '/templates/admin-vacation-layers.php');
		$this->assertNotFalse($src);
		$this->assertStringContainsString("'vacationUnit' => \$isVacationHours ? 'hours' : 'days'", $src);
		$this->assertStringContainsString("'amountMax' => 366", $src);
		$this->assertStringContainsString("'adminInputAlwaysDays' => true", $src);
		$this->assertStringContainsString('Enter annual vacation in days', $src);
		$this->assertStringNotContainsString('vacation hours an employee is entitled to', $src);
	}

	public function testJsUsesDaysInputWithHoursPreview(): void
	{
		$src = file_get_contents(dirname(__DIR__, 3) . '/js/admin-vacation-layers.js');
		$this->assertNotFalse($src);
		$this->assertStringContainsString('HOURS_MODE', $src);
		$this->assertStringContainsString('MANUAL_AMOUNT_MAX = 366', $src);
		$this->assertStringContainsString('Annual vacation days', $src);
		$this->assertStringContainsString('updateDaysHoursPreview', $src);
		$this->assertStringContainsString('fmtAmountCell', $src);
		$this->assertStringContainsString('convert to hours automatically', $src);
		$this->assertStringNotContainsString('Please enter the number of vacation hours per year', $src);
	}
}
