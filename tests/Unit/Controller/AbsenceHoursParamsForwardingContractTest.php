<?php

declare(strict_types=1);

/**
 * Absence hours request params must reach the service (web backup fill path).
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\Controller;

use PHPUnit\Framework\TestCase;

class AbsenceHoursParamsForwardingContractTest extends TestCase
{
	public function testStoreForwardsServerMayFillHours(): void
	{
		$src = file_get_contents(dirname(__DIR__, 2) . '/../lib/Controller/AbsenceController.php');
		$this->assertNotFalse($src);
		$this->assertStringContainsString("server_may_fill_hours", $src);
		$this->assertMatchesRegularExpression(
			"/server_may_fill_hours['\"]?\\]\\s*=\\s*true/",
			$src
		);
	}

	public function testStoreJsonErrorsExposeCodeEnvelope(): void
	{
		$src = file_get_contents(dirname(__DIR__, 2) . '/../lib/Controller/AbsenceController.php');
		$this->assertNotFalse($src);
		$this->assertStringContainsString("\$payload['code'] = \$code", $src);
		$this->assertStringContainsString("\$payload['error_code'] = \$code", $src);
		$this->assertStringContainsString('function getErrorCode', $src);
	}

	public function testManagerCreateExposesBusinessRuleCodes(): void
	{
		$src = file_get_contents(dirname(__DIR__, 2) . '/../lib/Controller/ManagerController.php');
		$this->assertNotFalse($src);
		$this->assertStringContainsString('BusinessRuleException', $src);
		$this->assertStringContainsString("\$payloadOut['error_code'] = \$code", $src);
	}
}
