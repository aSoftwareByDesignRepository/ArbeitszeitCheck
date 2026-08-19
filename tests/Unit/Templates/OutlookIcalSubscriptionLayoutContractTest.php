<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\Templates;

use PHPUnit\Framework\TestCase;

class OutlookIcalSubscriptionLayoutContractTest extends TestCase
{
	public function testOutlookPartialAlwaysShowsSubscriptionForm(): void
	{
		$src = file_get_contents(dirname(__DIR__, 3) . '/templates/partials/admin-settings/outlook-ical-subscription.php');
		$this->assertNotFalse($src);
		$this->assertStringContainsString('data-org-wide-available="1"', $src);
		$this->assertStringContainsString('id="outlookIcalGenerateBtn"', $src);
		$this->assertStringNotContainsString('Enable app-owned teams first. Calendar subscriptions are available only for app team scopes.', $src);
	}

	public function testOutlookPartialUsesResponsiveActionsRow(): void
	{
		$src = file_get_contents(dirname(__DIR__, 3) . '/templates/partials/admin-settings/outlook-ical-subscription.php');
		$this->assertNotFalse($src);
		$this->assertStringContainsString('id="outlookIcalFeedLanguage"', $src);
		$this->assertStringContainsString('form-row form-row--2', $src);
		$this->assertStringNotContainsString('outlookIcalManagerSearch', $src);
		$this->assertStringContainsString('card-actions--inline', $src);
		$this->assertStringContainsString('id="outlook-ical-subscription"', $src);
	}

	public function testSingleTopicPageReferencesShellLeadForAria(): void
	{
		$src = file_get_contents(dirname(__DIR__, 3) . '/templates/partials/admin-settings/outlook-ical-subscription.php');
		$this->assertNotFalse($src);
		$this->assertStringContainsString('$outlookIntroDescId', $src);
		$this->assertStringContainsString('azc-page-help', $src);
	}

	public function testAdminSettingsShellClosesSectionBeforePageStack(): void
	{
		$src = file_get_contents(dirname(__DIR__, 3) . '/templates/admin-settings.php');
		$this->assertNotFalse($src);
		$this->assertStringContainsString('</div><!-- /.section -->', $src);
		$this->assertStringContainsString('$showSettingsSaveFooter', $src);
		$this->assertMatchesRegularExpression(
			'/<\/div><!-- \/\.section -->[\s\S]*<\/div><!-- \/\.azc-page-stack -->/',
			$src,
		);
	}

	public function testOutlookPartialDocumentsIcalSubscriptionFlow(): void
	{
		$src = file_get_contents(dirname(__DIR__, 3) . '/templates/partials/admin-settings/outlook-ical-subscription.php');
		$this->assertNotFalse($src);
		$this->assertStringContainsString('New subscription', $src);
		$this->assertStringContainsString('outlookIcalEnableWebcalLocalBtn', $src);
		$this->assertStringContainsString('.ics', $src);
	}

	public function testPageStartExposesHelpLandmarkId(): void
	{
		$src = file_get_contents(dirname(__DIR__, 3) . '/templates/common/page-start.php');
		$this->assertNotFalse($src);
		$this->assertStringContainsString('id="azc-page-help"', $src);
	}
}
