<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Templates;

use PHPUnit\Framework\TestCase;

/**
 * Audit log Search activity filter: layout isolation + stable JS hooks.
 */
class AuditLogFilterLayoutTest extends TestCase
{
	public function testFilterTemplateKeepsStableIdsAndIsolatedClasses(): void
	{
		$template = (string)file_get_contents(__DIR__ . '/../../../templates/audit-log.php');

		foreach ([
			'id="audit-log-filter-form"',
			'id="start-date"',
			'id="end-date"',
			'id="user-filter"',
			'id="action-category-filter"',
			'id="entity-type-filter"',
			'id="apply-filters"',
			'id="reset-filters"',
			'id="export-logs"',
			'id="audit-log-filter-error"',
			'id="audit-log-filter-footnote"',
		] as $needle) {
			$this->assertStringContainsString($needle, $template);
		}

		$this->assertStringContainsString('audit-log-page__filter-grid', $template);
		$this->assertStringContainsString('audit-log-filter__field--from', $template);
		$this->assertStringContainsString('audit-log-filter__actions', $template);

		/* Must not reuse global .azc-filter-field (page-patterns subgrid breaks named areas). */
		$this->assertStringNotContainsString('azc-filter-field', $template);
		$this->assertStringNotContainsString('azc-audit-filter-grid', $template);
	}

	public function testFilterCssUsesNamedAreasWithoutSubgridSpan(): void
	{
		$css = (string)file_get_contents(__DIR__ . '/../../../css/audit-log.css');

		$this->assertStringContainsString("'from to actions'", $css);
		$this->assertStringContainsString("'user action entity'", $css);
		$this->assertStringContainsString('audit-log-filter__field', $css);
		$this->assertDoesNotMatchRegularExpression('/\.audit-log-filter__field\s*\{[^}]*grid-row:\s*span\s*2/s', $css);
		$this->assertStringNotContainsString('azc-audit-filter-grid', $css);
	}
}
