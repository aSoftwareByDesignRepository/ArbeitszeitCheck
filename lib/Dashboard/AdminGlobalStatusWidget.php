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

/**
 * Server-rendered company status list for the Nextcloud home dashboard.
 *
 * Intentionally does NOT load the time-client JS stack: this widget is pure
 * IAPIWidgetV2 + CSS. Pulling app scripts here would also pull l10n/*.js and
 * historically broke `/apps/dashboard` when those ran before `window.OC`.
 */
class AdminGlobalStatusWidget implements IAPIWidgetV2, IButtonWidget, IIconWidget, IReloadableWidget {
	use RegistersTimeClientTrait;

	private ?string $cachedWidgetUserId = null;

	/** @var array<string, mixed>|null */
	private ?array $cachedWidgetData = null;

	public function __construct(
		private readonly IL10N $l10n,
		private readonly IURLGenerator $urlGenerator,
		private readonly DashboardWidgetDataService $widgetDataService,
		private readonly WidgetIconHelper $widgetIconHelper,
	) {
	}

	public function getId(): string {
		return Application::APP_ID . '-admin-global-status';
	}

	public function getTitle(): string {
		return $this->l10n->t('Company status');
	}

	public function getOrder(): int {
		return 50;
	}

	public function getIconClass(): string {
		return 'icon-dashboard';
	}

	public function getIconUrl(): string {
		return $this->widgetIconHelper->getAbsoluteIconUrl();
	}

	public function getUrl(): ?string {
		return $this->absoluteRoute('arbeitszeitcheck.admin.dashboard');
	}

	public function load(): void {
		$this->registerDeskletStylesForWidget();
	}

	public function getItemsV2(string $userId, ?string $since = null, int $limit = 7): WidgetItems {
		$data = $this->getAdminData($userId, $limit);
		if (!(bool)$data['authorized']) {
			return new WidgetItems([], $this->l10n->t('This widget is only available for app administrators.'));
		}

		$copy = new WidgetStatusCopy($this->l10n);
		$icon = $this->getIconUrl();
		$dashboardUrl = $this->absoluteRoute('arbeitszeitcheck.admin.dashboard');
		$summary = is_array($data['summary'] ?? null) ? $data['summary'] : [];
		$absence = is_array($data['absenceSummary'] ?? null) ? $data['absenceSummary'] : [];
		$total = (int)($summary['total'] ?? 0);
		$working = (int)($summary['active'] ?? 0);

		$items = [
			new WidgetItem(
				$copy->workingHeadline($working, $total),
				$copy->summarySubtitle($summary, $absence),
				$dashboardUrl,
				$icon,
				'summary'
			),
		];

		/** @var list<array{userId?:string,displayName?:string,status?:string,workingTodayHours?:float|int}> $users */
		$users = is_array($data['users'] ?? null) ? $data['users'] : [];
		$peopleSlots = max(0, $limit - 1);
		$people = array_slice($copy->sortPeopleByStatus($users), 0, $peopleSlots);

		foreach ($people as $user) {
			$userIdKey = (string)($user['userId'] ?? '');
			$link = $userIdKey !== ''
				? $this->absoluteRoute('arbeitszeitcheck.admin.userDetail', ['userId' => $userIdKey])
				: $dashboardUrl;
			$items[] = new WidgetItem(
				(string)($user['displayName'] ?? $userIdKey),
				$copy->personSubtitle(
					(string)($user['status'] ?? 'clocked_out'),
					(float)($user['workingTodayHours'] ?? 0.0)
				),
				$link,
				$icon,
				$userIdKey !== '' ? $userIdKey : ('person-' . count($items))
			);
		}

		$footer = '';
		if (!empty($data['summaryTruncated'])) {
			$footer = $copy->truncationNote(
				(int)($data['summaryScopeLimit'] ?? 0),
				(int)($data['directoryTotal'] ?? 0)
			);
		}

		return new WidgetItems(
			$items,
			$this->l10n->t('No people found.'),
			$footer
		);
	}

	public function getWidgetButtons(string $userId): array {
		$data = $this->getAdminData($userId, 7);
		$buttons = [
			new WidgetButton(
				WidgetButton::TYPE_MORE,
				$this->absoluteRoute('arbeitszeitcheck.admin.dashboard'),
				$this->l10n->t('Open dashboard')
			),
		];

		if ((bool)($data['authorized'] ?? false) && !empty($data['summaryTruncated'])) {
			array_unshift($buttons, new WidgetButton(
				WidgetButton::TYPE_MORE,
				$this->absoluteRoute('arbeitszeitcheck.admin.users'),
				$this->l10n->t('Open employees')
			));
		}

		return $buttons;
	}

	public function getReloadInterval(): int {
		return 60;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function getAdminData(string $userId, int $limit): array {
		$effectiveLimit = max(1, $limit);
		if ($this->cachedWidgetUserId === $userId && is_array($this->cachedWidgetData)) {
			return $this->cachedWidgetData;
		}

		$data = $this->widgetDataService->getAdminWidgetData($userId, $effectiveLimit);
		$this->cachedWidgetUserId = $userId;
		$this->cachedWidgetData = $data;

		return $data;
	}

	/**
	 * @param array<string, mixed> $params
	 */
	private function absoluteRoute(string $route, array $params = []): string {
		return $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->linkToRoute($route, $params)
		);
	}
}
