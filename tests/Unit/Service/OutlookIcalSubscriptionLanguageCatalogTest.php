<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service;

use OCA\ArbeitszeitCheck\Exception\OutlookIcalSubscriptionBadRequestException;
use OCA\ArbeitszeitCheck\Service\OutlookIcalSubscriptionLanguageCatalog;
use PHPUnit\Framework\TestCase;

final class OutlookIcalSubscriptionLanguageCatalogTest extends TestCase
{
	public function testNormalizeAcceptsCommonVariants(): void
	{
		self::assertSame('de', OutlookIcalSubscriptionLanguageCatalog::normalize('de'));
		self::assertSame('de', OutlookIcalSubscriptionLanguageCatalog::normalize('de-DE'));
		self::assertSame('pt_BR', OutlookIcalSubscriptionLanguageCatalog::normalize('pt-br'));
		self::assertSame('en', OutlookIcalSubscriptionLanguageCatalog::normalize('en_US'));
	}

	public function testNormalizeRejectsUnsupportedAndMalformedCodes(): void
	{
		self::assertNull(OutlookIcalSubscriptionLanguageCatalog::normalize(''));
		self::assertNull(OutlookIcalSubscriptionLanguageCatalog::normalize('xx'));
		self::assertNull(OutlookIcalSubscriptionLanguageCatalog::normalize('../en'));
		self::assertNull(OutlookIcalSubscriptionLanguageCatalog::normalize('en;drop table'));
	}

	public function testAssertSupportedThrowsForInvalidCode(): void
	{
		$this->expectException(OutlookIcalSubscriptionBadRequestException::class);
		OutlookIcalSubscriptionLanguageCatalog::assertSupported('klingon');
	}

	public function testOptionsForUiListsOnlySupportedCodes(): void
	{
		$options = OutlookIcalSubscriptionLanguageCatalog::optionsForUi();
		self::assertNotSame([], $options);
		foreach ($options as $option) {
			self::assertContains($option['code'], OutlookIcalSubscriptionLanguageCatalog::SUPPORTED_CODES);
			self::assertNotSame('', $option['label']);
		}
	}

	public function testResolveDefaultFallsBackToEnglish(): void
	{
		self::assertSame('de', OutlookIcalSubscriptionLanguageCatalog::resolveDefault('de-DE'));
		self::assertSame('en', OutlookIcalSubscriptionLanguageCatalog::resolveDefault('xx'));
	}
}
