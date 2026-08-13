<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Js;

use PHPUnit\Framework\TestCase;

class AdminVacationUnitBanssUxContractTest extends TestCase
{
	public function testAdminNotificationsJsWiresBanssFactorButtonAndConfirmFactor(): void
	{
		$src = file_get_contents(dirname(__DIR__, 2) . '/../js/admin-notifications.js');
		$this->assertNotFalse($src);
		$this->assertStringContainsString('btn-vacation-hours-use-banss', $src);
		$this->assertStringContainsString('formatWithFactor', $src);
		$this->assertStringContainsString('vacationUnitConfirmHours', $src);
		$this->assertStringContainsString('vacationHoursBanssApplied', $src);
		$this->assertStringContainsString("replace(/%s/g", $src);
	}

	public function testVacationUnitIifeBindsOwnL10nNotInitClosure(): void
	{
		$src = file_get_contents(dirname(__DIR__, 2) . '/../js/admin-notifications.js');
		$this->assertNotFalse($src);
		$pos = strpos($src, 'function initVacationUnitMigration');
		$this->assertNotFalse($pos, 'vacation unit IIFE must exist');
		$chunk = substr($src, $pos, 800);
		$this->assertStringContainsString(
			'const l10n = (window.ArbeitszeitCheck && window.ArbeitszeitCheck.l10n) || {}',
			$chunk,
			'initVacationUnitMigration must bind its own l10n (outside init() scope) or syncApplyGate throws ReferenceError'
		);
	}
}
