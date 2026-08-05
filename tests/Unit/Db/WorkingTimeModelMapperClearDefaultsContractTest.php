<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\Db;

use OCA\ArbeitszeitCheck\Db\WorkingTimeModelMapper;
use PHPUnit\Framework\TestCase;

/**
 * Contract: default clearing must be a bulk UPDATE (not TOCTOU read-modify-write).
 */
final class WorkingTimeModelMapperClearDefaultsContractTest extends TestCase
{
	public function testClearDefaultsMethodExistsAndDocumentsExceptId(): void
	{
		$ref = new \ReflectionMethod(WorkingTimeModelMapper::class, 'clearDefaults');
		$this->assertTrue($ref->isPublic());
		$params = $ref->getParameters();
		$this->assertCount(1, $params);
		$this->assertSame('exceptId', $params[0]->getName());
		$this->assertTrue($params[0]->allowsNull());
		$this->assertTrue($params[0]->isDefaultValueAvailable());
		$this->assertNull($params[0]->getDefaultValue());
	}

	public function testCreateAndUpdateUseAtomicClearDefaults(): void
	{
		$src = (string)file_get_contents(
			dirname(__DIR__, 3) . '/lib/Controller/AdminController.php'
		);
		$this->assertMatchesRegularExpression(
			'/function createWorkingTimeModel\(\): JSONResponse[\s\S]*?clearDefaults\(\)/',
			$src,
			'create must clear defaults inside the write path'
		);
		$this->assertMatchesRegularExpression(
			'/function createWorkingTimeModel\(\): JSONResponse[\s\S]*?\$this->atomic\(/',
			$src,
			'create must run inside atomic()'
		);
		$this->assertMatchesRegularExpression(
			'/function updateWorkingTimeModel\(int \$id\): JSONResponse[\s\S]*?clearDefaults\(\$model->getId\(\)\)/',
			$src,
			'update must clear other defaults while keeping self'
		);
		$this->assertDoesNotMatchRegularExpression(
			'/function createWorkingTimeModel\(\): JSONResponse[\s\S]*?findDefault\(\)[\s\S]*?setIsDefault\(false\)/',
			$src,
			'create must not use the old findDefault TOCTOU pattern'
		);
	}
}
