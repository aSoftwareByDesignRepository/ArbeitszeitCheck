<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Support;

use OCA\ArbeitszeitCheck\Support\MobileAppLinks;
use PHPUnit\Framework\TestCase;

/**
 * Pins Play Store / product URLs for Get the App (no user input in hrefs).
 */
final class MobileAppLinksTest extends TestCase
{
	public function testPlayStoreUrlsAreCanonicalPackages(): void
	{
		$links = new MobileAppLinks();
		self::assertSame(
			'https://play.google.com/store/apps/details?id=de.softwarebydesign.arbeitszeitcheck',
			$links->playStoreUrl(),
		);
		self::assertSame('de.softwarebydesign.arbeitszeitcheck', $links->playStorePackageId());
		self::assertSame(
			'https://play.google.com/store/apps/details?id=de.softwarebydesign.arbeitszeitcheck.kiosk',
			$links->kioskPlayStoreUrl(),
		);
		self::assertSame('de.softwarebydesign.arbeitszeitcheck.kiosk', $links->kioskPlayStorePackageId());
		self::assertTrue($links->playListed());
	}

	public function testProductAndPrivacyUrlsAreHttpsOnVendorOrigin(): void
	{
		$links = new MobileAppLinks();
		$enProduct = $links->productPageUrl('en');
		$deProduct = $links->productPageUrl('de_DE');
		$enPrivacy = $links->privacyPageUrl('en');
		$dePrivacy = $links->privacyPageUrl('de');
		$enKiosk = $links->kioskPrivacyPageUrl('en');
		$deKiosk = $links->kioskPrivacyPageUrl('de');

		foreach ([$enProduct, $deProduct, $enPrivacy, $dePrivacy, $enKiosk, $deKiosk] as $url) {
			self::assertStringStartsWith('https://nextcloud.software-by-design.de/', $url);
			self::assertDoesNotMatchRegularExpression('/[\\x00-\\x1F\\x7F]/', $url);
		}
		self::assertStringContainsString('/en/apps/arbeitszeitcheck.html#mobile-app', $enProduct);
		self::assertStringContainsString('/de/apps/arbeitszeitcheck.html#mobile-app', $deProduct);
		self::assertStringContainsString('privacy-arbeitszeitcheck-mobile', $enPrivacy);
		self::assertStringContainsString('datenschutz-arbeitszeitcheck-mobile', $dePrivacy);
		self::assertStringContainsString('privacy-arbeitszeitcheck-terminal', $enKiosk);
		self::assertStringContainsString('datenschutz-arbeitszeitcheck-terminal', $deKiosk);
	}

	public function testGermanLocaleDetection(): void
	{
		$links = new MobileAppLinks();
		self::assertTrue($links->isGermanLocale('de'));
		self::assertTrue($links->isGermanLocale('de_DE'));
		self::assertFalse($links->isGermanLocale('en'));
		self::assertFalse($links->isGermanLocale('den'));
	}
}
