<?php

declare(strict_types=1);

/**
 * Admin policy save UX: automatic retry on lock-busy 409 codes.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\Js;

use PHPUnit\Framework\TestCase;

class AdminNotificationsBusyRetryContractTest extends TestCase
{
	public function testAdminNotificationsJsRetriesBusyConflictCodes(): void
	{
		$js = file_get_contents(dirname(__DIR__, 2) . '/../js/admin-notifications.js');
		$this->assertNotFalse($js);
		$this->assertStringContainsString('VAC_YEAR_MODE_BUSY', $js);
		$this->assertStringContainsString('PREMIUM_POLICY_BUSY', $js);
		$this->assertStringContainsString('VAC_UNIT_MIGRATE_IN_PROGRESS', $js);
		$this->assertStringContainsString('maxBusyRetries', $js);
		$this->assertStringContainsString('settingsBusyRetrying', $js);
		$this->assertStringContainsString('postSave(attempt + 1)', $js);
	}

	public function testVacationRulesTemplateExposesBusyRetryL10n(): void
	{
		$tpl = file_get_contents(dirname(__DIR__, 2) . '/../templates/admin-vacation-rules.php');
		$this->assertNotFalse($tpl);
		$this->assertStringContainsString('settingsBusyRetrying', $tpl);
		$this->assertStringContainsString('Still busy — trying again', $tpl);
	}
}
