<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Layout contract: table-heavy pages use full-width shell; admin list pages merge shell params.
 */
class PageShellLayoutTest extends TestCase
{
	public function testWideShellIncludesTableHeavyPageIds(): void
	{
		$controller = new ReflectionClass(\OCA\ArbeitszeitCheck\Controller\PageController::class);
		$constant = $controller->getConstant('WIDE_SHELL_PAGE_IDS');
		$this->assertIsArray($constant);

		foreach ([
			'admin-users',
			'admin-kiosk',
			'time-entries',
			'absences',
			'manager-time-entries',
			'manager-absences',
			'compliance-violations',
			'substitution-requests',
		] as $pageId) {
			$this->assertContains($pageId, $constant, 'Expected wide shell for ' . $pageId);
		}
	}

	public function testAdminUsersControllerMergesShellParams(): void
	{
		$content = (string)file_get_contents(__DIR__ . '/../../lib/Controller/AdminController.php');
		$this->assertStringContainsString("'admin-users', array_merge(", $content);
		$this->assertStringContainsString("buildAdminShellParams(\n\t\t\t\t'admin-users'", $content);
	}

	public function testWorkingTimeModelsControllerMergesShellParams(): void
	{
		$content = (string)file_get_contents(__DIR__ . '/../../lib/Controller/AdminController.php');
		$this->assertStringContainsString("'admin-working-time-models'", $content);
		$this->assertStringContainsString("buildAdminShellParams(\n\t\t\t\t'admin-working-time-models'", $content);
	}

	public function testSettingsUsesWideShell(): void
	{
		$controller = new ReflectionClass(\OCA\ArbeitszeitCheck\Controller\PageController::class);
		$wide = $controller->getConstant('WIDE_SHELL_PAGE_IDS');
		$constrained = $controller->getConstant('CONSTRAINED_SHELL_PAGE_IDS');
		$this->assertIsArray($wide);
		$this->assertIsArray($constrained);
		$this->assertContains('settings', $wide);
		$this->assertNotContains('settings', $constrained);
		$this->assertContains('admin-user-detail', $constrained);
	}

	public function testAdminUserDetailControllerMergesShellParams(): void
	{
		$content = (string)file_get_contents(__DIR__ . '/../../lib/Controller/AdminController.php');
		$this->assertStringContainsString("'admin-user-detail'", $content);
		$this->assertStringContainsString('function userDetail(string $userId)', $content);
	}

	public function testManagerScopePagesUseFullWidthLayout(): void
	{
		$content = (string)file_get_contents(__DIR__ . '/../../css/manager-time-entries.css');
		$this->assertStringContainsString('.manager-scope-page', $content);
		$this->assertStringNotContainsString('max-width: 56rem', $content);

		$monthClosures = (string)file_get_contents(__DIR__ . '/../../css/manager-month-closures.css');
		$this->assertStringContainsString('max-width: none', $monthClosures);
		$this->assertStringNotContainsString('max-width: 48rem', $monthClosures);
		$this->assertStringNotContainsString('max-width: 56rem', $monthClosures);
	}

	public function testAdminKioskUsesFullWidthLayout(): void
	{
		$content = (string)file_get_contents(__DIR__ . '/../../css/admin-kiosk.css');
		$this->assertStringContainsString('.azc-kiosk-page', $content);
		$this->assertStringContainsString('max-width: none', $content);
		$this->assertStringNotContainsString('max-width: min(72rem', $content);
		$this->assertStringContainsString('.azc-kiosk-overview', $content);
		$this->assertStringContainsString('.azc-kiosk-panel', $content);
		$template = (string)file_get_contents(__DIR__ . '/../../templates/admin-kiosk.php');
		$this->assertStringContainsString('azc-kiosk-open-create', $template);
		$this->assertStringContainsString('azc-kiosk-create-modal', $template);
	}
}
