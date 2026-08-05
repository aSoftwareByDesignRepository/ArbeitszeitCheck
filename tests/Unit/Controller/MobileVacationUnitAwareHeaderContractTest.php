<?php

declare(strict_types=1);

/**
 * Mobile bootstrap must honour X-AZC-Vacation-Unit-Aware for Q8.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\Controller;

use PHPUnit\Framework\TestCase;

class MobileVacationUnitAwareHeaderContractTest extends TestCase
{
	public function testBootstrapAndDashboardPassHeaderAwareness(): void
	{
		$src = file_get_contents(dirname(__DIR__, 3) . '/lib/Controller/MobileBootstrapController.php');
		$this->assertNotFalse($src);
		$this->assertStringContainsString('isVacationUnitAwareClient', $src);
		$this->assertStringContainsString('X-AZC-Vacation-Unit-Aware', $src);
		$this->assertStringContainsString('getEmployeeWidgetData($userId, $unitAware)', $src);
		$this->assertStringContainsString('getEmployeeWidgetData(', $src);
		$this->assertMatchesRegularExpression(
			'/getEmployeeWidgetData\(\s*\$user->getUID\(\)\s*,\s*\$this->isVacationUnitAwareClient\(\)\s*\)/',
			$src
		);
	}
}
