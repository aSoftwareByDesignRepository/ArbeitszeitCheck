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
		self::assertStringContainsString('card__header', $src);
		self::assertStringContainsString('-card__header', $src);
		self::assertStringNotContainsString('-section__header', $src);
		self::assertStringContainsString('card__body', $src);
		self::assertStringContainsString('-card__body', $src);
		self::assertStringContainsString('card__lead', $src);
		self::assertStringContainsString('admin-settings-section', $src);
		self::assertStringContainsString('support-us__secondary-title', $src);
		self::assertStringContainsString('support-us__option-title', $src);
		self::assertStringContainsString('support-us__primary-copy', $src);
		self::assertStringContainsString('Setup & training', $src);
		self::assertStringContainsString('Commissioned feature', $src);
		self::assertStringContainsString('Mobile & terminal', $src);
		self::assertStringContainsString('Recommended', $src);
		self::assertStringContainsString("supportUsPresentation === 'page'", $src);
		self::assertStringContainsString('data-support-us-presentation', $src);
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
			$root . '/css/admin-support-us.css',
			$root . '/css/stockcheck.css',
		];
		$css = '';
		foreach ($candidates as $path) {
			if (is_file($path)) {
				$css .= (string)file_get_contents($path);
			}
		}
		self::assertStringContainsString('azc-support-us', $css);
		self::assertStringContainsString('azc-support-us-page', $css);
		self::assertStringContainsString(':focus-visible', $css);
		self::assertStringContainsString('prefers-reduced-motion', $css);
		self::assertStringContainsString('min-height: 2.75rem', $css);
		self::assertStringContainsString('support-us__option', $css);
		self::assertStringContainsString('support-us__option-title', $css);
		self::assertStringContainsString('support-us__benefit', $css);
		self::assertStringContainsString('support-us__coverage', $css);
		self::assertStringContainsString('support-us__options', $css);
		self::assertStringContainsString('support-us__eyebrow', $css);
		self::assertStringContainsString('support-us__primary-actions', $css);
		self::assertStringContainsString('support-us__primary-copy', $css);
		self::assertStringContainsString('azc-support-us-page__hero-main', $css);
		self::assertStringContainsString('azc-support-us-page__lockup', $css);
		self::assertStringContainsString('azc-support-us-page__wordmark-by', $css);
		self::assertStringContainsString(
			'#app-content.azc-app--admin-support-us ul.azc-support-us-page__trust',
			$css,
			'Trust list must beat #app-content-wrapper ul padding-left'
		);
		self::assertMatchesRegularExpression(
			'/#app-content\.azc-app--admin-support-us ul\.azc-support-us-page__trust\s*\{[^}]*list-style:\s*none/s',
			$css
		);
		self::assertMatchesRegularExpression(
			'/#app-content\.azc-app--admin-support-us \.azc-support-us-page\s*,\s*\.azc-support-us-page\s*\{[^}]*max-width:\s*none/s',
			$css,
			'Support Us page root must fill the shell'
		);
		self::assertMatchesRegularExpression(
			'/\.azc-support-us--page \.azc-support-us__primary\s*\{[^}]*max-width:\s*none/s',
			$css,
			'Page partner spotlight must not stay capped at 48rem'
		);
		self::assertStringContainsString(
			'a.azc-support-us__cta.azc-btn--primary',
			$css,
			'Page-scoped primary CTA reinforcement must remain'
		);
		self::assertMatchesRegularExpression(
			'/a\.azc-support-us__cta\.azc-btn--primary\s*\{[^}]*background-color:[^;]*!important/s',
			$css
		);
		self::assertStringContainsString('background-color: #ffffff !important', $css);
	}

	public function testSupportUsLivesOnDedicatedAdminPageNotSettingsEmbed(): void {
		$root = dirname(__DIR__, 3);
		$settings = (string)file_get_contents($root . '/templates/admin-settings.php');
		$page = (string)file_get_contents($root . '/templates/admin-support-us.php');
		$routes = (string)file_get_contents($root . '/appinfo/routes.php');
		$nav = (string)file_get_contents($root . '/templates/common/navigation.php');

		self::assertStringNotContainsString('support-us-section.php', $settings);
		self::assertStringNotContainsString('#azc-support-us-title', $settings);
		self::assertStringContainsString('supportUsUrl', $settings);
		self::assertStringContainsString('Open Support & us', $settings);

		self::assertStringContainsString('support-us-section.php', $page);
		self::assertStringContainsString("supportUsPresentation = 'page'", $page);
		self::assertStringContainsString('azc-support-us-page__hero', $page);
		self::assertStringContainsString('azc-support-us-page__hero-main', $page);
		self::assertStringContainsString('data-azc-support-us-layout="offer-grid"', $page);
		self::assertStringContainsString('SupportUsLinks', $page);
		self::assertStringContainsString('vendor-logo-mark.png', $page);
		self::assertStringContainsString('data-azc-vendor-logo="1"', $page);
		self::assertStringContainsString('azc-support-us-page__wordmark-by', $page);
		self::assertStringContainsString('BY DESIGN', $page);
		self::assertStringContainsString('rel="noopener noreferrer"', $page);
		$section = (string)file_get_contents($root . '/templates/parts/support-us-section.php');
		self::assertStringContainsString('support-us__cta--primary', $section);
		self::assertStringContainsString('support-us__cta--secondary', $section);
		self::assertFileExists($root . '/img/vendor-logo-mark.png');
		self::assertFileExists($root . '/img/vendor-logo-sbd.svg');
		$logo = (string)file_get_contents($root . '/img/vendor-logo-sbd.svg');
		self::assertStringContainsString('Software by Design', $logo);
		self::assertStringNotContainsString('<script', $logo);

		self::assertStringContainsString("admin#supportUs", $routes);
		self::assertStringContainsString('/admin/support-us', $routes);
		self::assertStringContainsString('admin.supportUs', $nav);
		self::assertStringContainsString('admin-support-us', $nav);
	}
}
