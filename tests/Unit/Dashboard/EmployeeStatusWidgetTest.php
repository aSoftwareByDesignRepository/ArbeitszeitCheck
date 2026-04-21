<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Dashboard;

use OCA\ArbeitszeitCheck\Dashboard\EmployeeStatusWidget;
use OCA\ArbeitszeitCheck\Service\DashboardWidgetDataService;
use OCP\Dashboard\Model\WidgetItems;
use OCP\IL10N;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;

class EmployeeStatusWidgetTest extends TestCase {
	/** @var IL10N&\PHPUnit\Framework\MockObject\MockObject */
	private $l10n;
	/** @var IURLGenerator&\PHPUnit\Framework\MockObject\MockObject */
	private $urlGenerator;
	/** @var DashboardWidgetDataService&\PHPUnit\Framework\MockObject\MockObject */
	private $dataService;

	protected function setUp(): void {
		parent::setUp();

		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')->willReturnCallback(
			static fn (string $s, array $p = []): string => $p ? (string)vsprintf($s, $p) : $s
		);

		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->urlGenerator->method('imagePath')->willReturn('/apps/arbeitszeitcheck/img/app.svg');
		$this->urlGenerator->method('linkToRoute')->willReturn('/apps/arbeitszeitcheck/dashboard');
		$this->urlGenerator->method('getAbsoluteURL')->willReturnCallback(
			static fn (string $v): string => 'https://example.local' . $v
		);

		$this->dataService = $this->createMock(DashboardWidgetDataService::class);
	}

	public function testWidgetReturnsSingleStatusItem(): void {
		$this->dataService->method('getEmployeeWidgetData')->willReturn([
			'status' => 'active',
			'workingTodayHours' => 3.25,
			'currentSessionDuration' => 1800,
		]);

		$widget = new EmployeeStatusWidget($this->l10n, $this->urlGenerator, $this->dataService);
		$items  = $widget->getItemsV2('u1');

		$this->assertInstanceOf(WidgetItems::class, $items);
		$this->assertCount(1, $items->getItems());
		$this->assertStringContainsString('Status:', $items->getItems()[0]->getTitle());
	}

	/** @dataProvider statusLabelProvider */
	public function testStatusLabelUsesCorrectL10nKeys(string $status, string $expectedKey): void {
		$capturedKeys = [];
		$l10n         = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(
			static function (string $key, array $p = []) use (&$capturedKeys): string {
				$capturedKeys[] = $key;
				return $p ? (string)vsprintf($key, $p) : $key;
			}
		);

		$this->dataService->method('getEmployeeWidgetData')->willReturn([
			'status' => $status,
			'workingTodayHours' => 0.0,
			'currentSessionDuration' => 0,
		]);

		$widget = new EmployeeStatusWidget($l10n, $this->urlGenerator, $this->dataService);
		$widget->getItemsV2('u1');

		$this->assertContains(
			$expectedKey,
			$capturedKeys,
			"Expected l10n key '{$expectedKey}' to be used for status '{$status}'"
		);
	}

	public static function statusLabelProvider(): array {
		return [
			'working'    => ['active',      'Working'],
			'on break'   => ['break',       'On Break'],    // capital B — must match en.json key
			'paused'     => ['paused',      'Paused'],
			'clocked out'=> ['clocked_out', 'Clocked Out'], // capital O — must match en.json key
		];
	}

	public function testWidgetItemSubtitleContainsHoursAndSession(): void {
		$this->dataService->method('getEmployeeWidgetData')->willReturn([
			'status' => 'active',
			'workingTodayHours' => 5.5,
			'currentSessionDuration' => 3600,
		]);

		$widget   = new EmployeeStatusWidget($this->l10n, $this->urlGenerator, $this->dataService);
		$items    = $widget->getItemsV2('u1');
		$subtitle = $items->getItems()[0]->getSubtitle();

		$this->assertStringContainsString('5.50', $subtitle);
		$this->assertStringContainsString('Next:', $subtitle);
	}

	public function testReloadIntervalIsPositive(): void {
		$widget = new EmployeeStatusWidget($this->l10n, $this->urlGenerator, $this->dataService);
		$this->assertGreaterThan(0, $widget->getReloadInterval());
	}

	public function testWidgetButtonsExist(): void {
		$this->dataService->method('getEmployeeWidgetData')->willReturn([
			'status' => 'active',
			'workingTodayHours' => 5.5,
			'currentSessionDuration' => 3600,
		]);

		$widget   = new EmployeeStatusWidget($this->l10n, $this->urlGenerator, $this->dataService);
		$buttons  = $widget->getWidgetButtons('u1');

		$this->assertNotEmpty($buttons);
	}
}
