<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Templates;

use PHPUnit\Framework\TestCase;

class AbsencesVacationHoursLayoutContractTest extends TestCase
{
	public function testAbsencesTemplateIncludesHoursBookingSurface(): void
	{
		$src = file_get_contents(dirname(__DIR__, 2) . '/../templates/absences.php');
		$this->assertNotFalse($src);
		$this->assertStringContainsString('id="absence-duration-hours-group"', $src);
		$this->assertStringContainsString('id="absence-duration-hours"', $src);
		$this->assertStringContainsString('name="duration_hours"', $src);
		$this->assertStringContainsString('absence-hours-preset', $src);
		$this->assertStringContainsString('stat-card--hero', $src);
		$this->assertStringContainsString('stat-value--hero', $src);
		$this->assertStringContainsString("\$l->t('Duration')", $src);
		$this->assertStringContainsString('getDurationHours()', $src);
		$this->assertStringContainsString('data-hours-range', $src);
		$this->assertStringContainsString('id="absence-duration-hours-preview"', $src);
		$this->assertStringContainsString('Working days in range', $src);
		// Weekend/holiday ranges may legitimately estimate 0 h — do not ignore success+0.
		$this->assertStringContainsString('j.hours < 0', $src);
		$this->assertStringNotContainsString('j.hours <= 0', $src);
		$this->assertStringContainsString('id="absence-org-debit-warn"', $src);
		$this->assertStringContainsString('data-debit-basis', $src);
		$this->assertStringContainsString('No working-time model assigned', $src);
		// Never invent hours via days × org factor when duration_hours is missing.
		$this->assertStringNotContainsString('round($displayD * $hoursPerDay, 1)', $src);
		$this->assertStringNotContainsString('((float)$days * $hoursPerDay)', $src);
	}
}
