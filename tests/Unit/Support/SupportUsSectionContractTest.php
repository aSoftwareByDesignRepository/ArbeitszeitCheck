<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;

/**
 * Static contract: Support & Us template keeps CTA hierarchy and security attributes.
 */
final class SupportUsSectionContractTest extends TestCase {
	private function template(): string {
		$path = dirname(__DIR__, 3) . '/templates/parts/support-us-section.php';
		$src = file_get_contents($path);
		self::assertNotFalse($src);
		return $src;
	}

	public function testPrimaryCtaIsPartnerMailtoNotSponsors(): void {
		$src = $this->template();
		$partnerPos = strpos($src, 'partnerMailto');
		$sponsorsPos = strpos($src, 'sponsorsUrl');
		self::assertNotFalse($partnerPos);
		self::assertNotFalse($sponsorsPos);
		self::assertLessThan($sponsorsPos, $partnerPos, 'Partner CTA must appear before Sponsors');
		self::assertStringContainsString('Ask for a partner offer', $src);
		self::assertStringContainsString('Check Partner', $src);
		self::assertStringContainsString('invoiceable service', $src);
		self::assertStringContainsString('individual partner offer', $src);
		self::assertStringContainsString('data-support-us="1"', $src);
	}

	public function testExternalLinksUseNoopenerNoreferrer(): void {
		$src = $this->template();
		self::assertSame(
			substr_count($src, 'target="_blank"'),
			substr_count($src, 'rel="noopener noreferrer"')
		);
		self::assertGreaterThanOrEqual(2, substr_count($src, 'rel="noopener noreferrer"'));
	}

	public function testNoHardCodedPrices(): void {
		$src = $this->template();
		self::assertStringNotContainsString('490', $src);
		self::assertStringNotContainsString('990', $src);
		self::assertStringNotContainsString('€', $src);
		self::assertStringNotContainsString('EUR', $src);
	}

	public function testAccessibilityHooksPresent(): void {
		$src = $this->template();
		self::assertStringContainsString('aria-labelledby', $src);
		self::assertStringContainsString('aria-describedby', $src);
		self::assertStringContainsString('role="group"', $src);
		self::assertStringContainsString('Support & us', $src);
		self::assertStringContainsString('aria-hidden="true"', $src);
	}

	public function testMobileLicenseBlockIsConditional(): void {
		$src = $this->template();
		self::assertStringContainsString('hasOfficialMobileLicenses', $src);
		self::assertStringContainsString('Official mobile & terminal licenses', $src);
		self::assertStringContainsString('software licence on invoice', $src);
		self::assertStringContainsString('billed as a service', $src);
		self::assertStringContainsString('billed as project work', $src);
		self::assertStringContainsString(
			'bookable help on an invoice — or official mobile licenses — choose an option below:',
			$src
		);
		self::assertStringContainsString(
			'bookable help on an invoice, choose an option below:',
			$src
		);
	}

	public function testCssContractHasFocusAndReducedMotion(): void {
		$root = dirname(__DIR__, 3);
		$candidates = [
			$root . '/css/app.css',
			$root . '/css/admin-settings.css',
			$root . '/css/stockcheck.css',
		];
		$css = '';
		foreach ($candidates as $path) {
			if (is_file($path)) {
				$css .= (string)file_get_contents($path);
			}
		}
		self::assertStringContainsString('azc-support-us', $css);
		self::assertStringContainsString(':focus-visible', $css);
		self::assertStringContainsString('prefers-reduced-motion', $css);
		self::assertStringContainsString('min-height: 2.75rem', $css);
		self::assertStringContainsString('support-us__option', $css);
		self::assertStringContainsString('support-us__benefit', $css);
		self::assertStringContainsString('support-us__coverage', $css);
	}
}
