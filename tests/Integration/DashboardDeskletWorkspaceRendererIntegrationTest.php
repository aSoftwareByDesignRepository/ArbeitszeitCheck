<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Integration;

use OCA\ArbeitszeitCheck\Service\DashboardDeskletWorkspaceRenderer;
use OCP\IL10N;
use Test\TestCase;

/**
 * Exercises the legacy \OCP\Template path outside the large unit suite (avoids PHP segfault from polluted globals).
 */
class DashboardDeskletWorkspaceRendererIntegrationTest extends TestCase
{
	public function testRenderProducesDeskletMarkup(): void
	{
		$config = [
			'status' => 'clocked_out',
			'l10n' => [
				'deskletTitle' => 'Quick time tracking',
				'deskletLead' => 'Clock in from here.',
				'tryAgain' => 'Try again',
			],
		];

		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);

		$renderer = new DashboardDeskletWorkspaceRenderer();
		$html = $renderer->render($config, $l10n);

		$this->assertStringContainsString('data-arbeitszeitcheck-desklet', $html);
		$this->assertStringContainsString('dz-error-panel', $html);
		$this->assertStringContainsString('dz-retry', $html);
		$this->assertStringContainsString('dz-status-section', $html);
		$this->assertStringContainsString('azc-btn azc-btn--primary', $html);
		$this->assertStringContainsString('id="dz-clock-in"', $html);
		$this->assertStringNotContainsString('btn-primary', $html);
	}
}
