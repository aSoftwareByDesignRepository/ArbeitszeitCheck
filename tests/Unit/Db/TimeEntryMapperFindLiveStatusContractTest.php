<?php

declare(strict_types=1);

/**
 * Contract: admin/manager desklets batch live punch status in one query.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\Db;

use OCA\ArbeitszeitCheck\Db\TimeEntry;
use OCA\ArbeitszeitCheck\Db\TimeEntryMapper;
use OCA\ArbeitszeitCheck\Support\QueryInChunker;
use PHPUnit\Framework\TestCase;

class TimeEntryMapperFindLiveStatusContractTest extends TestCase {
	public function testFindLiveStatusByUserIdsExistsAndDocumentsPriority(): void {
		$src = (string)file_get_contents(dirname(__DIR__, 3) . '/lib/Db/TimeEntryMapper.php');
		$this->assertStringContainsString('function findLiveStatusByUserIds', $src);
		$this->assertStringContainsString('QueryInChunker::in', $src);
		$this->assertStringContainsString(TimeEntry::STATUS_ACTIVE, $src);
		$this->assertStringContainsString(TimeEntry::STATUS_BREAK, $src);
		$this->assertStringContainsString(TimeEntry::STATUS_PAUSED, $src);
		// Priority: active beats break beats paused
		$this->assertMatchesRegularExpression(
			"/STATUS_ACTIVE\s*=>\s*3/",
			$src
		);
		$this->assertMatchesRegularExpression(
			"/STATUS_BREAK\s*=>\s*2/",
			$src
		);
		$this->assertMatchesRegularExpression(
			"/STATUS_PAUSED\s*=>\s*1/",
			$src
		);
	}

	public function testDashboardWidgetDataServiceUsesBatchLiveStatus(): void {
		$src = (string)file_get_contents(dirname(__DIR__, 3) . '/lib/Service/DashboardWidgetDataService.php');
		$this->assertStringContainsString('TimeEntryMapper $timeEntryMapper', $src);
		$this->assertStringContainsString('findLiveStatusByUserIds', $src);
		$this->assertStringContainsString('getAdminWidgetData', $src);
		$this->assertGreaterThanOrEqual(
			2,
			substr_count($src, 'findLiveStatusByUserIds'),
			'Admin and manager paths must both batch live status'
		);
	}

	public function testEmptyUserIdsIsSafeForQueryInChunker(): void {
		$this->assertSame([], QueryInChunker::normalizeValues([]));
		$this->assertSame([], QueryInChunker::normalizeValues(['', '  ']));
	}
}
