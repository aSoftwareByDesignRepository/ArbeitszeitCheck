<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Contract: azc-btn anchors keep solid fills (not demoted to underlined links).
 *
 * Historic bug: #app-content-wrapper a rules (base/typography/a11y) were more
 * specific than .azc-btn--primary and wiped background + forced underline.
 */
final class AzcBtnCascadeContractTest extends TestCase {
	public function testLinkResetsExcludeAzcBtn(): void {
		$root = dirname(__DIR__, 2);
		$files = [
			$root . '/css/common/base.css',
			$root . '/css/common/typography.css',
			$root . '/css/common/accessibility.css',
		];
		foreach ($files as $path) {
			$css = (string)file_get_contents($path);
			self::assertStringContainsString('a:not(.btn):not(.azc-btn)', $css, $path);
			self::assertDoesNotMatchRegularExpression(
				'/#app-content-wrapper a\s*\{/',
				$css,
				'Unscoped wrapper link rule must not remain in ' . $path
			);
		}
	}

	public function testAzcBtnPrimaryHasSolidFillAndHover(): void {
		$css = (string)file_get_contents(dirname(__DIR__, 2) . '/css/app.css');
		self::assertStringContainsString('#app-content.azc-app .azc-btn--primary', $css);
		self::assertStringContainsString('#app-content-wrapper .azc-btn--primary', $css);
		self::assertStringContainsString('color-primary-element-text', $css);
		self::assertStringContainsString('.azc-btn--primary:hover', $css);
		self::assertStringContainsString('.azc-btn--secondary:hover', $css);
		self::assertStringContainsString('text-decoration: none !important', $css);
		self::assertMatchesRegularExpression(
			'/background-color:\s*var\(--color-primary-element[^)]*\)\s*!important/',
			$css,
			'Primary fill must use !important so link/theme cascades cannot flatten CTAs'
		);
		self::assertMatchesRegularExpression(
			'/#app-content\.azc-app \.azc-btn--secondary[\s\S]*?background-color:[^;]*!important/',
			$css,
			'Secondary fill must use !important against link demotion'
		);
		self::assertStringContainsString('min-height: 2.75rem', $css);
	}
}
