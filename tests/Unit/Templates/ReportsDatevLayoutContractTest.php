<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\Templates;

use PHPUnit\Framework\TestCase;

class ReportsDatevLayoutContractTest extends TestCase
{
	public function testReportsTemplateIncludesDatevPanelAndUrls(): void
	{
		$src = file_get_contents(dirname(__DIR__, 2) . '/../templates/reports.php');
		$this->assertNotFalse($src);
		$this->assertStringContainsString('id="datev-export-panel"', $src);
		$this->assertStringContainsString('id="btn-datev-self"', $src);
		$this->assertStringContainsString('id="btn-datev-org"', $src);
		$this->assertStringContainsString('id="datev-export-status"', $src);
		$this->assertStringContainsString("export.datev", $src);
		$this->assertStringContainsString("export.datevConfig", $src);
		$this->assertStringContainsString('DATEV section under this form', $src);
		$this->assertStringNotContainsString(
			'This page offers CSV and JSON only',
			$src
		);
	}

	public function testReportsJsRendersSealedPremiumAndDatevDownload(): void
	{
		$src = file_get_contents(dirname(__DIR__, 2) . '/../js/reports.js');
		$this->assertNotFalse($src);
		$this->assertStringContainsString('from_month_closure_snapshot', $src);
		$this->assertStringContainsString('frozenPremiumsTitle', $src);
		$this->assertStringContainsString('initDatevExportPanel', $src);
		$this->assertStringContainsString("scope', 'organization'", $src);
		$this->assertStringContainsString('ready_for_self_export', $src);
	}
}
