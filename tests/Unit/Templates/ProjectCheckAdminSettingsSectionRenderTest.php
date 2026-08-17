<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Templates;

use OCP\IL10N;
use PHPUnit\Framework\TestCase;

/**
 * Renders the ProjectCheck Global settings partial.
 *
 * Guards the dispatcher bug where $projectCheckAvailable was never imported into
 * the include closure, so the yellow “app required” warning always showed.
 */
final class ProjectCheckAdminSettingsSectionRenderTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		require_once dirname(__DIR__, 2) . '/Unit/Support/template_stubs.php';
	}

	public function testShowsSwitchWhenProjectCheckIsInstalledOnInstance(): void
	{
		$html = $this->render([
			'projectCheckAvailable' => true,
			'projectCheckEnabledForCurrentUser' => true,
			'projectCheckAppsUrl' => '/settings/apps',
			'settings' => ['projectCheckIntegrationEnabled' => false],
		]);

		$this->assertStringContainsString('id="projectCheckIntegrationEnabled"', $html);
		$this->assertStringContainsString('role="switch"', $html);
		$this->assertStringContainsString('Connect ArbeitszeitCheck to ProjectCheck', $html);
		$this->assertStringContainsString('Connection off', $html);
		$this->assertStringNotContainsString('id="azc-projectcheck-app-required"', $html);
		$this->assertStringNotContainsString('id="azc-projectcheck-group-limited"', $html);
		$this->assertStringNotContainsString('<script', $html);
	}

	public function testShowsRequiredWarningWhenProjectCheckIsNotInstalled(): void
	{
		$html = $this->render([
			'projectCheckAvailable' => false,
			'projectCheckEnabledForCurrentUser' => false,
			'projectCheckAppsUrl' => '/settings/apps',
			'settings' => ['projectCheckIntegrationEnabled' => false],
		]);

		$this->assertStringContainsString('id="azc-projectcheck-app-required"', $html);
		$this->assertStringContainsString('ProjectCheck app required', $html);
		$this->assertStringContainsString('Open Apps', $html);
		$this->assertStringContainsString('href="/settings/apps"', $html);
		$this->assertStringContainsString('azc-btn--touch', $html);
		$this->assertStringNotContainsString('id="projectCheckIntegrationEnabled"', $html);
	}

	public function testShowsGroupLimitedNoteButKeepsSwitchWhenAdminIsOutsideLimit(): void
	{
		$html = $this->render([
			'projectCheckAvailable' => true,
			'projectCheckEnabledForCurrentUser' => false,
			'projectCheckAppsUrl' => '/settings/apps',
			'settings' => ['projectCheckIntegrationEnabled' => true],
		]);

		$this->assertStringContainsString('id="azc-projectcheck-group-limited"', $html);
		$this->assertStringContainsString('id="projectCheckIntegrationEnabled"', $html);
		$this->assertStringContainsString('checked', $html);
		$this->assertStringContainsString('Connection on', $html);
		$this->assertStringNotContainsString('id="azc-projectcheck-app-required"', $html);
	}

	public function testOmitsAppsButtonWhenUrlEmpty(): void
	{
		$html = $this->render([
			'projectCheckAvailable' => false,
			'projectCheckEnabledForCurrentUser' => false,
			'projectCheckAppsUrl' => '',
			'settings' => [],
		]);

		$this->assertStringContainsString('id="azc-projectcheck-app-required"', $html);
		$this->assertStringNotContainsString('Open Apps', $html);
	}

	public function testDispatcherImportsProjectCheckAvailabilityIntoIncludeScope(): void
	{
		$src = (string)file_get_contents(dirname(__DIR__, 3) . '/templates/admin-settings.php');
		$this->assertStringContainsString('$projectCheckAvailable = !empty($_[\'projectCheckAvailable\'])', $src);
		$this->assertMatchesRegularExpression(
			'/\$includeSection = static function \(string \$slug\) use \([^)]*\$projectCheckAvailable/',
			$src
		);
	}

	/**
	 * @param array<string, mixed> $vars
	 */
	private function render(array $vars): string
	{
		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnCallback(static fn (string $s) => $s);
		$projectCheckAvailable = !empty($vars['projectCheckAvailable']);
		$projectCheckEnabledForCurrentUser = !empty($vars['projectCheckEnabledForCurrentUser']);
		$projectCheckAppsUrl = (string)($vars['projectCheckAppsUrl'] ?? '');
		$settings = is_array($vars['settings'] ?? null) ? $vars['settings'] : [];
		$azcSettingsShowCardChrome = true;
		$renderAll = true;

		ob_start();
		include dirname(__DIR__, 3) . '/templates/partials/projectcheck-admin-settings-section.php';
		$html = (string)ob_get_clean();
		$this->assertNotSame('', trim($html));
		return $html;
	}
}
