<?php

declare(strict_types=1);

/**
 * §9.7 calendar→anniversary requires hire-date ack when users lack employment_start.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\Controller;

use OCA\ArbeitszeitCheck\Constants;
use PHPUnit\Framework\TestCase;

class AdminVacationYearAckGateContractTest extends TestCase
{
	public function testAdminControllerEnforcesMissingHireAckOnAnniversarySwitch(): void
	{
		$src = file_get_contents(dirname(__DIR__, 3) . '/lib/Controller/AdminController.php');
		$this->assertNotFalse($src);
		$this->assertStringContainsString('VAC_YEAR_MISSING_HIRE_ACK_REQUIRED', $src);
		$this->assertStringContainsString('vacationYearMissingHireAcknowledged', $src);
		$this->assertStringContainsString('countUsersMissingEmploymentStart()', $src);
		$this->assertStringContainsString('Http::STATUS_CONFLICT', $src);
	}

	public function testAdminTemplateExposesAckCheckbox(): void
	{
		$tpl = file_get_contents(dirname(__DIR__, 3) . '/templates/partials/admin-policy-vacation.php');
		$this->assertNotFalse($tpl);
		$this->assertStringContainsString('id="vacationYearMissingHireAcknowledged"', $tpl);
		$this->assertStringContainsString('vacation-year-missing-hire-ack-wrap', $tpl);
	}

	public function testAdminJsSendsAckAndBlocksWithoutIt(): void
	{
		$js = file_get_contents(dirname(__DIR__, 3) . '/js/admin-notifications.js');
		$this->assertNotFalse($js);
		$this->assertStringContainsString('vacationYearMissingHireAcknowledged', $js);
		$this->assertStringContainsString('vacationYearMissingHireAckRequired', $js);
		$this->assertStringContainsString("yearMode === 'anniversary'", $js);
	}

	public function testErrorCodeConstantExists(): void
	{
		$this->assertSame(
			'VAC_YEAR_MISSING_HIRE_ACK_REQUIRED',
			Constants::VAC_YEAR_MISSING_HIRE_ACK_REQUIRED
		);
	}
}
