<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Templates;

use PHPUnit\Framework\TestCase;

class AdminVacationUnitLayoutContractTest extends TestCase
{
	public function testVacationPolicyPartialIncludesVacationUnitWizard(): void
	{
		$src = file_get_contents(dirname(__DIR__, 3) . '/templates/partials/admin-policy-vacation.php');
		$this->assertNotFalse($src);
		$this->assertStringContainsString('id="vacation-unit-heading"', $src);
		$this->assertStringContainsString('id="btn-vacation-unit-apply"', $src);
		$this->assertStringContainsString('id="vacation-unit-days"', $src);
		$this->assertStringContainsString('id="vacation-unit-hours"', $src);
		$this->assertStringContainsString('id="vacationUnitClientConfirmed"', $src);
		$this->assertStringContainsString('id="vacationHoursPerDay"', $src);
		$this->assertStringContainsString('id="vacation-hours-per-day-help"', $src);
		$this->assertStringContainsString('id="vacation-hours-banss-callout"', $src);
		$this->assertStringContainsString('id="btn-vacation-hours-use-banss"', $src);
		$this->assertStringContainsString('7.7', $src);
		$this->assertStringContainsString('38.5', $src);
		$this->assertStringContainsString('BANSS', $src);
		$this->assertStringContainsString('id="vacation-year-mode-heading"', $src);
		$this->assertStringNotContainsString('id="btn-vacation-unit-to-hours"', $src);
		$this->assertStringNotContainsString('id="btn-vacation-unit-to-days"', $src);
		$this->assertStringNotContainsString('missingClockInRemindersEnabled', $src);
	}

	public function testVacationRulesPageHostsPolicyFormWithoutJumpNav(): void
	{
		$page = file_get_contents(dirname(__DIR__, 3) . '/templates/admin-vacation-rules.php');
		$this->assertNotFalse($page);
		$this->assertStringContainsString('admin-policy-vacation.php', $page);
		$this->assertStringContainsString('admin-vacation-policy-form', $page);
		$this->assertStringNotContainsString('azc-jump-nav.php', $page);
		$layers = file_get_contents(dirname(__DIR__, 3) . '/templates/admin-vacation-layers.php');
		$this->assertNotFalse($layers);
		$this->assertStringNotContainsString('admin-policy-vacation.php', $layers);
		$this->assertStringNotContainsString('admin-vacation-policy-form', $layers);
		$this->assertStringContainsString('admin-vacation-layers', $layers);
	}

	public function testNotificationsPageNoLongerHostsVacationOrPremiums(): void
	{
		$page = file_get_contents(dirname(__DIR__, 3) . '/templates/admin-notifications.php');
		$this->assertNotFalse($page);
		$this->assertStringNotContainsString('vacation-unit-heading', $page);
		$this->assertStringNotContainsString('premium-surcharges-heading', $page);
		$this->assertStringNotContainsString('overtime-bank-heading', $page);
		$this->assertStringContainsString('admin-policy-clock-reminders.php', $page);
		$this->assertStringContainsString('admin-policy-hr-office.php', $page);
		$this->assertStringContainsString('admin-policy-overtime-alerts.php', $page);
	}
}
