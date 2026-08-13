<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Contract: admin surfaces that set Content-Type: application/json must
 * normalize empty mutating bodies (same opaque HTTP 400 class as mobile clock-in).
 */
final class MutatingJsonBodyContractTest extends TestCase {
	public function testUtilsExportsNormalizeMutatingFetchInit(): void {
		$utils = (string)file_get_contents(dirname(__DIR__, 2) . '/js/common/utils.js');
		self::assertStringContainsString('normalizeMutatingFetchInit(init', $utils);
		self::assertStringContainsString('JSON.stringify({})', $utils);
	}

	public function testAdminKioskApiNormalizesMutatingBodies(): void {
		$src = (string)file_get_contents(dirname(__DIR__, 2) . '/js/admin-kiosk.js');
		self::assertStringContainsString('normalizeMutatingFetchInit', $src);
		self::assertStringContainsString("method: 'DELETE'", $src);
		self::assertStringContainsString("method: 'POST'", $src);
	}

	public function testAdminLicenseApiNormalizesMutatingBodies(): void {
		$src = (string)file_get_contents(dirname(__DIR__, 2) . '/js/admin-license.js');
		self::assertStringContainsString('normalizeMutatingFetchInit', $src);
		self::assertStringContainsString('apiFetch', $src);
		self::assertStringContainsString("method: 'DELETE'", $src);
	}

	public function testAdminTariffRulesApiNormalizesMutatingBodies(): void {
		$src = (string)file_get_contents(dirname(__DIR__, 2) . '/js/admin-tariff-rules.js');
		self::assertStringContainsString('normalizeMutatingFetchInit', $src);
	}

	public function testAdminHolidaysDeleteNormalizesMutatingBodies(): void {
		$src = (string)file_get_contents(dirname(__DIR__, 2) . '/js/admin-holidays.js');
		self::assertStringContainsString('normalizeMutatingFetchInit', $src);
		self::assertStringContainsString('deleteHoliday', $src);
	}

	public function testDeskletTemplateReadsAssignedConfigFromUnderscoreArray(): void {
		$src = (string)file_get_contents(
			dirname(__DIR__, 2) . '/templates/partials/dashboard-desklet-workspace.php'
		);
		self::assertStringContainsString("\$_['deskletConfig']", $src);
		self::assertStringNotContainsString(
			'is_array($deskletConfig ?? null)',
			$src,
			'Bare $deskletConfig is invisible under OCP\\Template — must use $_[]'
		);
	}
}
