<?php

declare(strict_types=1);

/**
 * Schema-step tests for {@see \OCA\ArbeitszeitCheck\Migration\Version1035Date20260724130000}
 * (dedupe + unique index on at_holidays, B-1).
 *
 * The dedupe pass against real data is covered by
 * tests/Integration/HolidayMigrationsIntegrationTest.php.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\Migration;

use Doctrine\DBAL\Schema\Table;
use OCA\ArbeitszeitCheck\Migration\Version1035Date20260724130000;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;

class Version1035UniqueIndexTest extends TestCase
{
	private const INDEX_NAME = 'at_hol_st_dt_sc_u';

	private function migration(?IDBConnection $db = null): Version1035Date20260724130000
	{
		return new Version1035Date20260724130000($db ?? $this->createMock(IDBConnection::class));
	}

	public function testPreSchemaChangeSkipsWhenTableMissing(): void
	{
		$db = $this->createMock(IDBConnection::class);
		$db->method('tableExists')->with('at_holidays')->willReturn(false);
		$db->expects($this->never())->method('getQueryBuilder');

		$this->migration($db)->preSchemaChange(
			$this->createMock(IOutput::class),
			fn (): ISchemaWrapper => $this->createMock(ISchemaWrapper::class),
			[]
		);
	}

	public function testChangeSchemaAddsUniqueIndexWhenMissing(): void
	{
		$table = $this->createMock(Table::class);
		$table->method('hasIndex')->with(self::INDEX_NAME)->willReturn(false);
		$table->expects($this->once())
			->method('addUniqueIndex')
			->with(['state', 'date', 'scope'], self::INDEX_NAME);

		$schema = $this->createMock(ISchemaWrapper::class);
		$schema->method('hasTable')->with('at_holidays')->willReturn(true);
		$schema->method('getTable')->with('at_holidays')->willReturn($table);

		$result = $this->migration()->changeSchema(
			$this->createMock(IOutput::class),
			fn (): ISchemaWrapper => $schema,
			[]
		);
		$this->assertSame($schema, $result);
	}

	/**
	 * Idempotency: a re-run finds the index present and must not add it again
	 * (addUniqueIndex on an existing name would throw).
	 */
	public function testChangeSchemaIsIdempotentWhenIndexExists(): void
	{
		$table = $this->createMock(Table::class);
		$table->method('hasIndex')->with(self::INDEX_NAME)->willReturn(true);
		$table->expects($this->never())->method('addUniqueIndex');

		$schema = $this->createMock(ISchemaWrapper::class);
		$schema->method('hasTable')->with('at_holidays')->willReturn(true);
		$schema->method('getTable')->with('at_holidays')->willReturn($table);

		$this->migration()->changeSchema(
			$this->createMock(IOutput::class),
			fn (): ISchemaWrapper => $schema,
			[]
		);
	}

	public function testChangeSchemaSkipsWhenTableMissing(): void
	{
		$schema = $this->createMock(ISchemaWrapper::class);
		$schema->method('hasTable')->with('at_holidays')->willReturn(false);
		$schema->expects($this->never())->method('getTable');

		$this->migration()->changeSchema(
			$this->createMock(IOutput::class),
			fn (): ISchemaWrapper => $schema,
			[]
		);
	}
}
