<?php

declare(strict_types=1);

/**
 * Rollover audit amounts must not hard-cap at 366 in hours mode.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\Db;

use OCA\ArbeitszeitCheck\Db\VacationRolloverLog;
use OCA\ArbeitszeitCheck\Db\VacationRolloverLogMapper;
use OCP\AppFramework\Db\Entity;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

class VacationRolloverLogMapperHoursCapTest extends TestCase
{
	public function testInsertLogAllowsHourAmountsAbove366(): void
	{
		$db = $this->createMock(IDBConnection::class);
		$mapper = new class ($db) extends VacationRolloverLogMapper {
			public ?VacationRolloverLog $last = null;

			public function insert(Entity $entity): Entity
			{
				$this->last = $entity instanceof VacationRolloverLog ? $entity : null;
				return $entity;
			}
		};

		$mapper->insertLog('alice', 2025, 2026, 392.5);
		$this->assertNotNull($mapper->last);
		$this->assertSame(392.5, $mapper->last->getAmount());
	}

	public function testInsertLogStillCapsAbsurdAmountsAt4000(): void
	{
		$db = $this->createMock(IDBConnection::class);
		$mapper = new class ($db) extends VacationRolloverLogMapper {
			public ?VacationRolloverLog $last = null;

			public function insert(Entity $entity): Entity
			{
				$this->last = $entity instanceof VacationRolloverLog ? $entity : null;
				return $entity;
			}
		};

		$mapper->insertLog('alice', 2025, 2026, 99999.0);
		$this->assertNotNull($mapper->last);
		$this->assertSame(4000.0, $mapper->last->getAmount());
	}

	public function testInsertLogRejectsNegatives(): void
	{
		$db = $this->createMock(IDBConnection::class);
		$mapper = new class ($db) extends VacationRolloverLogMapper {
			public ?VacationRolloverLog $last = null;

			public function insert(Entity $entity): Entity
			{
				$this->last = $entity instanceof VacationRolloverLog ? $entity : null;
				return $entity;
			}
		};

		$mapper->insertLog('alice', 2025, 2026, -12.0);
		$this->assertNotNull($mapper->last);
		$this->assertSame(0.0, $mapper->last->getAmount());
	}
}
