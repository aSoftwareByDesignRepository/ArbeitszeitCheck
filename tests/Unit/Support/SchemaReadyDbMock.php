<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Support;

use OCP\DB\IResult;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

/**
 * Mocks IDBConnection for VacationUnitMigrationService schema probes.
 */
trait SchemaReadyDbMock
{
	private function createSchemaReadyDbMock(?TestCase $test = null): IDBConnection
	{
		$test ??= $this;
		$result = $test->createMock(IResult::class);
		$result->method('closeCursor');

		$qb = $test->createMock(IQueryBuilder::class);
		$qb->method('select')->willReturnSelf();
		$qb->method('from')->willReturnSelf();
		$qb->method('setMaxResults')->willReturnSelf();
		$qb->method('executeQuery')->willReturn($result);

		$db = $test->createMock(IDBConnection::class);
		$db->method('getQueryBuilder')->willReturn($qb);

		return $db;
	}
}
