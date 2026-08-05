<?php

declare(strict_types=1);

/**
 * Entitlement explainer must not hardcode “days” when org is hours (NN-08).
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\Templates;

use PHPUnit\Framework\TestCase;

class EntitlementExplainerHoursCopyContractTest extends TestCase
{
	public function testAbsencesBootstrapIncludesVacationUnitAndHoursCopy(): void
	{
		$src = file_get_contents(dirname(__DIR__, 3) . '/templates/absences.php');
		$this->assertNotFalse($src);
		$this->assertStringContainsString("'vacationUnit' => \$isVacationHours ? 'hours' : 'days'", $src);
		$this->assertStringContainsString("hours per year", $src);
		$this->assertStringContainsString('0–4000 hour range', $src);
		$this->assertStringContainsString('{prorated} of {full} hours', $src);
		$this->assertStringContainsString('days per year', $src);
	}

	public function testAdminApplyHelpDocumentsEnabledButtonGate(): void
	{
		$src = file_get_contents(dirname(__DIR__, 3) . '/templates/partials/admin-policy-vacation.php');
		$this->assertNotFalse($src);
		$this->assertStringContainsString('Select Days or Hours, then Apply', $src);
		$js = file_get_contents(dirname(__DIR__, 3) . '/js/admin-notifications.js');
		$this->assertNotFalse($js);
		$this->assertStringContainsString('btnApply.disabled = false', $js);
		$this->assertStringContainsString('vacationUnitNeedClientConfirm', $js);
		$this->assertStringContainsString('vacationUnitFactorHint', $js);
	}
}
