<?php

declare(strict_types=1);

/**
 * Contract: admin nav IA — one Policy settings entry + Tariff sibling (no menu-in-menu).
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\Templates;

use PHPUnit\Framework\TestCase;

class AdminNavIaContractTest extends TestCase
{
	public function testAdminSubnavGroupsAndPolicyRoutes(): void
	{
		$nav = file_get_contents(dirname(__DIR__, 3) . '/templates/common/navigation.php');
		$this->assertNotFalse($nav);
		$this->assertStringContainsString('admin-nav-group-policy', $nav);
		$this->assertStringContainsString('Policy settings', $nav);
		$this->assertStringContainsString('$isAdminPolicyPage', $nav);
		$this->assertStringContainsString('arbeitszeitcheck.admin.vacationRules', $nav);
		$this->assertStringContainsString('nav-submenu-group__title', $nav);
		// Nested leave/overtime/alerts sidebar children removed (chips are the switcher).
		$this->assertStringNotContainsString('admin-nav-group-leave', $nav);
		$this->assertStringNotContainsString('admin-nav-group-overtime', $nav);
		$this->assertStringNotContainsString('admin-nav-group-alerts', $nav);
		$this->assertStringNotContainsString('arbeitszeitcheck.admin.overtimeSettings', $nav);
		// Payout deep-links stay on related pages, not sidebar.
		$payouts = file_get_contents(dirname(__DIR__, 3) . '/templates/admin-overtime-payouts.php');
		$this->assertNotFalse($payouts);
		$this->assertStringContainsString('admin.overtimeSettings', $payouts);
		$this->assertStringNotContainsString("admin.notifications') . '#overtime-bank-heading'", $payouts);
	}

	public function testRoutesRegisterOvertimeSettings(): void
	{
		$routes = file_get_contents(dirname(__DIR__, 3) . '/appinfo/routes.php');
		$this->assertNotFalse($routes);
		$this->assertStringContainsString("'admin#overtimeSettings'", $routes);
		$this->assertStringContainsString('/admin/overtime-settings', $routes);
	}

	public function testNavigationCssStylesSubmenuGroups(): void
	{
		$css = file_get_contents(dirname(__DIR__, 3) . '/css/navigation.css');
		$this->assertNotFalse($css);
		$this->assertStringContainsString('.nav-submenu-group__title', $css);
		$this->assertStringContainsString('pointer-events: none', $css);
	}
}
