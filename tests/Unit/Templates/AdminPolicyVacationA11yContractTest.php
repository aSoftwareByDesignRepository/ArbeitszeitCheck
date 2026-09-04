<?php

declare(strict_types=1);

/**
 * Admin vacation policy template: anniversary day field must not stay required
 * when hidden; unit choice must not nest radiogroup inside fieldset.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\Templates;

use PHPUnit\Framework\TestCase;

class AdminPolicyVacationA11yContractTest extends TestCase
{
	private function template(): string
	{
		$path = dirname(__DIR__, 3) . '/templates/partials/admin-policy-vacation.php';
		$this->assertFileExists($path);
		$src = file_get_contents($path);
		$this->assertIsString($src);
		return $src;
	}

	public function testAnniversaryModeOmitsRequiredOnHiddenDayInput(): void
	{
		$src = $this->template();
		// PHP emits required only when NOT anniversary (ternary).
		$this->assertMatchesRegularExpression(
			'/vacationCarryoverExpiryDay[\s\S]*?<\?php echo \$vacationYearMode === \'anniversary\' \? \'\' : \'required\'; \?>/',
			$src
		);
		// Must not unconditionally hardcode required on the day input.
		$this->assertDoesNotMatchRegularExpression(
			'/id="vacationCarryoverExpiryDay"[^>]*\srequired\s/',
			$src
		);
	}

	public function testUnitChoiceFieldsetHasNoNestedRadiogroup(): void
	{
		$src = $this->template();
		$this->assertStringContainsString('id="vacation-unit-choice"', $src);
		$this->assertStringContainsString('class="vacation-unit-radios azc-choice-cards"', $src);
		$this->assertStringNotContainsString(
			'vacation-unit-radios azc-choice-cards" role="radiogroup"',
			$src
		);
	}

	public function testVacationYearModeChoiceCardsWrapInputInLabel(): void
	{
		$src = $this->template();
		$this->assertMatchesRegularExpression(
			'/<label class="form-radio azc-choice-card" for="vacationYearMode-calendar">[\s\S]*?<input type="radio" id="vacationYearMode-calendar"/',
			$src
		);
		$this->assertMatchesRegularExpression(
			'/<label class="form-radio azc-choice-card" for="vacationYearMode-anniversary">[\s\S]*?<input type="radio" id="vacationYearMode-anniversary"/',
			$src
		);
		$this->assertStringContainsString('azc-choice-card__copy', $src);
		$this->assertDoesNotMatchRegularExpression(
			'/<div class="form-radio azc-choice-card">/',
			$src,
			'Year-mode cards must be labels (radio + copy as flex siblings), not div wrappers'
		);
	}

	public function testMissingHireAckHelpIsOutsideCheckboxRow(): void
	{
		$src = $this->template();
		$this->assertDoesNotMatchRegularExpression(
			'/id="vacation-year-missing-hire-ack-wrap"[^>]*class="[^"]*form-checkbox/',
			$src
		);
		$this->assertMatchesRegularExpression(
			'/id="vacation-year-missing-hire-ack-wrap"[\s\S]*?<div class="form-checkbox">[\s\S]*?<\/div>\s*<p id="vacation-year-missing-hire-ack-help" class="form-help"/',
			$src
		);
	}
}
