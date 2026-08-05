<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service;

use OCA\ArbeitszeitCheck\Service\DashboardDeskletConfigService;
use OCA\ArbeitszeitCheck\Service\PermissionService;
use OCA\ArbeitszeitCheck\Service\ProjectCheckIntegrationService;
use OCP\App\IAppManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;

class DashboardDeskletConfigServiceTest extends TestCase {
	public function testBuildL10nPreservesPlaceholderTemplates(): void {
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static function (string $id, array $params = []): string {
			return vsprintf($id, $params);
		});

		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->method('linkToRoute')->willReturn('/route');

		$permissions = $this->createMock(PermissionService::class);
		$permissions->method('canAccessManagerDashboard')->willReturn(false);
		$permissions->method('isAdmin')->willReturn(false);

		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppWebPath')->willReturn('/custom_apps/arbeitszeitcheck');

		$projectCheck = $this->createMock(ProjectCheckIntegrationService::class);
		$projectCheck->method('isLinkingEnabledForUser')->with('alice')->willReturn(false);
		$projectCheck->method('isProjectCheckAvailable')->willReturn(false);
		$projectCheck->expects($this->never())->method('getAvailableProjects');

		$service = new DashboardDeskletConfigService(
			$urlGenerator,
			$permissions,
			$l10n,
			$appManager,
			$projectCheck,
		);
		$config = $service->buildForUser('alice');
		$l10nMap = $config['l10n'];

		$this->assertSame('Status: %1$s', $l10nMap['statusLine']);
		$this->assertSame('Last updated: %1$s', $l10nMap['lastUpdated']);
		$this->assertSame('%1$s successful', $l10nMap['actionDone']);
		$this->assertSame('%1$s: %2$s (%3$s h)', $l10nMap['peopleRow']);
		$this->assertSame('Working', $l10nMap['working']);
		$this->assertSame('Project', $l10nMap['projectLabel']);
		$this->assertSame('Daily maximum reached', $l10nMap['dailyMaximumTitle']);
		$this->assertSame('ProjectCheck linking is turned off', $l10nMap['projectLinkingOffTitle']);
		$this->assertIsArray($config['projectCheck']);
		$this->assertFalse($config['projectCheck']['linkingEnabled']);
		$this->assertSame([], $config['projectCheck']['projects']);
	}

	public function testBuildForUserIncludesAssignableProjectsWhenLinkingEnabled(): void {
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static function (string $id, array $params = []): string {
			return vsprintf($id, $params);
		});

		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->method('linkToRoute')->willReturn('/route');

		$permissions = $this->createMock(PermissionService::class);
		$permissions->method('canAccessManagerDashboard')->willReturn(false);
		$permissions->method('isAdmin')->willReturn(false);

		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppWebPath')->willReturn('/custom_apps/arbeitszeitcheck');

		$projectCheck = $this->createMock(ProjectCheckIntegrationService::class);
		$projectCheck->method('isLinkingEnabledForUser')->with('bob')->willReturn(true);
		$projectCheck->method('isProjectCheckAvailable')->willReturn(true);
		$projectCheck->method('getAvailableProjects')->with('bob')->willReturn([
			['id' => '42', 'displayName' => 'Alpha'],
		]);

		$service = new DashboardDeskletConfigService(
			$urlGenerator,
			$permissions,
			$l10n,
			$appManager,
			$projectCheck,
		);
		$config = $service->buildForUser('bob');

		$this->assertTrue($config['projectCheck']['available']);
		$this->assertTrue($config['projectCheck']['linkingEnabled']);
		$this->assertCount(1, $config['projectCheck']['projects']);
		$this->assertSame('42', $config['projectCheck']['projects'][0]['id']);
	}
}
