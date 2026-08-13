<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Classic OC.L10N.register files throw on the Vue home dashboard when they run
 * before window.OC exists. Every locale JS must boot through __azcBootL10n.
 */
class L10nOcSafeBootstrapContractTest extends TestCase {
	public function testEveryLocaleJsDefersUntilOcExists(): void {
		$dir = __DIR__ . '/../../l10n';
		$files = glob($dir . '/*.js') ?: [];
		$this->assertNotEmpty($files, 'expected l10n/*.js locales');

		foreach ($files as $file) {
			$src = (string)file_get_contents($file);
			$base = basename($file);
			$this->assertStringContainsString(
				'__azcBootL10n',
				$src,
				"$base must defer OC.L10N.register until window.OC exists"
			);
			$this->assertStringContainsString(
				"typeof OC !== 'undefined'",
				$src,
				"$base must guard typeof OC"
			);
			// Must not leave a top-level bare OC.L10N.register( as first statement.
			$trimmed = ltrim($src);
			$this->assertStringStartsWith(
				'(function',
				$trimmed,
				"$base must start with an IIFE, not bare OC.L10N.register"
			);
		}
	}
}
