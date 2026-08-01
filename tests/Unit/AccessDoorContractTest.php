<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Portfolio ACCESS-AND-DIRECTORY-PICKERS: AZC must expose Open/Restricted +
 * directory pickers (never raw UID inputs) and keep the door role-free.
 */
final class AccessDoorContractTest extends TestCase
{
	public function testAdminSettingsExposeOpenRestrictedAndUserPicker(): void
	{
		$html = (string) file_get_contents(dirname(__DIR__, 2) . '/templates/admin-settings.php');
		self::assertStringContainsString('name="accessRestrictionEnabled"', $html);
		self::assertStringContainsString('Open — every logged-in Nextcloud user', $html);
		self::assertStringContainsString('Restricted — only allow-listed', $html);
		self::assertStringContainsString('name="accessAllowedUserIds[]"', $html);
		self::assertStringContainsString('data-azc-access-user-add', $html);
		self::assertStringContainsString('Search and pick people. Never type a raw user id.', $html);
		self::assertStringNotContainsString('type="text" name="accessAllowedUserIds"', $html);
		self::assertStringNotContainsString('Nextcloud user id, e.g.', $html);
	}

	public function testPermissionDoorIsRoleFree(): void
	{
		$src = (string) file_get_contents(dirname(__DIR__, 2) . '/lib/Service/PermissionService.php');
		$start = strpos($src, 'public function isUserAllowedByAccessGroups(string $userId): bool');
		self::assertNotFalse($start);
		$end = strpos($src, 'public function isAdmin(string $userId): bool', $start);
		self::assertNotFalse($end);
		$fn = substr($src, $start, $end - $start);
		self::assertStringContainsString('isAccessRestrictionEnabled', $fn);
		self::assertStringContainsString('return true;', $fn);
		self::assertStringNotContainsString('canManageEmployee', $fn);
		self::assertStringNotContainsString('TeamResolver', $fn);
	}
}
