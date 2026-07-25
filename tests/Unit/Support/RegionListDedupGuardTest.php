<?php

declare(strict_types=1);

/**
 * B-4 guard: region lists must live only in RegionRegistry — no hard-coded
 * Bundesland / Länder arrays in controllers or templates.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;

class RegionListDedupGuardTest extends TestCase
{
	/**
	 * Historic DE codes that used to be copy-pasted into 7 files. Seeing a
	 * contiguous multi-code array literal outside RegionRegistry is a drift bug.
	 */
	public function testControllersAndTemplatesDoNotHardcodeRegionArrays(): void
	{
		$appRoot = dirname(__DIR__, 3);
		$scanRoots = [
			$appRoot . '/lib/Controller',
			$appRoot . '/lib/Service',
			$appRoot . '/templates',
			$appRoot . '/js',
		];
		$allowedBasenames = [
			'RegionRegistry.php',
			'RegionRegistryTest.php',
			'RegionListDedupGuardTest.php',
			'GermanStatutoryHolidayCatalog.php',
			'AustrianStatutoryHolidayCatalog.php',
			'SwissStatutoryHolidayCatalog.php',
		];

		// A classic duplicated block from the pre-DACH AdminController.
		$forbiddenSnippet = "'BW', 'BY', 'BE', 'BB', 'HB', 'HH', 'HE', 'MV', 'NI', 'NW', 'RP', 'SL', 'SN', 'ST', 'SH', 'TH'";
		$forbiddenAtSnippet = "'AT-B', 'AT-K', 'AT-NOE', 'AT-OOE', 'AT-S', 'AT-ST', 'AT-T', 'AT-V', 'AT-W'";

		$violations = [];
		foreach ($scanRoots as $root) {
			if (!is_dir($root)) {
				continue;
			}
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
			);
			/** @var \SplFileInfo $file */
			foreach ($iterator as $file) {
				if (!$file->isFile()) {
					continue;
				}
				$ext = strtolower($file->getExtension());
				if (!in_array($ext, ['php', 'js'], true)) {
					continue;
				}
				if (in_array($file->getBasename(), $allowedBasenames, true)) {
					continue;
				}
				$contents = file_get_contents($file->getPathname());
				if ($contents === false) {
					continue;
				}
				if (str_contains($contents, $forbiddenSnippet) || str_contains($contents, $forbiddenAtSnippet)) {
					$violations[] = substr($file->getPathname(), strlen($appRoot) + 1);
				}
			}
		}

		$this->assertSame(
			[],
			$violations,
			'Hard-coded region arrays found outside RegionRegistry (B-4): ' . implode(', ', $violations)
		);
	}
}
