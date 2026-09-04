<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Db;

use OCA\ArbeitszeitCheck\Db\KioskCredMapper;
use PHPUnit\Framework\TestCase;

/**
 * Regression: findById used to call non-existent QBMapper::getById(), which
 * crashed every wrong-PIN identify with HTTP 500 and never recorded lockout.
 */
class KioskCredMapperFindByIdTest extends TestCase
{
	public function testFindByIdSourceDoesNotCallUndefinedGetById(): void
	{
		$path = dirname(__DIR__, 3) . '/lib/Db/KioskCredMapper.php';
		$real = realpath($path);
		$this->assertNotFalse($real, 'mapper path must resolve: ' . $path);
		$src = file_get_contents($real);
		$this->assertNotFalse($src);
		$this->assertStringNotContainsString(
			'->getById(',
			$src,
			'findById must not call undefined QBMapper::getById (breaks PIN lockout)',
		);
		$this->assertStringContainsString('findEntity(', $src);
		$this->assertStringContainsString('PARAM_INT', $src);
	}

	public function testFindByIdMethodIsPublicAndNullable(): void
	{
		$ref = new \ReflectionMethod(KioskCredMapper::class, 'findById');
		$this->assertTrue($ref->isPublic());
		$return = $ref->getReturnType();
		$this->assertNotNull($return);
		$this->assertTrue($return->allowsNull(), 'Missing row must return null, not throw');
	}
}
