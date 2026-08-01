<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class DedicatedAppAdminContractTest extends TestCase
{
	private string $root;

	protected function setUp(): void
	{
		$this->root = dirname(__DIR__, 2);
	}

	public function testPermissionServiceUsesOrSemantics(): void
	{
		$src = (string)file_get_contents($this->root . '/lib/Service/PermissionService.php');
		$start = strpos($src, 'public function isAdmin(string $userId): bool');
		$this->assertNotFalse($start);
		$end = strpos($src, 'public function getConfiguredAppAdminUserIds', $start);
		$this->assertNotFalse($end);
		$body = substr($src, $start, $end - $start);
		$this->assertStringContainsString('if ($this->groupManager->isAdmin($userId))', $body);
		$this->assertStringContainsString('return true;', $body);
		$this->assertStringContainsString('return in_array($userId, $this->getConfiguredAppAdminUserIds(), true);', $body);
		$this->assertStringNotContainsString('if (!$this->groupManager->isAdmin($userId))', $body);
	}

	public function testNormalizeAllowsNonSystemAdmins(): void
	{
		$src = (string)file_get_contents($this->root . '/lib/Controller/AdminController.php');
		$start = strpos($src, 'private function normalizeAppAdminUserIds');
		$this->assertNotFalse($start);
		$body = substr($src, $start, 600);
		$this->assertStringContainsString('isEnabled()', $body);
		$this->assertStringNotContainsString('groupManager->isAdmin($candidate)', $body);
	}

	public function testSettingsTemplateAllowsDelegatedAppAdmins(): void
	{
		$html = (string)file_get_contents($this->root . '/templates/admin-settings.php');
		$this->assertStringContainsString('without making them a Nextcloud admin', $html);
		$this->assertStringContainsString('appAdminUsersAddSearch', $html);
		$this->assertStringNotContainsString('Only users in the Nextcloud admin group are listed', $html);
		$this->assertStringContainsString('never type a raw user id', $html);
	}

	public function testTeamsPickerHelpDoesNotAskForRawUserId(): void
	{
		$l10n = (string) file_get_contents($this->root . '/templates/common/teams-l10n.php');
		$js = (string) file_get_contents($this->root . '/js/admin-teams.js');
		$this->assertStringContainsString('Start typing their name or login, then pick them from the list.', $l10n);
		$this->assertStringNotContainsString('name or user ID, then pick', $l10n);
		$this->assertStringContainsString('Start typing their name or login, then pick them from the list.', $js);
		$this->assertStringNotContainsString('name or user ID, then pick', $js);
	}

	public function testVacationSimulatorRequiresSuggestionPickNotFreeTextUid(): void
	{
		$html = (string) file_get_contents($this->root . '/templates/admin-vacation-layers.php');
		$js = (string) file_get_contents($this->root . '/js/admin-vacation-layers.js');
		$this->assertStringContainsString('id="sim-user"', $html);
		$this->assertStringContainsString('type="search"', $html);
		$this->assertStringContainsString('sim-user-suggest', $html);
		$this->assertStringContainsString('name or login', $html);
		$this->assertStringContainsString('const userId = simSelectedUserId || \'\'', $js);
		$this->assertStringNotContainsString('simSelectedUserId || typedValue', $js);
		$this->assertStringNotContainsString('usedFreeText', $js);
		$this->assertStringNotContainsString('type a UID exactly', $js);
		$this->assertStringContainsString('Please pick an employee from the suggestions first.', $js);
	}
}
