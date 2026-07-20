<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service\Kiosk;

use OCA\ArbeitszeitCheck\Service\Kiosk\KioskCredentialLockKeys;
use OCA\ArbeitszeitCheck\Service\Kiosk\KioskDbLockPurger;
use OCA\ArbeitszeitCheck\Service\Kiosk\KioskEnrollmentLockKeys;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

class KioskDbLockPurgerTest extends TestCase
{
	public function testLegacyTruncatedCapsAtSixtyFour(): void
	{
		$full = 'arbeitszeitcheck/kiosk_enroll_user/' . str_repeat('a', 64);
		$this->assertGreaterThan(64, strlen($full));
		$truncated = KioskDbLockPurger::legacyTruncated($full);
		$this->assertSame(64, strlen($truncated));
		$this->assertSame(substr($full, 0, 64), $truncated);
	}

	public function testPurgeEnrollmentLocksDeletesCurrentAndLegacyKeys(): void
	{
		$userId = 'alice';
		$terminalId = '86464388-e0fd-481d-8eb6-51bb6ace83a4';

		$expected = [
			KioskEnrollmentLockKeys::forTerminal($terminalId),
			KioskDbLockPurger::legacyTruncated('arbeitszeitcheck/kiosk_enroll/' . $terminalId),
			KioskEnrollmentLockKeys::forUser($userId),
			KioskDbLockPurger::legacyTruncated(
				'arbeitszeitcheck/kiosk_enroll_user/' . hash('sha256', $userId),
			),
		];
		sort($expected);

		$db = $this->createMock(IDBConnection::class);
		$db->method('tableExists')->with('file_locks')->willReturn(true);

		$expr = $this->createMock(IExpressionBuilder::class);
		$expr->method('in')->willReturn('in-clause');

		$qb = $this->createMock(IQueryBuilder::class);
		$qb->method('expr')->willReturn($expr);
		$qb->method('delete')->with('file_locks')->willReturnSelf();
		$qb->method('where')->willReturnSelf();
		$qb->method('createNamedParameter')
			->willReturnCallback(function ($value, $type = null) use ($expected) {
				if (is_array($value)) {
					$keys = $value;
					sort($keys);
					TestCase::assertSame($expected, $keys);
					TestCase::assertSame(IQueryBuilder::PARAM_STR_ARRAY, $type);
				}
				return 'param';
			});
		$qb->expects($this->once())->method('executeStatement')->willReturn(4);
		$db->method('getQueryBuilder')->willReturn($qb);

		$purger = new KioskDbLockPurger($db);
		$this->assertSame(4, $purger->purgeEnrollmentLocks($userId, $terminalId));
	}

	public function testPurgeCredentialLocksIncludesLegacyTruncatedKeys(): void
	{
		$userId = 'bob';
		$hash = hash('sha256', $userId);
		$expected = [
			KioskCredentialLockKeys::forRfidAssign($userId),
			KioskCredentialLockKeys::forPinGenerate($userId),
			KioskDbLockPurger::legacyTruncated('arbeitszeitcheck/kiosk_rfid_assign/' . $hash),
			KioskDbLockPurger::legacyTruncated('arbeitszeitcheck/kiosk_pin_gen/' . $hash),
		];
		foreach ($expected as $key) {
			$this->assertLessThanOrEqual(64, strlen($key), $key);
		}
		sort($expected);

		$db = $this->createMock(IDBConnection::class);
		$db->method('tableExists')->with('file_locks')->willReturn(true);

		$expr = $this->createMock(IExpressionBuilder::class);
		$expr->method('in')->willReturn('in-clause');

		$qb = $this->createMock(IQueryBuilder::class);
		$qb->method('expr')->willReturn($expr);
		$qb->method('delete')->willReturnSelf();
		$qb->method('where')->willReturnSelf();
		$qb->method('createNamedParameter')
			->willReturnCallback(function ($value, $type = null) use ($expected) {
				if (is_array($value)) {
					$keys = $value;
					sort($keys);
					TestCase::assertSame($expected, $keys);
					TestCase::assertSame(IQueryBuilder::PARAM_STR_ARRAY, $type);
				}
				return 'param';
			});
		$qb->method('executeStatement')->willReturn(2);
		$db->method('getQueryBuilder')->willReturn($qb);

		$purger = new KioskDbLockPurger($db);
		$this->assertSame(2, $purger->purgeCredentialLocks($userId));
	}

	public function testPurgeKeysNoopsWhenTableMissing(): void
	{
		$db = $this->createMock(IDBConnection::class);
		$db->method('tableExists')->with('file_locks')->willReturn(false);
		$db->expects($this->never())->method('getQueryBuilder');

		$purger = new KioskDbLockPurger($db);
		$this->assertSame(0, $purger->purgeKeys(['azc/eu/abc']));
	}

	public function testEnrollmentAndCredentialKeysStayWithinSixtyFourChars(): void
	{
		$user = 'user-with-a-reasonably-long-uid@example.com';
		$term = '86464388-e0fd-481d-8eb6-51bb6ace83a4';
		foreach ([
			KioskEnrollmentLockKeys::forUser($user),
			KioskEnrollmentLockKeys::forTerminal($term),
			KioskCredentialLockKeys::forRfidAssign($user),
			KioskCredentialLockKeys::forPinGenerate($user),
		] as $key) {
			$this->assertLessThanOrEqual(64, strlen($key), $key);
		}
	}
}
