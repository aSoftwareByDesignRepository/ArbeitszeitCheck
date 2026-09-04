<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Templates;

use PHPUnit\Framework\TestCase;

class ManagerAbsencesHoursLayoutContractTest extends TestCase
{
	public function testManagerAbsencesTemplateIncludesHoursRecordingSurface(): void
	{
		$src = file_get_contents(dirname(__DIR__, 2) . '/../templates/manager-absences.php');
		$this->assertNotFalse($src);
		$this->assertStringContainsString('id="manager-absence-record-hours-field"', $src);
		$this->assertStringContainsString('id="manager-absence-record-hours"', $src);
		$this->assertStringContainsString('manager-absence-hours-preset', $src);
		$this->assertStringContainsString('data-hours-range', $src);
		$this->assertStringContainsString('id="manager-absence-record-hours-preview"', $src);
		$this->assertStringContainsString('id="employee-absences-duration-heading"', $src);
		$this->assertStringContainsString('vacationUnit', $src);
	}

	public function testManagerAbsencesJsAcceptsZeroHourEstimate(): void
	{
		$js = file_get_contents(dirname(__DIR__, 2) . '/../js/manager-absences.js');
		$this->assertNotFalse($js);
		$this->assertStringContainsString('j.hours < 0', $js);
		$this->assertStringNotContainsString('j.hours <= 0', $js);
	}
}
