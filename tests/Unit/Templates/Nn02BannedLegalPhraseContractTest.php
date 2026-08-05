<?php

declare(strict_types=1);

/**
 * NN-02: UI must not claim premiums are immutable statutory overtime law.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\Templates;

use PHPUnit\Framework\TestCase;

class Nn02BannedLegalPhraseContractTest extends TestCase
{
	/** @return list<string> */
	private function scanPaths(): array
	{
		$root = dirname(__DIR__, 2) . '/../';
		return [
			$root . 'templates/admin-notifications.php',
			$root . 'templates/admin-overtime-settings.php',
			$root . 'templates/partials/admin-policy-hour-premiums.php',
			$root . 'templates/dashboard.php',
			$root . 'js/admin-notifications.js',
		];
	}

	public function testPremiumCopyAvoidsBannedStatutoryClaims(): void
	{
		$banned = [
			'gesetzliche Überstunden',
			'statutory overtime pay',
			'immutable AZG',
			'gesetzlicher Zuschlag',
			'ArbZG overtime table',
		];
		$haystack = '';
		foreach ($this->scanPaths() as $path) {
			$src = file_get_contents($path);
			$this->assertNotFalse($src, $path);
			$haystack .= "\n" . $src;
		}
		foreach ($banned as $phrase) {
			$this->assertStringNotContainsStringIgnoringCase(
				$phrase,
				$haystack,
				"Banned NN-02 phrase found: {$phrase}"
			);
		}
		$this->assertStringContainsString('AT Tarif/KV example', $haystack);
		$this->assertStringContainsString('non-binding', $haystack);
	}
}
