<?php

declare(strict_types=1);

/**
 * Contract: WTM delete refuses default + purges L1 vacation defaults atomically.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service;

use OCA\ArbeitszeitCheck\Service\LayeredVacationDefaultsService;
use PHPUnit\Framework\TestCase;

final class WorkingTimeModelDeleteSafetyContractTest extends TestCase
{
	public function testServiceExposesBulkPurgeForWorkingTimeModel(): void
	{
		$ref = new \ReflectionMethod(LayeredVacationDefaultsService::class, 'deleteDefaultsForWorkingTimeModel');
		$this->assertTrue($ref->isPublic());
		$params = $ref->getParameters();
		$this->assertCount(1, $params);
		$this->assertSame('workingTimeModelId', $params[0]->getName());
	}

	public function testDeleteWorkingTimeModelIsAtomicAndRefuseDefault(): void
	{
		$src = (string)file_get_contents(dirname(__DIR__, 3) . '/lib/Controller/AdminController.php');
		$this->assertMatchesRegularExpression(
			'/function deleteWorkingTimeModel\(int \$id\): JSONResponse[\s\S]*?\$this->atomic\(/',
			$src
		);
		$this->assertMatchesRegularExpression(
			'/function deleteWorkingTimeModel\(int \$id\): JSONResponse[\s\S]*?deleteDefaultsForWorkingTimeModel\(\$id\)/',
			$src
		);
		$this->assertMatchesRegularExpression(
			'/function deleteWorkingTimeModel\(int \$id\): JSONResponse[\s\S]*?DEFAULT_MODEL/',
			$src
		);
		$this->assertDoesNotMatchRegularExpression(
			"/function deleteWorkingTimeModel\(int \$id\): JSONResponse[\s\S]*?'Working time model deleted successfully'/",
			$src,
			'delete success message must be translated via l10n'
		);
	}

	public function testGdprDeleteIsAtomicAndHonestAboutRetention(): void
	{
		$ctrl = (string)file_get_contents(dirname(__DIR__, 3) . '/lib/Controller/GdprController.php');
		$tpl = (string)file_get_contents(dirname(__DIR__, 3) . '/templates/partials/employee-settings/data-privacy.php');
		$this->assertStringContainsString('use TTransactional', $ctrl);
		$this->assertMatchesRegularExpression(
			'/function delete\(\): JSONResponse[\s\S]*?\$this->atomic\(/',
			$ctrl
		);
		$this->assertStringContainsString('retention period', $tpl);
		$this->assertStringNotContainsString(
			'permanently removes time entries, absences, and settings',
			$tpl
		);
	}
}
