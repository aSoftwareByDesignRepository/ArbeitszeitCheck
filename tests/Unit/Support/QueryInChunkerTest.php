<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\Support;

use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Support\QueryInChunker;
use OCP\DB\QueryBuilder\ICompositeExpression;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use PHPUnit\Framework\TestCase;

final class QueryInChunkerTest extends TestCase
{
	public function testNormalizeValuesDropsBlanksAndDedupes(): void
	{
		$this->assertSame(
			['alice', 'bob', 7],
			QueryInChunker::normalizeValues([' alice ', '', 'bob', 'alice', 7, 7]),
		);
	}

	public function testResolveChunkSizeClampsAndFallsBack(): void
	{
		$this->assertSame(Constants::BATCH_CHUNK_SIZE, QueryInChunker::resolveChunkSize(null));
		$this->assertSame(Constants::BATCH_CHUNK_SIZE, QueryInChunker::resolveChunkSize(0));
		$this->assertSame(Constants::BATCH_CHUNK_SIZE, QueryInChunker::resolveChunkSize(-3));
		$this->assertSame(250, QueryInChunker::resolveChunkSize(250));
		$this->assertSame(
			QueryInChunker::MAX_EXPRESSIONS_PER_LIST,
			QueryInChunker::resolveChunkSize(QueryInChunker::MAX_EXPRESSIONS_PER_LIST + 50),
		);
	}

	public function testEmptyValuesYieldNeverMatchPredicate(): void
	{
		$expr = $this->createMock(IExpressionBuilder::class);
		$expr->expects($this->once())
			->method('eq')
			->with('p1', 'p0')
			->willReturn('never-match');

		$qb = $this->createMock(IQueryBuilder::class);
		$qb->method('expr')->willReturn($expr);
		$qb->method('createNamedParameter')
			->willReturnCallback(static function ($value, $type = null) {
				TestCase::assertSame(IQueryBuilder::PARAM_INT, $type);
				return 'p' . (string)$value;
			});

		$this->assertSame('never-match', QueryInChunker::in($qb, 'user_id', [], IQueryBuilder::PARAM_STR_ARRAY));
	}

	public function testSmallListUsesSingleIn(): void
	{
		$values = ['u1', 'u2', 'u3'];
		$expr = $this->createMock(IExpressionBuilder::class);
		$expr->expects($this->once())
			->method('in')
			->with('user_id', 'param-chunk')
			->willReturn('in-clause');
		$expr->expects($this->never())->method('orX');

		$qb = $this->createMock(IQueryBuilder::class);
		$qb->method('expr')->willReturn($expr);
		$qb->method('createNamedParameter')
			->willReturnCallback(function ($value, $type = null) use ($values) {
				$this->assertSame($values, $value);
				$this->assertSame(IQueryBuilder::PARAM_STR_ARRAY, $type);
				return 'param-chunk';
			});

		$this->assertSame('in-clause', QueryInChunker::in($qb, 'user_id', $values, IQueryBuilder::PARAM_STR_ARRAY));
	}

	public function testLargeListUsesOrCombinedChunksUnderOracleLimit(): void
	{
		$values = [];
		for ($i = 0; $i < 1001; $i++) {
			$values[] = 'user-' . $i;
		}

		$chunkSizes = [];
		$inClauses = [];
		$orResult = $this->createMock(ICompositeExpression::class);

		$expr = $this->createMock(IExpressionBuilder::class);
		$expr->method('in')
			->willReturnCallback(function ($field, $param) use (&$inClauses) {
				$this->assertSame('user_id', $field);
				$inClauses[] = $param;
				return $param;
			});
		$expr->expects($this->once())
			->method('orX')
			->willReturnCallback(function (...$parts) use (&$inClauses, $orResult) {
				$this->assertSame($inClauses, $parts);
				$this->assertCount(3, $parts);
				return $orResult;
			});

		$qb = $this->createMock(IQueryBuilder::class);
		$qb->method('expr')->willReturn($expr);
		$qb->method('createNamedParameter')
			->willReturnCallback(function ($chunk, $type = null) use (&$chunkSizes) {
				$this->assertSame(IQueryBuilder::PARAM_STR_ARRAY, $type);
				$this->assertIsArray($chunk);
				$this->assertLessThanOrEqual(QueryInChunker::MAX_EXPRESSIONS_PER_LIST, count($chunk));
				$this->assertLessThanOrEqual(Constants::BATCH_CHUNK_SIZE, count($chunk));
				$chunkSizes[] = count($chunk);
				return 'chunk-' . count($chunkSizes);
			});

		$this->assertSame(
			$orResult,
			QueryInChunker::in($qb, 'user_id', $values, IQueryBuilder::PARAM_STR_ARRAY),
		);
		$this->assertSame([500, 500, 1], $chunkSizes);
	}

	public function testCustomChunkSizeIsHonoredWhenBelowCeiling(): void
	{
		$values = range(1, 5);
		$chunkSizes = [];
		$orResult = $this->createMock(ICompositeExpression::class);
		$expr = $this->createMock(IExpressionBuilder::class);
		$expr->method('in')->willReturnCallback(static fn ($f, $p) => $p);
		$expr->method('orX')->willReturn($orResult);

		$qb = $this->createMock(IQueryBuilder::class);
		$qb->method('expr')->willReturn($expr);
		$qb->method('createNamedParameter')
			->willReturnCallback(function ($chunk, $type = null) use (&$chunkSizes) {
				$this->assertSame(IQueryBuilder::PARAM_INT_ARRAY, $type);
				$chunkSizes[] = count($chunk);
				return 'c' . count($chunkSizes);
			});

		$this->assertSame(
			$orResult,
			QueryInChunker::in($qb, 'team_id', $values, IQueryBuilder::PARAM_INT_ARRAY, 2),
		);
		$this->assertSame([2, 2, 1], $chunkSizes);
	}
}
