<?php

declare(strict_types=1);

/**
 * Prevents guest-layout / HTTP 500 from bare `$l->t('…%…')` without vsprintf args.
 *
 * Nextcloud L10N runs vsprintf on every translated string. A literal percent must be
 * written as `%%`; placeholders (`%s`, `%d`, `%1$s`, …) need a second argument or
 * {@see \OCA\ArbeitszeitCheck\Util\TemplateL10n::translate()}.
 *
 * Historic: admin/notifications crashed on `50% = 0.50` → body-login + guest.css
 * centered the sidebar and checkbox labels.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\Templates;

use PHPUnit\Framework\TestCase;

class L10nPrintfSafetyContractTest extends TestCase
{
	/** @return list<string> */
	private function phpRoots(): array
	{
		$base = dirname(__DIR__, 3);
		return [
			$base . '/templates',
			$base . '/lib',
		];
	}

	/**
	 * @return \Generator<string, array{0: string}>
	 */
	public static function phpFileProvider(): \Generator
	{
		$base = dirname(__DIR__, 3);
		foreach ([$base . '/templates', $base . '/lib'] as $root) {
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
			);
			foreach ($iterator as $file) {
				if (!$file->isFile() || $file->getExtension() !== 'php') {
					continue;
				}
				yield $file->getPathname() => [$file->getPathname()];
			}
		}
	}

	/**
	 * @dataProvider phpFileProvider
	 */
	public function testNoUnsafePrintfInL10nCalls(string $path): void
	{
		$src = file_get_contents($path);
		$this->assertNotFalse($src);

		$pattern = '/(?:\$l|\$this->l10n|\$this->l|\$l10n)\s*->\s*t\s*\(\s*([\'"])((?:\\\\.|(?!\1).)*)\1(\s*,)?/s';
		if (!preg_match_all($pattern, $src, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
			$this->assertTrue(true);
			return;
		}

		$issues = [];
		foreach ($matches as $match) {
			$string = $match[2][0];
			$hasArgs = isset($match[3]) && $match[3][0] !== '';
			$offset = (int)$match[0][1];
			$line = substr_count(substr($src, 0, $offset), "\n") + 1;

			$cleaned = str_replace('%%', '', $string);
			preg_match_all('/%(?:\d+\$)?[sdfF]|%n/', $cleaned, $ph);
			$placeholderCount = count($ph[0]);
			$withoutPlaceholders = preg_replace('/%(?:\d+\$)?[sdfF]|%n/', '', $cleaned) ?? $cleaned;
			$barePercentCount = substr_count($withoutPlaceholders, '%');

			if ($hasArgs) {
				continue;
			}
			if ($barePercentCount > 0 || $placeholderCount > 0) {
				$issues[] = sprintf(
					'line %d: bare%%=%d placeholders=%d :: %s',
					$line,
					$barePercentCount,
					$placeholderCount,
					mb_substr($string, 0, 100)
				);
			}
		}

		$this->assertSame(
			[],
			$issues,
			basename($path) . " has unsafe \$l->t() printf usage (use %% for literals or TemplateL10n / pass args):\n"
			. implode("\n", $issues)
		);
	}

	public function testAdminNotificationsPremiumPercentStringsAreEscaped(): void
	{
		$src = file_get_contents(dirname(__DIR__, 3) . '/templates/partials/admin-policy-hour-premiums.php');
		$this->assertNotFalse($src);
		// Literal percent signs in $l->t() must use %% (vsprintf) — historic guest-layout crash.
		$this->assertStringContainsString('__PCT__%%', $src);
		$this->assertStringContainsString('@ 100%%', $src);
		$this->assertStringNotContainsString("50% = 0.50", $src);
		$en = json_decode(
			(string)file_get_contents(dirname(__DIR__, 3) . '/l10n/en.json'),
			true,
			512,
			JSON_THROW_ON_ERROR
		);
		$enT = $en['translations'] ?? [];
		$this->assertArrayHasKey(
			'Load a starter, then edit the percentages. Rates are stored as fractions (50%% = 0.50).',
			$enT,
			'Catalog keeps escaped 50%% example for any UI that still references it',
		);
		$vacationPage = file_get_contents(dirname(__DIR__, 3) . '/templates/admin-vacation-rules.php');
		$this->assertNotFalse($vacationPage);
		$this->assertStringContainsString('TemplateL10n::translate($l, \'Convert all open vacation balances and absences to hours using %s', $vacationPage);
		$this->assertStringContainsString('TemplateL10n::translate($l, \'Convert all open vacation balances back to days using %s', $vacationPage);
	}
}
