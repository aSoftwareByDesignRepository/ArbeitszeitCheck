<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Css;

use PHPUnit\Framework\TestCase;

/**
 * Pins stacked/admin DL resets against Nextcloud core dt chrome
 * (width: 130px; text-align: end).
 */
final class DefinitionListCssContractTest extends TestCase {
	public function testAdminOvertimePolicyNeutralisesNextcloudCoreDtChrome(): void {
		$css = (string) file_get_contents(dirname(__DIR__, 3) . '/css/admin-dashboard.css');
		self::assertNotSame('', $css);
		self::assertMatchesRegularExpression(
			'/admin-overtime-policy__grid\s+dt[^{]*\{[^}]*text-align:\s*start/s',
			$css,
		);
		self::assertMatchesRegularExpression(
			'/admin-overtime-policy__grid\s+dt[^{]*\{[^}]*width:\s*auto/s',
			$css,
		);
	}

	public function testAdminUserDetailMetaNeutralisesNextcloudCoreDtChrome(): void {
		$css = (string) file_get_contents(dirname(__DIR__, 3) . '/css/admin-users.css');
		self::assertNotSame('', $css);
		self::assertMatchesRegularExpression(
			'/admin-user-detail__meta-item\s+dt[^{]*\{[^}]*text-align:\s*start/s',
			$css,
		);
		self::assertMatchesRegularExpression(
			'/admin-user-detail__meta-item\s+dt[^{]*\{[^}]*width:\s*auto/s',
			$css,
		);
	}

	public function testAbsenceDetailNeutralisesNextcloudCoreDtChrome(): void {
		$css = (string) file_get_contents(dirname(__DIR__, 3) . '/css/absences.css');
		self::assertNotSame('', $css);
		self::assertMatchesRegularExpression(
			'/absence-detail-(?:list\s+dt|label)[^{]*\{[^}]*text-align:\s*start/s',
			$css,
		);
		self::assertMatchesRegularExpression(
			'/absence-detail-(?:list\s+dt|label)[^{]*\{[^}]*width:\s*auto/s',
			$css,
		);
	}
}
