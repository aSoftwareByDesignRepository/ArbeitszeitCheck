<?php

declare(strict_types=1);

/**
 * Guard-logic tests for {@see \OCA\ArbeitszeitCheck\Migration\Version1034Date20260724120000}
 * (retirement of the legacy 'holidays_initialized_state_years' flag, B-2).
 *
 * Full conversion behaviour against a real database is covered by
 * tests/Integration/HolidayMigrationsIntegrationTest.php — these unit tests
 * pin the early-exit and input-validation paths that must never touch the DB.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\Migration;

use OCA\ArbeitszeitCheck\Migration\Version1034Date20260724120000;
use OCP\DB\ISchemaWrapper;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;

class Version1034LegacyFlagRetirementTest extends TestCase
{
	private const LEGACY_KEY = 'holidays_initialized_state_years';

	private function runMigration(IDBConnection $db, IConfig $config): void
	{
		$migration = new Version1034Date20260724120000($db, $config);
		$migration->postSchemaChange(
			$this->createMock(IOutput::class),
			fn (): ISchemaWrapper => $this->createMock(ISchemaWrapper::class),
			[]
		);
	}

	public function testMissingKeyIsANoOp(): void
	{
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')
			->with('arbeitszeitcheck', self::LEGACY_KEY, '')
			->willReturn('');
		$config->expects($this->never())->method('deleteAppValue');

		$db = $this->createMock(IDBConnection::class);
		$db->expects($this->never())->method('getQueryBuilder');
		$db->expects($this->never())->method('tableExists');

		$this->runMigration($db, $config);
	}

	public function testCorruptJsonStillDeletesTheLegacyKey(): void
	{
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturn('{not valid json');
		$config->expects($this->once())
			->method('deleteAppValue')
			->with('arbeitszeitcheck', self::LEGACY_KEY);

		$db = $this->createMock(IDBConnection::class);
		$db->expects($this->never())->method('getQueryBuilder');

		$this->runMigration($db, $config);
	}

	public function testMissingTablesSkipConversionButDeleteTheKey(): void
	{
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturn(json_encode(['NW-2026']));
		$config->expects($this->once())
			->method('deleteAppValue')
			->with('arbeitszeitcheck', self::LEGACY_KEY);

		$db = $this->createMock(IDBConnection::class);
		$db->method('tableExists')->willReturn(false);
		$db->expects($this->never())->method('getQueryBuilder');

		$this->runMigration($db, $config);
	}

	/**
	 * Malformed entries (unknown regions, out-of-range years, non-strings,
	 * garbage) must be skipped without a single query.
	 */
	public function testInvalidEntriesNeverReachTheDatabase(): void
	{
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturn(json_encode([
			'XX-2026',      // unknown region
			'NW-1969',      // year below range
			'NW-2101',      // year above range
			'FR-75-2026',   // unsupported country (valid format, invalid region)
			'CH-XX-2026',   // supported country, unknown canton
			'NW2026',       // missing dash
			'NW-',          // missing year
			42,             // not a string
			null,
			['NW', 2026],
		]));
		$config->expects($this->once())->method('deleteAppValue');

		$db = $this->createMock(IDBConnection::class);
		$db->method('tableExists')->willReturn(true);
		$db->expects($this->never())->method('getQueryBuilder');

		$this->runMigration($db, $config);
	}

	/**
	 * The final-dash split must attribute 'AT-W-2026' to region 'AT-W', not 'AT'.
	 * (Reaching fetchDates proves the entry passed validation as AT-W.)
	 */
	public function testAustrianEntryWithDashesInRegionCodeIsParsed(): void
	{
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturn(json_encode(['AT-W-2026']));

		$db = $this->createMock(IDBConnection::class);
		$db->method('tableExists')->willReturn(true);
		// A valid entry must query at_holidays and at_holiday_suppress; the
		// unit-level mock cannot emulate the full builder chain, so stub it to
		// throw a recognisable marker once reached.
		$db->expects($this->atLeastOnce())
			->method('getQueryBuilder')
			->willReturnCallback(static function (): never {
				throw new \DomainException('reached-db');
			});

		try {
			$this->runMigration($db, $config);
			$this->fail('Expected the valid AT-W entry to reach the database layer');
		} catch (\DomainException $e) {
			$this->assertSame('reached-db', $e->getMessage());
		}
	}
}
