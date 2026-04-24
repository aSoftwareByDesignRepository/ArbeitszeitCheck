<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Dashboard;

use OCA\ArbeitszeitCheck\AppInfo\Application;
use OCA\ArbeitszeitCheck\Service\DashboardWidgetDataService;
use OCP\Dashboard\IAPIWidgetV2;
use OCP\Dashboard\IButtonWidget;
use OCP\Dashboard\IIconWidget;
use OCP\Dashboard\IReloadableWidget;
use OCP\Dashboard\Model\WidgetButton;
use OCP\Dashboard\Model\WidgetItem;
use OCP\Dashboard\Model\WidgetItems;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Util;

class EmployeeStatusWidget implements IAPIWidgetV2, IButtonWidget, IIconWidget, IReloadableWidget {
	/** In-request cache: Nextcloud may call getItemsV2 and getWidgetButtons in one request. */
	private ?string $cachedWidgetUserId = null;

	/** @var array<string, mixed>|null */
	private ?array $cachedWidgetData = null;

	public function __construct(
		private readonly IL10N $l10n,
		private readonly IURLGenerator $urlGenerator,
		private readonly DashboardWidgetDataService $widgetDataService,
	) {
	}

	public function getId(): string {
		return Application::APP_ID . '-employee-status';
	}

	public function getTitle(): string {
		return $this->l10n->t('My work status');
	}

	public function getOrder(): int {
		return 30;
	}

	public function getIconClass(): string {
		return 'icon-history';
	}

	public function getIconUrl(): string {
		return $this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath(Application::APP_ID, 'app.svg'));
	}

	public function getUrl(): ?string {
		return $this->dashboardQuickActionsUrl();
	}

	public function load(): void {
		Util::addScript(Application::APP_ID, 'dashboard-widgets');
		Util::addStyle(Application::APP_ID, 'dashboard-widgets');
	}

	public function getItemsV2(string $userId, ?string $since = null, int $limit = 7): WidgetItems {
		$data    = $this->getEmployeeDataSafe($userId);
		$status  = (string)$data['status'];
		$url     = $this->dashboardQuickActionsUrl();
		$icon    = $this->getIconUrl();
		$ts      = (string)time();

		$items = [];

		// ── Item 1: current status with contextual session detail ──────────────
		$statusTitle = $this->statusLabel($status);

		$duration = $this->formatDuration((int)$data['currentSessionDuration']);
		$sessionStart = (string)$data['sessionStartFormatted'];
		$breakStart   = (string)$data['breakStartFormatted'];

		$subtitle1 = match ($status) {
			'active'  => $sessionStart !== ''
				? $this->l10n->t('Since %1$s · Session: %2$s', [$sessionStart, $duration])
				: $this->l10n->t('Session: %1$s', [$duration]),
			'break'   => $breakStart !== ''
				? $this->l10n->t('Break since %1$s · Worked: %2$s', [$breakStart, $duration])
				: $this->l10n->t('On break · Worked: %1$s', [$duration]),
			'paused'  => $this->l10n->t('Paused · Worked so far: %1$s', [$duration]),
			default   => $this->l10n->t('Not clocked in today'),
		};
		$items[] = new WidgetItem($statusTitle, $subtitle1, $url, $icon, $ts . '-status');

		// ── Item 2: today's hours ───────────────────────────────────────────────
		$todayHours = number_format((float)$data['workingTodayHours'], 2);
		$items[] = new WidgetItem(
			$this->l10n->t('Today'),
			$this->l10n->t('%1$s h worked', [$todayHours]),
			$url, $icon, $ts . '-today'
		);

		// ── Item 3: this week (worked / required) ───────────────────────────────
		$weekWorked   = number_format((float)$data['weekHoursWorked'], 2);
		$weekRequired = number_format((float)$data['weekHoursRequired'], 2);
		$items[] = new WidgetItem(
			$this->l10n->t('This week'),
			$this->l10n->t('%1$s / %2$s h', [$weekWorked, $weekRequired]),
			$url, $icon, $ts . '-week'
		);

		// ── Item 4: cumulative overtime balance ─────────────────────────────────
		$balance     = (float)$data['cumulativeBalance'];
		$balanceStr  = ($balance >= 0 ? '+' : '') . number_format($balance, 2);
		$items[] = new WidgetItem(
			$this->l10n->t('Overtime balance'),
			$this->l10n->t('%1$s h', [$balanceStr]),
			$url, $icon, $ts . '-balance'
		);

		// ── Item 5: vacation summary ─────────────────────────────────────────────
		$vacationYear = (int)$data['vacationYear'];
		$vacationRemaining = number_format((float)$data['vacationRemaining'], 1);
		$vacationTotal = number_format((float)$data['vacationEntitlement'], 1);
		$items[] = new WidgetItem(
			$this->l10n->t('Vacation %1$s', [(string)$vacationYear]),
			$this->l10n->t('%1$s / %2$s days remaining', [$vacationRemaining, $vacationTotal]),
			$url, $icon, $ts . '-vacation'
		);

		// ── Item 6: vacation pool split (annual + carryover) ───────────────────
		$carryover = number_format((float)$data['vacationCarryoverUsable'], 1);
		$annualPool = max(0.0, (float)$data['vacationRemaining'] - (float)$data['vacationCarryoverUsable']);
		$annual = number_format($annualPool, 1);
		$items[] = new WidgetItem(
			$this->l10n->t('Vacation pool'),
			$this->l10n->t('Annual: %1$s d · Carryover: %2$s d', [$annual, $carryover]),
			$url, $icon, $ts . '-vacation-pool'
		);

		// ── Item 7 (conditional): break compliance warning ──────────────────────
		if ((bool)$data['breakRequired'] && (int)$data['remainingBreakMinutes'] > 0) {
			$remaining = (int)$data['remainingBreakMinutes'];
			$items[] = new WidgetItem(
				$this->l10n->t('Break required (ArbZG §4)'),
				$this->l10n->t('%1$s min still needed', [$remaining]),
				$url, $icon, $ts . '-break'
			);
		}

		return new WidgetItems($items, '');
	}

	public function getWidgetButtons(string $userId): array {
		$data = $this->getEmployeeDataSafe($userId);
		$status = (string)$data['status'];

		return [
			new WidgetButton(
				WidgetButton::TYPE_NEW,
				$this->dashboardQuickActionsUrl(),
				$this->primaryActionLabel($status)
			),
			new WidgetButton(
				WidgetButton::TYPE_MORE,
				$this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute('arbeitszeitcheck.page.timeEntries')),
				$this->l10n->t('Open time entries')
			),
		];
	}

	public function getReloadInterval(): int {
		return 30;
	}

	private function statusLabel(string $status): string {
		return match ($status) {
			'active' => $this->l10n->t('Working'),
			'break' => $this->l10n->t('On Break'),
			'paused' => $this->l10n->t('Paused'),
			default => $this->l10n->t('Clocked Out'),
		};
	}

	private function formatDuration(int $seconds): string {
		$seconds = max(0, $seconds);
		$hours = intdiv($seconds, 3600);
		$minutes = intdiv($seconds % 3600, 60);
		return sprintf('%02d:%02d', $hours, $minutes);
	}

	private function dashboardQuickActionsUrl(): string {
		return $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->linkToRoute('arbeitszeitcheck.page.dashboard') . '#dashboard-status-heading'
		);
	}

	private function nextActionLabel(string $status): string {
		return match ($status) {
			'active' => $this->l10n->t('Pause'),
			'break' => $this->l10n->t('Continue'),
			'paused' => $this->l10n->t('Continue'),
			default => $this->l10n->t('Clock In'),
		};
	}

	private function primaryActionLabel(string $status): string {
		return match ($status) {
			'active' => $this->l10n->t('Start Break'),
			'break' => $this->l10n->t('End Break'),
			'paused' => $this->l10n->t('Clock In'),
			default => $this->l10n->t('Clock In'),
		};
	}

	/**
	 * @return array<string, mixed>
	 */
	private function getEmployeeDataSafe(string $userId): array {
		if ($this->cachedWidgetUserId === $userId && $this->cachedWidgetData !== null) {
			return $this->cachedWidgetData;
		}
		try {
			$this->cachedWidgetData = $this->widgetDataService->getEmployeeWidgetData($userId);
		} catch (\Throwable $e) {
			\OCP\Log\logger('arbeitszeitcheck')->error('Employee dashboard widget: failed to load data', [
				'exception' => $e,
			]);
			$this->cachedWidgetData = $this->fallbackEmployeeWidgetData();
		}
		$this->cachedWidgetUserId = $userId;
		return $this->cachedWidgetData;
	}

	/**
	 * Safe defaults so the Nextcloud dashboard never receives an empty/ broken widget.
	 *
	 * @return array<string, mixed>
	 */
	private function fallbackEmployeeWidgetData(): array {
		$y = (int)date('Y');
		return [
			'userId' => '',
			'status' => 'clocked_out',
			'workingTodayHours' => 0.0,
			'currentSessionDuration' => 0,
			'sessionStartFormatted' => '',
			'breakStartFormatted' => '',
			'weekHoursWorked' => 0.0,
			'weekHoursRequired' => 0.0,
			'weeklyContractHours' => 40.0,
			'cumulativeBalance' => 0.0,
			'breakRequired' => false,
			'remainingBreakMinutes' => 0,
			'breakWarningLevel' => 'none',
			'vacationYear' => $y,
			'vacationRemaining' => 0.0,
			'vacationEntitlement' => 0.0,
			'vacationUsed' => 0.0,
			'vacationCarryover' => 0.0,
			'vacationCarryoverUsable' => 0.0,
		];
	}
}
