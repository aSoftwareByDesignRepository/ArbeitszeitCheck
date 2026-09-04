<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service;

use OCA\ArbeitszeitCheck\Service\OutlookIcalSubscriptionFeedService;
use OCP\IL10N;
use OCP\L10N\IFactory;
use PHPUnit\Framework\TestCase;

/**
 * Shared factory for Outlook iCal feed service unit tests.
 */
trait OutlookIcalFeedServiceTestTrait
{
	/**
	 * @param array<string, string> $translations msgId => translated
	 */
	protected function makeFeedService(array $translations = [], string $language = 'en'): OutlookIcalSubscriptionFeedService
	{
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(
			static fn (string $text): string => $translations[$text] ?? $text
		);

		$factory = $this->createMock(IFactory::class);
		$factory->method('get')->willReturn($l10n);
		$factory->method('findLanguage')->willReturn($language);

		return new OutlookIcalSubscriptionFeedService($factory);
	}
}
