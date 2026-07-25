<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

/**
 * Ensures routed UI tables follow the unified table-container + table--hover convention.
 */
class TableConventionTest extends TestCase
{
	private const TEMPLATE_SKIP = [
		'admin-notifications.php', // matrix grids use azc-table--matrix
		'admin-kiosk.php', // compact kiosk credential tables (azc-table, no hover)
		'admin-license.php', // license seat matrix (azc-license-seats-table)
	];

	/**
	 * @return list<string>
	 */
	public static function routedTemplateProvider(): array
	{
		$dir = __DIR__ . '/../../templates';
		$files = [];
		$it = new RegexIterator(
			new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir)),
			'/\.php$/'
		);
		foreach ($it as $file) {
			$path = $file->getPathname();
			if (str_contains($path, '/common/') || str_contains($path, '/email/')) {
				continue;
			}
			$base = basename($path);
			if (in_array($base, self::TEMPLATE_SKIP, true)) {
				continue;
			}
			$content = (string)file_get_contents($path);
			if (!str_contains($content, '<table')) {
				continue;
			}
			$files[] = $path;
		}
		sort($files);
		return array_map(static fn (string $p): array => [$p], $files);
	}

	/**
	 * @dataProvider routedTemplateProvider
	 */
	public function testTemplatesUseTableContainerAndHover(string $path): void
	{
		$content = (string)file_get_contents($path);

		if (!preg_match_all('/<table\b[^>]*class="([^"]*)"/', $content, $matches)) {
			return;
		}

		foreach ($matches[1] as $classAttr) {
			if (str_contains($classAttr, 'azc-table--matrix')
				|| str_contains($classAttr, 'correction-snapshot')
				|| str_contains($classAttr, 'trace-table')
				|| str_contains($classAttr, 'azc-license-seats-table')) {
				continue;
			}
			$this->assertStringContainsString(
				'table',
				$classAttr,
				$path . ' table must include .table class: ' . $classAttr
			);
			$this->assertStringContainsString(
				'table--hover',
				$classAttr,
				$path . ' table must include .table--hover: ' . $classAttr
			);
		}

		if (str_contains($content, 'table-responsive') && !str_contains($content, 'table-container')) {
			$this->fail($path . ' uses table-responsive without table-container — migrate to table-container');
		}
	}

	public function testPagePatternsDefineShellTableRules(): void
	{
		$content = (string)file_get_contents(__DIR__ . '/../../css/common/page-patterns.css');
		$this->assertStringContainsString('#app-content.azc-app .table-container', $content);
		$this->assertStringContainsString('.azc-table-actions-col', $content);
		$this->assertStringContainsString('min-width: max-content', $content);
		$this->assertStringContainsString('overflow-x: auto', $content);
		$this->assertStringNotContainsString('width: 1%', $content);
		$this->assertStringContainsString('.azc-table--responsive', $content);
	}

	public function testDenseListTablesUseResponsiveCardReflow(): void
	{
		$paths = [
			'time-entries.php',
			'absences.php',
			'manager-time-entries.php',
			'manager-absences.php',
			'admin-users.php',
			'admin-holidays.php',
			'working-time-models.php',
			'compliance-dashboard.php',
			'compliance-reports.php',
			'dashboard.php',
		];
		foreach ($paths as $file) {
			$content = (string)file_get_contents(__DIR__ . '/../../templates/' . $file);
			$this->assertStringContainsString(
				'azc-table--responsive',
				$content,
				$file . ' should use responsive table card reflow'
			);
		}
		$timeEntries = (string)file_get_contents(__DIR__ . '/../../templates/time-entries.php');
		$absences = (string)file_get_contents(__DIR__ . '/../../templates/absences.php');
		$this->assertStringContainsString('azc-table-actions', $timeEntries);
		$this->assertStringContainsString('azc-table-actions', $absences);
		// Type + Past record must share one inline flex group (not stacked siblings).
		$this->assertStringContainsString('class="absence-type-badges"', $absences);
		$this->assertStringNotContainsString('class="absence-type-badges-', $absences);
		$typeCellPos = strpos($absences, 'class="absence-type-badges"');
		$pastPos = strpos($absences, 'absence-past-record-badge');
		$this->assertNotFalse($typeCellPos);
		$this->assertNotFalse($pastPos);
		$this->assertLessThan($pastPos, $typeCellPos);
		$css = (string)file_get_contents(__DIR__ . '/../../css/absences.css');
		$this->assertStringContainsString('.absence-type-badges {', $css);
		$this->assertMatchesRegularExpression(
			'/\.badge--past-record,\s*\n\.absence-past-record-badge \{\s*\n\tmargin-top: 0;/',
			$css
		);
		$managerJs = (string)file_get_contents(__DIR__ . '/../../js/manager-absences.js');
		$this->assertStringContainsString('class="absence-type-badges"', $managerJs);
		$this->assertStringContainsString('badge--past-record', $managerJs);
	}

	public function testUtilsExposeResponsiveTableHelpers(): void
	{
		$content = (string)file_get_contents(__DIR__ . '/../../js/common/utils.js');
		$this->assertStringContainsString('dataLabelAttr', $content);
		$this->assertStringContainsString('responsiveTd', $content);
	}

	public function testAdminDashboardJsUsesResponsiveDrilldownTable(): void
	{
		$content = (string)file_get_contents(__DIR__ . '/../../js/admin-dashboard.js');
		$this->assertStringContainsString('azc-table--responsive', $content);
		$this->assertStringContainsString('Utils.responsiveTd', $content);
	}

	public function testReportsJsWrapsDynamicTables(): void
	{
		$content = (string)file_get_contents(__DIR__ . '/../../js/reports.js');
		$this->assertStringContainsString('table-container', $content);
		$this->assertStringContainsString('azc-table--responsive', $content);
	}
}
