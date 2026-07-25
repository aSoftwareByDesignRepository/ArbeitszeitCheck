<?php

declare(strict_types=1);

/**
 * Product-scope contract: ArbeitszeitCheck is DACH (DE/AT/CH), not Germany-only.
 *
 * Prevents packaging / README / composer / store metadata from regressing to
 * ArbZG-only claims while the runtime ships AT/CH profiles.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\AppInfo;

use PHPUnit\Framework\TestCase;

class DachProductScopeContractTest extends TestCase {
	private string $root;

	protected function setUp(): void {
		$this->root = dirname(__DIR__, 3);
	}

	public function testInfoXmlSummariesClaimDach(): void {
		$xml = file_get_contents($this->root . '/appinfo/info.xml');
		$this->assertNotFalse($xml);
		$this->assertStringContainsString('DACH', $xml);
		$this->assertStringContainsString('AZG', $xml);
		$this->assertStringContainsString('ArG', $xml);
		$this->assertMatchesRegularExpression(
			'/<summary[^>]*>[^<]*DACH[^<]*<\/summary>/',
			$xml
		);
		$this->assertStringContainsString('Germany, Austria and Switzerland', $xml);
		$this->assertStringContainsString('Deutschland, Österreich und die Schweiz', $xml);
		// Must not claim Germany-only in the English summary
		$this->assertDoesNotMatchRegularExpression(
			'/<summary>Time tracking for German labor law only/',
			$xml
		);
	}

	public function testComposerDescriptionIsDach(): void {
		$raw = file_get_contents($this->root . '/composer.json');
		$this->assertNotFalse($raw);
		$data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
		$desc = (string)($data['description'] ?? '');
		$this->assertStringContainsString('DACH', $desc);
		$this->assertStringContainsString('AZG', $desc);
		$this->assertStringContainsString('ArG', $desc);
		$this->assertStringNotContainsString('for German labor law (ArbZG) and GDPR', $desc);
	}

	public function testPackageJsonKeywordsIncludeDachCountries(): void {
		$raw = file_get_contents($this->root . '/package.json');
		$this->assertNotFalse($raw);
		$data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
		$keywords = $data['keywords'] ?? [];
		$this->assertContains('dach', $keywords);
		$this->assertContains('azg', $keywords);
		$this->assertContains('austria', $keywords);
		$this->assertContains('switzerland', $keywords);
		$desc = (string)($data['description'] ?? '');
		$this->assertStringContainsString('DACH', $desc);
	}

	public function testReadmeClaimsDachNotGermanyOnly(): void {
		$readme = file_get_contents($this->root . '/README.md');
		$this->assertNotFalse($readme);
		$this->assertStringContainsString('DACH', $readme);
		$this->assertStringContainsString('Österreich', $readme);
		$this->assertStringContainsString('Schweiz', $readme);
		$this->assertStringContainsString('AZG', $readme);
		$this->assertStringContainsString('ArG', $readme);
		$this->assertStringNotContainsString(
			'explizit auf **deutsches Arbeitszeitgesetz (ArbZG)** und **DSGVO/ GDPR** ausgerichtet',
			$readme
		);
		$this->assertStringNotContainsString(
			'technical controls for German working time law (ArbZG) and GDPR',
			$readme
		);
	}

	public function testActiveAdminUiDoesNotSayFederalStateForHolidays(): void {
		$userDetail = file_get_contents($this->root . '/templates/admin-user-detail.php');
		$l10nPartial = file_get_contents($this->root . '/templates/partials/admin-user-edit-l10n.php');
		$this->assertNotFalse($userDetail);
		$this->assertNotFalse($l10nPartial);
		$this->assertStringContainsString('Choose work schedule and region for holidays', $userDetail);
		$this->assertStringNotContainsString('Choose work schedule and state for holidays', $userDetail);
		$this->assertStringContainsString('organisation default often 20–25 days in DACH', $l10nPartial);
		$this->assertStringNotContainsString('standard in Germany: 25 days', $l10nPartial);
	}

	public function testGermanDeTranslationForDachFooterIsNotEnglishPassthrough(): void {
		$raw = file_get_contents($this->root . '/l10n/de.json');
		$this->assertNotFalse($raw);
		$data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
		$key = 'Professional time tracking and compliance management for Germany, Austria and Switzerland (DACH).';
		$translated = $data['translations'][$key] ?? null;
		$this->assertNotNull($translated);
		$this->assertStringContainsString('Österreich', $translated);
		$this->assertStringContainsString('Schweiz', $translated);
		$this->assertNotSame($key, $translated);
	}

	public function testRegionRegistrySupportsAllThreeCountries(): void {
		$codes = \OCA\ArbeitszeitCheck\Support\RegionRegistry::supportedCountries();
		$this->assertSame(['DE', 'AT', 'CH'], $codes);
		$this->assertTrue(\OCA\ArbeitszeitCheck\Support\RegionRegistry::isValidRegion('AT-OOE'));
		$this->assertTrue(\OCA\ArbeitszeitCheck\Support\RegionRegistry::isValidRegion('CH-ZH'));
		$this->assertTrue(\OCA\ArbeitszeitCheck\Support\RegionRegistry::isValidRegion('NW'));
	}
}
