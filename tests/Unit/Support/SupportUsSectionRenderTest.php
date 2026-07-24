<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Support;

use OCA\ArbeitszeitCheck\Support\SupportUsLinks;
use PHPUnit\Framework\TestCase;

/**
 * Integration-style render of the Support & Us partial (escaped HTML contract).
 *
 * Runs without a full Nextcloud kernel: stubs IL10N and p()/print_unescaped helpers.
 */
final class SupportUsSectionRenderTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		require_once dirname(__DIR__, 3) . '/tests/Unit/Support/template_stubs.php';
	}

	public function testRenderEscapesDisplayNameAndOmitsMobileWithoutFlag(): void {
		$html = $this->renderSection(
			new SupportUsLinks('ArbeitszeitCheck', false, null),
			'en'
		);
		self::assertStringContainsString('data-support-us="1"', $html);
		self::assertStringContainsString('Check Partner', $html);
		self::assertStringContainsString('invoiceable service', $html);
		self::assertStringContainsString('individual partner offer', $html);
		self::assertStringContainsString('Ask for a partner offer', $html);
		self::assertStringContainsString('billed as a service', $html);
		self::assertStringContainsString('billed as project work', $html);
		self::assertStringContainsString('mailto:info@software-by-design.de?subject=', $html);
		self::assertStringContainsString(rawurlencode('ArbeitszeitCheck: partner / care retainer'), $html);
		self::assertStringContainsString('noopener noreferrer', $html);
		self::assertStringNotContainsString('Official mobile & terminal licenses', $html);
		self::assertStringContainsString('bookable help on an invoice, choose an option below', $html);
		self::assertStringNotContainsString('official mobile licenses', $html);
		self::assertStringNotContainsString('490', $html);
		self::assertStringNotContainsString('<script', $html);
	}

	public function testRenderIncludesMobileLicenseWhenConfigured(): void {
		$html = $this->renderSection(
			new SupportUsLinks(
				'ArbeitszeitCheck',
				true,
				'/apps/arbeitszeitcheck/admin/license'
			),
			'de'
		);
		self::assertStringContainsString('Official mobile &amp; terminal licenses', $html);
		self::assertStringContainsString('software licence on invoice', $html);
		self::assertStringContainsString('href="/apps/arbeitszeitcheck/admin/license"', $html);
		self::assertStringContainsString(rawurlencode('ArbeitszeitCheck: Partner / Care Retainer'), $html);
		self::assertStringContainsString('official mobile licenses', $html);
		self::assertStringNotContainsString('bookable help on an invoice, choose an option below', $html);
	}

	public function testRenderUsesGermanIntroViaL10nCallback(): void {
		$html = $this->renderSection(
			new SupportUsLinks('ArbeitszeitCheck', false, null),
			'de',
			[
				'Support & us' => 'Support & wir',
				'Ask for a partner offer' => 'Partner-Angebot anfragen',
				'Check Partner' => 'Check Partner',
				'Annual hour packs — Small, Standard, or Premium — with priority email for your organisation. This is invoiceable service — not a donation. See packages on our support page.' =>
					'Jährliche Stundenpakete — Small, Standard oder Premium — plus priorisierte E-Mail für Ihre Organisation. Verrechenbare Leistung, keine Spende. Pakete auf unserer Support-Seite.',
			]
		);
		self::assertStringContainsString('Support &amp; wir', $html);
		self::assertStringContainsString('Partner-Angebot anfragen', $html);
		self::assertStringContainsString('Verrechenbare Leistung', $html);
	}

	/**
	 * @param array<string, string> $map
	 */
	private function renderSection(SupportUsLinks $supportUsLinks, string $lang, array $map = []): string {
		$l = new class ($lang, $map) {
			/** @param array<string, string> $map */
			public function __construct(private string $lang, private array $map) {
			}

			public function getLanguageCode(): string {
				return $this->lang;
			}

			public function t(string $text, array $parameters = []): string {
				$out = $this->map[$text] ?? $text;
				if ($parameters !== []) {
					$out = str_replace('%s', (string)$parameters[0], $out);
				}
				return $out;
			}
		};

		$supportUsCssPrefix = 'azc';
		$supportUsBtnPrimaryClass = 'button primary';
		$supportUsBtnSecondaryClass = 'button';
		$supportUsLanguageCode = $lang;

		ob_start();
		include dirname(__DIR__, 3) . '/templates/parts/support-us-section.php';
		$html = (string)ob_get_clean();
		self::assertNotSame('', trim($html));
		return $html;
	}
}
