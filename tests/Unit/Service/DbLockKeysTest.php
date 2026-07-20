<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service;

use OCA\ArbeitszeitCheck\Service\DbLockKeys;
use PHPUnit\Framework\TestCase;

class DbLockKeysTest extends TestCase
{
	public function testAllKeysFitFileLocksVarchar64(): void
	{
		$longUid = str_repeat('u', 128);
		$keys = [
			DbLockKeys::timeTrackingUser($longUid),
			DbLockKeys::absenceUser($longUid),
			DbLockKeys::monthClosure($longUid, 2026, 12),
			DbLockKeys::entitlementSnapshot($longUid, 2026, '2026-12-31'),
		];
		foreach ($keys as $key) {
			$this->assertLessThanOrEqual(64, strlen($key), $key);
		}
	}

	public function testKeysAreStableAndDistinct(): void
	{
		$this->assertSame(
			DbLockKeys::timeTrackingUser('alice'),
			DbLockKeys::timeTrackingUser('alice'),
		);
		$this->assertNotSame(
			DbLockKeys::timeTrackingUser('alice'),
			DbLockKeys::timeTrackingUser('bob'),
		);
		$this->assertNotSame(
			DbLockKeys::timeTrackingUser('alice'),
			DbLockKeys::absenceUser('alice'),
		);
		$this->assertNotSame(
			DbLockKeys::monthClosure('alice', 2026, 1),
			DbLockKeys::monthClosure('alice', 2026, 2),
		);
	}

	public function testLegacyShapesExceedLimitForRealisticUids(): void
	{
		$uid = 'guest_very_long_username_example_de';
		$legacyEntitlement = 'arbeitszeitcheck/entitlement-snapshot/' . $uid . '/2026/2026-07-20';
		$legacyMonth = sprintf('arbeitszeitcheck/month-closure/%s/%04d-%02d', $uid, 2026, 7);
		$legacyTime = 'arbeitszeitcheck/time-tracking-user/' . $uid;
		$this->assertGreaterThan(64, strlen($legacyEntitlement));
		$this->assertGreaterThan(64, strlen($legacyMonth));
		$this->assertGreaterThan(64, strlen($legacyTime));
		$this->assertLessThanOrEqual(64, strlen(DbLockKeys::entitlementSnapshot($uid, 2026, '2026-07-20')));
		$this->assertLessThanOrEqual(64, strlen(DbLockKeys::monthClosure($uid, 2026, 7)));
		$this->assertLessThanOrEqual(64, strlen(DbLockKeys::timeTrackingUser($uid)));
		$this->assertLessThanOrEqual(64, strlen(DbLockKeys::absenceUser($uid)));
	}
}
