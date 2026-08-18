<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Support;

use OCA\ArbeitszeitCheck\Support\MobileAppLinks;
use PHPUnit\Framework\TestCase;

/**
 * Contract: Get the App nav + route + PHP template + Play Store security attributes.
 */
final class GetTheAppPageContractTest extends TestCase
{
	private string $root;

	protected function setUp(): void
	{
		parent::setUp();
		$this->root = dirname(__DIR__, 3);
	}

	public function testRouteAndControllerExist(): void
	{
		$routes = (string) file_get_contents($this->root . '/appinfo/routes.php');
		self::assertStringContainsString("page#getTheApp", $routes);
		self::assertStringContainsString("'/get-the-app'", $routes);

		$php = (string) file_get_contents($this->root . '/lib/Controller/PageController.php');
		self::assertStringContainsString('function getTheApp(', $php);
		self::assertStringContainsString("'get-the-app'", $php);
		self::assertStringContainsString('Get the App', $php);
		self::assertStringContainsString('MobileAppLinks', $php);
		self::assertStringContainsString("'playStore'", $php);
		self::assertStringContainsString("'kioskPlayStore'", $php);

		$shell = (string) file_get_contents($this->root . '/lib/Controller/PageShellTrait.php');
		self::assertStringContainsString('arbeitszeitcheck.page.getTheApp', $shell);
	}

	public function testNavPlacedAfterMySettings(): void
	{
		$nav = (string) file_get_contents($this->root . '/templates/common/navigation.php');
		$settingsPos = strpos($nav, "arbeitszeitcheck.page.settings");
		$getAppPos = strpos($nav, "arbeitszeitcheck.page.getTheApp");
		self::assertNotFalse($settingsPos);
		self::assertNotFalse($getAppPos);
		self::assertGreaterThan($settingsPos, $getAppPos);
		self::assertStringContainsString("azcNavIcon('smartphone')", $nav);
	}

	public function testTemplateWiresPlayStoreSafely(): void
	{
		$tpl = (string) file_get_contents($this->root . '/templates/get-the-app.php');
		self::assertStringContainsString('azc-get-app__hero', $tpl);
		self::assertStringContainsString('azc-get-app__features', $tpl);
		self::assertStringContainsString('azc-get-app__actions', $tpl);
		self::assertStringContainsString('azc-get-app__play', $tpl);
		self::assertStringContainsString('azc-btn azc-btn--primary azc-get-app__play', $tpl);
		self::assertStringContainsString('rel="noopener noreferrer"', $tpl);
		self::assertStringContainsString('target="_blank"', $tpl);
		self::assertStringContainsString('MobileAppLinks::PLAY_STORE_URL', $tpl);
		self::assertStringContainsString('MobileAppLinks::KIOSK_PLAY_STORE_URL', $tpl);
		self::assertStringContainsString("str_starts_with(\$playStore, 'https://play.google.com/')", $tpl);
		self::assertStringContainsString("str_starts_with(\$kioskPlay, 'https://play.google.com/')", $tpl);
		self::assertStringNotContainsString('The Nextcloud web app stays free (AGPL)', $tpl);
	}

	public function testIconsAndChromeIncludeSmartphone(): void
	{
		$catalog = (string) file_get_contents($this->root . '/lib/Service/IconCatalog.php');
		$start = (string) file_get_contents($this->root . '/templates/common/page-start.php');
		self::assertStringContainsString("'smartphone'", $catalog);
		self::assertStringContainsString("'get-the-app' => 'smartphone'", $start);
	}

	public function testCssSeparatesStaticFeaturesFromActionButtons(): void
	{
		$css = (string) file_get_contents($this->root . '/css/get-the-app.css');
		self::assertMatchesRegularExpression(
			'/\.azc-get-app__hero[^{]*\{[^}]*linear-gradient/s',
			$css,
		);
		self::assertMatchesRegularExpression(
			'/\.azc-get-app__feature-copy[^{]*\{[^}]*flex-direction:\s*column/s',
			$css,
		);
		self::assertMatchesRegularExpression(
			'/\.azc-get-app__feature[^{]*\{[^}]*cursor:\s*default/s',
			$css,
		);
		self::assertMatchesRegularExpression(
			'/\.azc-get-app__feature[^{]*\{[^}]*background:\s*transparent/s',
			$css,
		);
		self::assertMatchesRegularExpression(
			'/#app-content\.azc-app--get-the-app a\.azc-get-app__play[^{]*\{[^}]*background-color:\s*var\(--color-primary-element\)\s*!important/s',
			$css,
		);
		self::assertMatchesRegularExpression(
			'/\.azc-get-app__action[^{]*\{[^}]*cursor:\s*pointer/s',
			$css,
		);
		self::assertMatchesRegularExpression(
			'/\.azc-get-app__action[^{]*\{[^}]*border:\s*2px\s+solid\s+var\(--color-primary-element\)/s',
			$css,
		);
		self::assertMatchesRegularExpression(
			'/\.azc-get-app__action[^{]*\{[^}]*text-decoration:\s*none/s',
			$css,
		);
		self::assertStringContainsString('prefers-contrast', $css);
		self::assertStringContainsString('forced-colors', $css);
		self::assertStringContainsString('min-width: var(--azc-touch, 44px)', $css);
		self::assertStringContainsString(':focus:not(:focus-visible)', $css);
		self::assertStringContainsString('var(--color-primary-element)', $css);
		self::assertStringContainsString('var(--color-primary-element-text)', $css);
		self::assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{3,8}\\b/', $css);
		self::assertSame(MobileAppLinks::PLAY_STORE_PACKAGE_ID, 'de.softwarebydesign.arbeitszeitcheck');
		self::assertTrue(MobileAppLinks::PLAY_LISTED);
	}

	public function testGetTheAppCopyIsTranslatedInFooterLocales(): void
	{
		$allowIdentity = [];
		$locales = ['de', 'fr', 'es', 'da', 'nl', 'it', 'pl', 'sv', 'nb', 'pt_BR'];
		$keys = $this->getTheAppSourceKeys();
		self::assertNotEmpty($keys);
		foreach ($locales as $locale) {
			$jsonPath = $this->root . '/l10n/' . $locale . '.json';
			$jsPath = $this->root . '/l10n/' . $locale . '.js';
			self::assertFileExists($jsonPath, $locale . ' catalog missing');
			self::assertFileExists($jsPath, $locale . ' JS catalog missing');
			$decoded = json_decode((string) file_get_contents($jsonPath), true, 512, JSON_THROW_ON_ERROR);
			self::assertIsArray($decoded);
			$translations = $decoded['translations'] ?? null;
			self::assertIsArray($translations);
			$js = (string) file_get_contents($jsPath);
			foreach ($keys as $key) {
				self::assertArrayHasKey($key, $translations, $locale . ' missing Get the App key: ' . $key);
				$value = $translations[$key];
				self::assertIsString($value);
				if (!in_array($key, $allowIdentity, true)) {
					self::assertNotSame($key, $value, $locale . ' left Get the App copy in English: ' . $key);
				}
				$encodedKey = json_encode($key, JSON_UNESCAPED_UNICODE);
				self::assertIsString($encodedKey);
				self::assertStringContainsString($encodedKey, $js, $locale . ' JS missing key: ' . $key);
				$encodedVal = json_encode($value, JSON_UNESCAPED_UNICODE);
				self::assertIsString($encodedVal);
				self::assertStringContainsString($encodedVal, $js, $locale . ' JS missing translation for: ' . $key);
			}
		}
	}

	/**
	 * @return list<string>
	 */
	private function getTheAppSourceKeys(): array
	{
		$keys = [];
		$tpl = (string) file_get_contents($this->root . '/templates/get-the-app.php');
		if (preg_match_all("/\\\$l->t\\('((?:\\\\'|[^'])*)'\\)/", $tpl, $matches) === false) {
			self::fail('Failed to parse get-the-app.php l10n keys');
		}
		foreach ($matches[1] as $raw) {
			$keys[] = str_replace("\\'", "'", $raw);
		}
		$controller = (string) file_get_contents($this->root . '/lib/Controller/PageController.php');
		if (preg_match('/function getTheApp\b[\s\S]{0,2500}/', $controller, $block) === 1
			&& preg_match_all("/->(?:l10n|l)->t\\('((?:\\\\'|[^'])*)'\\)/", $block[0], $ctrlMatches) !== false) {
			foreach ($ctrlMatches[1] as $raw) {
				$keys[] = str_replace("\\'", "'", $raw);
			}
		}
		return array_values(array_unique($keys));
	}
}
