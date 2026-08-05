<?php

declare(strict_types=1);

/**
 * Bachus: layered defaults accept calendar days and store hours in hours mode.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service;

use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Db\AuditLogMapper;
use OCA\ArbeitszeitCheck\Db\ModelVacationDefaultMapper;
use OCA\ArbeitszeitCheck\Db\OrgVacationDefault;
use OCA\ArbeitszeitCheck\Db\OrgVacationDefaultMapper;
use OCA\ArbeitszeitCheck\Db\TariffRuleSetMapper;
use OCA\ArbeitszeitCheck\Db\TeamMapper;
use OCA\ArbeitszeitCheck\Db\TeamVacationPolicyMapper;
use OCA\ArbeitszeitCheck\Db\WorkingTimeModelMapper;
use OCA\ArbeitszeitCheck\Service\LayeredVacationDefaultsService;
use OCA\ArbeitszeitCheck\Service\VacationUnitService;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Lock\ILockingProvider;
use PHPUnit\Framework\TestCase;

class LayeredVacationAdminDaysConvertTest extends TestCase
{
	private function unitHours(float $hpd = 8.0): VacationUnitService
	{
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static function (string $app, string $key, string $default = '') use ($hpd): string {
				if ($key === Constants::CONFIG_VACATION_UNIT) {
					return Constants::VACATION_UNIT_HOURS;
				}
				if ($key === Constants::CONFIG_VACATION_HOURS_PER_DAY) {
					return (string)$hpd;
				}
				return $default;
			}
		);

		return new VacationUnitService($config);
	}

	private function serviceWithHours(): array
	{
		$orgMapper = $this->createMock(OrgVacationDefaultMapper::class);
		$db = $this->createMock(IDBConnection::class);
		$db->method('beginTransaction');
		$db->method('commit');
		$db->method('rollBack');
		$locking = $this->createMock(ILockingProvider::class);
		$audit = $this->createMock(AuditLogMapper::class);
		$audit->expects(self::once())->method('logAction');

		$bag = new \stdClass();
		$bag->entity = null;
		$orgMapper->method('findOverlappingRanges')->willReturn([]);
		$orgMapper->method('closeOverlappingOpenRows')->willReturn([]);
		$orgMapper->expects(self::once())->method('insert')->willReturnCallback(
			static function (OrgVacationDefault $e) use ($bag): OrgVacationDefault {
				$bag->entity = $e;
				$e->setId(42);
				return $e;
			}
		);

		$svc = new LayeredVacationDefaultsService(
			$orgMapper,
			$this->createMock(ModelVacationDefaultMapper::class),
			$this->createMock(TeamVacationPolicyMapper::class),
			$this->createMock(TeamMapper::class),
			$this->createMock(WorkingTimeModelMapper::class),
			$this->createMock(TariffRuleSetMapper::class),
			$audit,
			$db,
			$locking,
			null,
			null,
			$this->unitHours(8.0)
		);

		return [$svc, $bag];
	}

	public function testUpsertConvertsAdminDaysToStoredHours(): void
	{
		[$svc, $bag] = $this->serviceWithHours();
		$result = $svc->upsertOrgDefault([
			'vacationMode' => Constants::VACATION_MODE_MANUAL_FIXED,
			'manualDays' => 25.0,
			'effectiveFrom' => '2026-01-01',
		], 'admin');

		$this->assertInstanceOf(OrgVacationDefault::class, $bag->entity);
		$this->assertSame(200.0, $bag->entity->getManualDays());
		$this->assertSame(200.0, $result->getManualDays());
	}

	public function testPresentLayerSummaryShowsDaysAndHours(): void
	{
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static function (string $app, string $key, string $default = ''): string {
				if ($key === Constants::CONFIG_VACATION_UNIT) {
					return Constants::VACATION_UNIT_HOURS;
				}
				if ($key === Constants::CONFIG_VACATION_HOURS_PER_DAY) {
					return '8';
				}
				return $default;
			}
		);
		$svc = new LayeredVacationDefaultsService(
			$this->createMock(OrgVacationDefaultMapper::class),
			$this->createMock(ModelVacationDefaultMapper::class),
			$this->createMock(TeamVacationPolicyMapper::class),
			$this->createMock(TeamMapper::class),
			$this->createMock(WorkingTimeModelMapper::class),
			$this->createMock(TariffRuleSetMapper::class),
			$this->createMock(AuditLogMapper::class),
			$this->createMock(IDBConnection::class),
			$this->createMock(ILockingProvider::class),
			null,
			null,
			new VacationUnitService($config)
		);

		$presented = $svc->presentLayerSummary([
			'id' => 1,
			'manualDays' => 200.0,
			'vacationMode' => Constants::VACATION_MODE_MANUAL_FIXED,
		]);
		$this->assertSame(25.0, $presented['manualDays']);
		$this->assertSame(200.0, $presented['manualHours']);
		$this->assertSame(8.0, $presented['hoursPerDay']);
		$this->assertSame(200.0, $presented['manualAmountStored']);
	}

	public function testPresentRoundTripDoesNotDoubleConvert(): void
	{
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static function (string $app, string $key, string $default = ''): string {
				if ($key === Constants::CONFIG_VACATION_UNIT) {
					return Constants::VACATION_UNIT_HOURS;
				}
				if ($key === Constants::CONFIG_VACATION_HOURS_PER_DAY) {
					return '8';
				}
				return $default;
			}
		);
		$svc = new LayeredVacationDefaultsService(
			$this->createMock(OrgVacationDefaultMapper::class),
			$this->createMock(ModelVacationDefaultMapper::class),
			$this->createMock(TeamVacationPolicyMapper::class),
			$this->createMock(TeamMapper::class),
			$this->createMock(WorkingTimeModelMapper::class),
			$this->createMock(TariffRuleSetMapper::class),
			$this->createMock(AuditLogMapper::class),
			$this->createMock(IDBConnection::class),
			$this->createMock(ILockingProvider::class),
			null,
			null,
			new VacationUnitService($config)
		);

		// First present (DB hours → days). Second present must stay stable.
		$once = $svc->presentLayerSummary(['manualDays' => 200.0]);
		$this->assertSame(25.0, $once['manualDays']);
		$twice = $svc->presentLayerSummary($once);
		$this->assertSame(25.0, $twice['manualDays']);
		$this->assertSame(200.0, $twice['manualHours']);
		$this->assertSame(200.0, $twice['manualAmountStored']);
	}
}
