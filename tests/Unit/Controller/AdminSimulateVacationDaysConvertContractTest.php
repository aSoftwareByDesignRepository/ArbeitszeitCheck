<?php

declare(strict_types=1);

/**
 * Simulator draftPolicy must convert admin days → stored hours (Bachus).
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\Controller;

use PHPUnit\Framework\TestCase;

class AdminSimulateVacationDaysConvertContractTest extends TestCase
{
	public function testSimulateConvertsDraftManualDaysViaUnitService(): void
	{
		$src = file_get_contents(dirname(__DIR__, 3) . '/lib/Controller/AdminController.php');
		$this->assertNotFalse($src);
		$this->assertStringContainsString('adminDaysToStoredAmount($draftManual)', $src);
		$this->assertStringContainsString('simulator draft is always calendar days', $src);
	}
}
