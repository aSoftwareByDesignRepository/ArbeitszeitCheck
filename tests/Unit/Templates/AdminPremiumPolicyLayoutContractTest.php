<?php

declare(strict_types=1);

/**
 * Phase D admin premium controls — night window / stacking / holiday policy.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\Templates;

use PHPUnit\Framework\TestCase;

class AdminPremiumPolicyLayoutContractTest extends TestCase
{
	private function premiumPartial(): string
	{
		$src = file_get_contents(dirname(__DIR__, 3) . '/templates/partials/admin-policy-hour-premiums.php');
		$this->assertNotFalse($src);
		return $src;
	}

	public function testBachusSimpleModeChipsAreFirstPaint(): void
	{
		$src = $this->premiumPartial();
		$this->assertStringContainsString('data-premium-mode="simple"', $src);
		$this->assertStringContainsString('data-premium-mode="template"', $src);
		$this->assertStringContainsString('data-premium-mode="custom"', $src);
		$this->assertStringContainsString('role="radiogroup"', $src);
		$this->assertStringContainsString('id="premium-template-picker"', $src);
		$this->assertStringContainsString('hidden', $src);
		// Tarif templates must not be the primary first-paint CTA (Bachus A2).
		$this->assertMatchesRegularExpression(
			'/id="premium-mode-chips"[\\s\\S]*id="premium-template-picker"/',
			$src
		);
		if (preg_match('/id="premium-mode-chips"(.*?)id="premium-template-picker"/s', $src, $m) !== 1) {
			$this->fail('Could not isolate mode chips block');
		}
		$this->assertStringNotContainsString(
			'data-premium-preset',
			$m[1],
			'AT/DE templates must live under From template picker, not mode chips'
		);
		$this->assertStringContainsString('data-premium-preset="at"', $src);
	}

	public function testPremiumPanelExposesNightWindowAndOverlapControls(): void
	{
		$src = $this->premiumPartial();
		foreach ([
			'id="premium-night-start"',
			'id="premium-night-end"',
			'id="premium-stacking"',
			'id="premium-holiday-policy"',
			'premium-night-window-heading',
			'premium-rules-heading',
			'id="premium-more-options"',
		] as $needle) {
			$this->assertStringContainsString($needle, $src);
		}
		// Advanced controls live under Bachus “More options” (first-paint simplicity).
		$this->assertMatchesRegularExpression(
			'/id="premium-more-options"[\\s\\S]*id="premium-night-start"/',
			$src
		);
		$this->assertStringContainsString('type="time"', $src);
		$this->assertStringContainsString('max_single_rate', $src);
		$this->assertStringContainsString('treat_as_sunday', $src);
		$this->assertStringNotContainsString('gesetzliche Überstunden', $src);
	}

	public function testCollectPremiumPolicyReadsEditableFields(): void
	{
		$js = file_get_contents(dirname(__DIR__, 3) . '/js/admin-notifications.js');
		$this->assertNotFalse($js);
		$this->assertStringContainsString('#premium-night-start', $js);
		$this->assertStringContainsString('#premium-holiday-policy', $js);
		$this->assertStringContainsString('holiday_policy: holidayPolicy', $js);
		$this->assertStringContainsString('stacking: stacking', $js);
		$this->assertStringContainsString("applyPremiumPreset(form, 'simple')", $js);
		$this->assertStringContainsString('setPremiumModeChip', $js);
		$this->assertStringContainsString('data-premium-mode', $js);
	}

	public function testPremiumCategoriesTableUsesInlinePercentAndToggleLabels(): void
	{
		$tpl = $this->premiumPartial();
		$css = file_get_contents(dirname(__DIR__, 3) . '/css/admin-notifications.css');
		$this->assertNotFalse($css);
		$this->assertStringContainsString('premium-cat-rate-wrap', $tpl);
		$this->assertStringContainsString('premium-cat-rate-suffix', $tpl);
		$this->assertStringContainsString('class="premium-cat-name" for="premium-cat-ot-on"', $tpl);
		$this->assertStringContainsString('premium-cat-toggle', $tpl);
		$this->assertStringContainsString('.premium-cat-rate-wrap', $css);
		$this->assertStringContainsString('inline-flex', $css);
		$this->assertStringContainsString('.premium-cat-name', $css);
		$this->assertStringContainsString('text-align: start', $css);
	}

	public function testOvertimeSettingsPageHostsPremiumPartial(): void
	{
		$page = file_get_contents(dirname(__DIR__, 3) . '/templates/admin-overtime-settings.php');
		$this->assertNotFalse($page);
		$this->assertStringContainsString('admin-policy-hour-premiums.php', $page);
		$this->assertStringContainsString('admin-policy-overtime-bank.php', $page);
		$this->assertStringNotContainsString('section-absences-heading', $page);
		$this->assertStringNotContainsString('azc-jump-nav.php', $page);
		$partial = $this->premiumPartial();
		$this->assertStringContainsString('id="premium-surcharges-heading"', $partial);
	}
}
