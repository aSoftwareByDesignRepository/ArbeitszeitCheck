<?php

declare(strict_types=1);

/**
 * NN-06: closed-month premium snapshot is frozen in the canonical payload.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service;

use OCA\ArbeitszeitCheck\Db\AbsenceMapper;
use OCA\ArbeitszeitCheck\Db\AuditLogMapper;
use OCA\ArbeitszeitCheck\Db\MonthClosureMapper;
use OCA\ArbeitszeitCheck\Db\MonthClosureRevisionMapper;
use OCA\ArbeitszeitCheck\Db\OvertimePayoutMapper;
use OCA\ArbeitszeitCheck\Db\TimeEntryMapper;
use OCA\ArbeitszeitCheck\Service\MonthClosureService;
use OCA\ArbeitszeitCheck\Service\OvertimeBankService;
use OCA\ArbeitszeitCheck\Service\PermissionService;
use OCA\ArbeitszeitCheck\Service\PremiumSurchargeService;
use OCA\ArbeitszeitCheck\Service\ReportingService;
use OCP\IDBConnection;
use OCP\IConfig;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class MonthClosurePremiumSnapshotTest extends TestCase
{
	private function makeService(PremiumSurchargeService $premium): MonthClosureService
	{
		$reporting = $this->createMock(ReportingService::class);
		$reporting->method('generateMonthlyReport')->willReturn(['type' => 'monthly', 'total_hours' => 0]);

		$entries = $this->createMock(TimeEntryMapper::class);
		$entries->method('findByUserAndDateRange')->willReturn([]);
		$absences = $this->createMock(AbsenceMapper::class);
		$absences->method('findByUserAndDateRange')->willReturn([]);

		$bank = $this->createMock(OvertimeBankService::class);
		$bank->method('buildClosureAuditBlock')->willReturn(null);
		$payouts = $this->createMock(OvertimePayoutMapper::class);
		$payouts->method('findByUserAndMonth')->willReturn(null);

		return new MonthClosureService(
			$this->createMock(MonthClosureMapper::class),
			$this->createMock(MonthClosureRevisionMapper::class),
			$reporting,
			$entries,
			$absences,
			$this->createMock(AuditLogMapper::class),
			$this->createMock(IDBConnection::class),
			$this->createMock(IConfig::class),
			$this->createMock(IUserManager::class),
			$this->createMock(LoggerInterface::class),
			$this->createMock(PermissionService::class),
			$bank,
			$payouts,
			null,
			null,
			$premium,
		);
	}

	public function testBuildCanonicalPayloadEmbedsPremiumWhenEnabled(): void
	{
		$premium = $this->createMock(PremiumSurchargeService::class);
		$premium->expects($this->once())
			->method('buildClosureAuditBlock')
			->with('u1', 2026, 8)
			->willReturn([
				'enabled' => true,
				'policy_version' => 4,
				'summary' => ['buckets' => [['id' => 'sunday', 'hours' => 2.0]]],
				'orthogonal_to_saldo' => true,
			]);

		$payload = $this->makeService($premium)->buildCanonicalPayload('u1', 2026, 8);
		$this->assertArrayHasKey('premium', $payload);
		$this->assertSame(4, $payload['premium']['policy_version']);
		$this->assertTrue($payload['premium']['orthogonal_to_saldo']);
		$this->assertSame(2.0, $payload['premium']['summary']['buckets'][0]['hours']);
	}

	public function testBuildCanonicalPayloadOmitsPremiumWhenServiceReturnsNull(): void
	{
		$premium = $this->createMock(PremiumSurchargeService::class);
		$premium->method('buildClosureAuditBlock')->willReturn(null);

		$payload = $this->makeService($premium)->buildCanonicalPayload('u1', 2026, 8);
		$this->assertArrayNotHasKey('premium', $payload);
	}
}
