<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\Templates;

use PHPUnit\Framework\TestCase;

class AdminSettingsDatevLayoutContractTest extends TestCase
{
	public function testExportsSectionIncludesDatevOrgFields(): void
	{
		$src = file_get_contents(dirname(__DIR__, 2) . '/../templates/partials/admin-settings/exports.php');
		$this->assertNotFalse($src);
		$this->assertStringContainsString('id="datevBeraternummer"', $src);
		$this->assertStringContainsString('id="datevMandantennummer"', $src);
		$this->assertStringContainsString('id="datevLohnartNormal"', $src);
		$this->assertStringContainsString('datev-org-legend', $src);
		$this->assertStringContainsString('aria-describedby="datevBeraternummer-help datev-org-intro"', $src);
	}

	public function testUserDetailIncludesDatevTocAnchor(): void
	{
		$src = file_get_contents(dirname(__DIR__, 2) . '/../templates/admin-user-detail.php');
		$this->assertNotFalse($src);
		$this->assertStringContainsString('href="#user-edit-datev"', $src);
	}
}
