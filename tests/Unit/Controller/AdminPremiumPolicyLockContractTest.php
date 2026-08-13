<?php

declare(strict_types=1);

/**
 * Premium policy exclusive lock: save busy conflict + seal path contract.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\Controller;

use OCA\ArbeitszeitCheck\Constants;
use PHPUnit\Framework\TestCase;

class AdminPremiumPolicyLockContractTest extends TestCase
{
	public function testAdminPolicySaveUsesExclusivePremiumPolicyLock(): void
	{
		$src = file_get_contents(dirname(__DIR__, 2) . '/../lib/Controller/AdminController.php');
		$this->assertNotFalse($src);
		$this->assertStringContainsString('DbLockKeys::premiumPolicy()', $src);
		$this->assertStringContainsString('PREMIUM_POLICY_BUSY', $src);
		$this->assertStringContainsString('Premium policy save', $src);
		$this->assertStringContainsString('LOCK_EXCLUSIVE', $src);
		$this->assertStringContainsString('lockingProvider ?? \\OCP\\Server::get', $src);
	}

	public function testClosureSealUsesExclusivePremiumPolicyLockAndOverride(): void
	{
		$src = file_get_contents(dirname(__DIR__, 2) . '/../lib/Service/PremiumSurchargeService.php');
		$this->assertNotFalse($src);
		$this->assertStringContainsString('DbLockKeys::premiumPolicy()', $src);
		$this->assertStringContainsString('Premium seal snapshot', $src);
		$this->assertStringContainsString('summariseForUser($userId, $start, $end, $policy)', $src);
		$this->assertStringContainsString('?PremiumPolicy $policyOverride', $src);
	}

	public function testPremiumPolicyBusyConstantStable(): void
	{
		$this->assertSame('azc/pp/policy', \OCA\ArbeitszeitCheck\Service\DbLockKeys::premiumPolicy());
		$this->assertLessThanOrEqual(64, strlen(\OCA\ArbeitszeitCheck\Service\DbLockKeys::premiumPolicy()));
	}
}
