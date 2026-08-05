<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Dashboard;

use OCA\ArbeitszeitCheck\Dashboard\EmployeeStatusWidget;
use OCA\ArbeitszeitCheck\Dashboard\WidgetIconHelper;
use OCA\ArbeitszeitCheck\Service\DashboardDeskletRenderService;
use OCA\ArbeitszeitCheck\Service\DashboardWidgetDataService;
use OCA\ArbeitszeitCheck\Service\TimeZoneService;
use OCA\ArbeitszeitCheck\Support\TimeClientBootstrap;
use OCP\AppFramework\Services\IInitialState;
use OCP\Dashboard\Model\WidgetItems;
use OCP\IConfig;
use OCP\IDateTimeZone;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class EmployeeStatusWidgetTest extends TestCase {
	/** @var IL10N&\PHPUnit\Framework\MockObject\MockObject */
	private $l10n;
	/** @var IURLGenerator&\PHPUnit\Framework\MockObject\MockObject */
	private $urlGenerator;
	/** @var DashboardWidgetDataService&\PHPUnit\Framework\MockObject\MockObject */
	private $dataService;
	private TimeClientBootstrap $timeClientBootstrap;

	protected function setUp(): void {
		parent::setUp();

		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')->willReturnCallback(
			static fn (string $s, array $p = []): string => $p ? (string)vsprintf($s, $p) : $s
		);

		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->urlGenerator->method('imagePath')->willReturn('/apps/arbeitszeitcheck/img/app-dark.svg');
		$this->urlGenerator->method('linkToRoute')->willReturn('/apps/arbeitszeitcheck/dashboard');
		$this->urlGenerator->method('getAbsoluteURL')->willReturnCallback(
			static fn (string $v): string => 'https://example.local' . $v
		);

		$this->dataService = $this->createMock(DashboardWidgetDataService::class);

		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(fn ($app, $key, $default) => match ($key) {
			'app_timezone' => 'Europe/Berlin',
			default => $default,
		});
		$dateTimeZone = $this->createMock(IDateTimeZone::class);
		$dateTimeZone->method('getTimeZone')->willReturn(new \DateTimeZone('Europe/Berlin'));
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn(null);
		$timeZoneService = new TimeZoneService($config, $dateTimeZone, $userSession, new NullLogger());
		$this->timeClientBootstrap = new TimeClientBootstrap(
			$timeZoneService,
			$dateTimeZone,
			$this->createMock(IInitialState::class)
		);
	}

	private function createWidget(?IL10N $l10n = null): EmployeeStatusWidget {
		$iconHelper = new WidgetIconHelper($this->urlGenerator);
		$userSession = $this->createMock(IUserSession::class);
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$userSession->method('getUser')->willReturn($user);
		$initialState = $this->createMock(IInitialState::class);
		$deskletRender = $this->createMock(DashboardDeskletRenderService::class);
		$deskletRender->method('renderForUser')->willReturn([
			'config' => ['employeeDataUrl' => '/employee'],
			'workspaceHtml' => '<div data-arbeitszeitcheck-desklet="1"></div>',
		]);

		return new EmployeeStatusWidget(
			$l10n ?? $this->l10n,
			$this->urlGenerator,
			$this->dataService,
			$this->timeClientBootstrap,
			$iconHelper,
			$userSession,
			$initialState,
			$deskletRender,
		);
	}

	public function testWidgetReturnsSingleStatusItem(): void {
		$this->dataService->method('getEmployeeWidgetData')->willReturn([
			'status' => 'active',
			'workingTodayHours' => 3.25,
			'currentSessionDuration' => 1800,
		]);

		$widget = $this->createWidget();
		$items  = $widget->getItemsV2('u1');

		$this->assertInstanceOf(WidgetItems::class, $items);
		$this->assertGreaterThanOrEqual(4, count($items->getItems()));
		$this->assertSame('Working', $items->getItems()[0]->getTitle());
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

		$widget = $this->createWidget($l10n);
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

		$widget   = $this->createWidget();
		$items    = $widget->getItemsV2('u1');
		$subtitle = $items->getItems()[0]->getSubtitle();

		$this->assertStringContainsString('Session: 01:00', $subtitle);
	}

	public function testVacationSubtitleUsesHoursWhenUnitIsHours(): void {
		$this->dataService->method('getEmployeeWidgetData')->willReturn([
			'status' => 'clocked_out',
			'workingTodayHours' => 0.0,
			'currentSessionDuration' => 0,
			'vacationYear' => 2026,
			'vacationRemaining' => 196.0,
			'vacationEntitlement' => 200.0,
			'vacationUnit' => 'hours',
			'vacationCarryoverUsable' => 16.0,
			'cumulativeBalance' => 0.0,
			'impliedDailyHours' => 8.0,
			'weekHoursWorked' => 0.0,
			'weekHoursRequired' => 40.0,
			'breakRequired' => false,
			'remainingBreakMinutes' => 0,
		]);

		$widget = $this->createWidget();
		$items = $widget->getItemsV2('u1');
		$subtitles = array_map(static fn ($item) => $item->getSubtitle(), $items->getItems());
		$joined = implode("\n", $subtitles);

		$this->assertStringContainsString('hours remaining', $joined);
		$this->assertStringNotContainsString('days remaining', $joined);
		$this->assertStringContainsString(' h · Carryover:', $joined);
		$this->assertStringNotContainsString(' d · Carryover:', $joined);
	}

	public function testVacationSubtitleUsesDaysByDefault(): void {
		$this->dataService->method('getEmployeeWidgetData')->willReturn([
			'status' => 'clocked_out',
			'workingTodayHours' => 0.0,
			'currentSessionDuration' => 0,
			'vacationYear' => 2026,
			'vacationRemaining' => 12.0,
			'vacationEntitlement' => 28.0,
			'vacationUnit' => 'days',
			'vacationCarryoverUsable' => 2.0,
			'cumulativeBalance' => 0.0,
			'impliedDailyHours' => 8.0,
			'weekHoursWorked' => 0.0,
			'weekHoursRequired' => 40.0,
			'breakRequired' => false,
			'remainingBreakMinutes' => 0,
		]);

		$widget = $this->createWidget();
		$items = $widget->getItemsV2('u1');
		$subtitles = array_map(static fn ($item) => $item->getSubtitle(), $items->getItems());
		$joined = implode("\n", $subtitles);

		$this->assertStringContainsString('days remaining', $joined);
		$this->assertStringContainsString(' d · Carryover:', $joined);
	}

	public function testReloadIntervalIsPositive(): void {
		$widget = $this->createWidget();
		$this->assertGreaterThan(0, $widget->getReloadInterval());
	}

	public function testWidgetButtonsExist(): void {
		$this->dataService->method('getEmployeeWidgetData')->willReturn([
			'status' => 'active',
			'workingTodayHours' => 5.5,
			'currentSessionDuration' => 3600,
		]);

		$widget   = $this->createWidget();
		$buttons  = $widget->getWidgetButtons('u1');

		$this->assertNotEmpty($buttons);
	}

	public function testWidgetSurvivesDataServiceException(): void {
		$this->dataService->method('getEmployeeWidgetData')->willThrowException(new \RuntimeException('simulated'));
		$widget = $this->createWidget();
		$items  = $widget->getItemsV2('u1');
		$this->assertInstanceOf(WidgetItems::class, $items);
		$this->assertGreaterThanOrEqual(1, count($items->getItems()));
		$this->assertSame('Clocked Out', $items->getItems()[0]->getTitle());
		$btns   = $widget->getWidgetButtons('u1');
		$this->assertNotEmpty($btns);
	}
}
