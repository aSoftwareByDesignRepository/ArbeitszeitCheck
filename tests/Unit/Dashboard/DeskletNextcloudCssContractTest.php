<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Dashboard;

use PHPUnit\Framework\TestCase;

/**
 * Guards the Nextcloud home-dashboard chrome for status widgets:
 * no decorative EmptyContent checkmark, readable truncation captions.
 */
class DeskletNextcloudCssContractTest extends TestCase {
	private string $css;

	protected function setUp(): void {
		parent::setUp();
		$path = __DIR__ . '/../../../css/desklet-nextcloud.css';
		$this->assertFileExists($path);
		$this->css = (string)file_get_contents($path);
	}

	public function testHalfEmptyCheckmarkIconIsSuppressed(): void {
		$this->assertStringContainsString(
			'.empty-content.half-screen .empty-content__icon',
			$this->css
		);
		$this->assertMatchesRegularExpression(
			'/\.empty-content\.half-screen\s+\.empty-content__icon\s*\{[^}]*display:\s*none\s*!important/s',
			$this->css
		);
	}

	public function testHalfEmptyDescriptionIsLeftAlignedReadableCaption(): void {
		$this->assertMatchesRegularExpression(
			'/\.empty-content\s+\.empty-content__description\s*\{[^}]*text-align:\s*left/s',
			$this->css
		);
		$this->assertMatchesRegularExpression(
			'/\.empty-content\s+\.empty-content__description\s*\{[^}]*font-size:\s*13px/s',
			$this->css
		);
	}

	public function testListRowsKeepLargeTouchTargets(): void {
		$this->assertMatchesRegularExpression(
			'/a\.item-list__entry\s*\{[^}]*min-height:\s*44px/s',
			$this->css
		);
		$this->assertMatchesRegularExpression(
			'/a\.more\s*\{[^}]*min-height:\s*44px/s',
			$this->css
		);
	}
}
