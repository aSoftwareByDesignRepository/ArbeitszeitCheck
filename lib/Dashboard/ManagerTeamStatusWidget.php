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
 * Server-rendered team status list. CSS only in {@see load()} — no time-client
 * JS (avoids premature l10n/*.js on the Vue home dashboard).
 */
class ManagerTeamStatusWidget implements IAPIWidgetV2, IButtonWidget, IIconWidget, IReloadableWidget {
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
		return Application::APP_ID . '-manager-team-status';
	}

	public function getTitle(): string {
		return $this->l10n->t('Team status');
	}

	public function getOrder(): int {
		return 40;
	}

	public function getIconClass(): string {
		return 'icon-group';
	}

	public function getIconUrl(): string {
		return $this->widgetIconHelper->getAbsoluteIconUrl();
	}

	public function getUrl(): ?string {
		return $this->absoluteRoute('arbeitszeitcheck.manager.dashboard');
	}

	public function load(): void {
		$this->registerDeskletStylesForWidget();
	}

	public function getItemsV2(string $userId, ?string $since = null, int $limit = 7): WidgetItems {
		$data = $this->getManagerData($userId, $limit);
		$copy = new WidgetStatusCopy($this->l10n);
		$icon = $this->getIconUrl();
		$dashboardUrl = $this->absoluteRoute('arbeitszeitcheck.manager.dashboard');
		$summary = is_array($data['summary'] ?? null) ? $data['summary'] : [];
		$absence = is_array($data['absenceSummary'] ?? null) ? $data['absenceSummary'] : [];

		/** @var list<array{userId?:string,displayName?:string,status?:string,workingTodayHours?:float|int}> $members */
		$members = is_array($data['members'] ?? null) ? $data['members'] : [];
		if ($members === []) {
			return new WidgetItems([], $this->l10n->t('No team members found.'));
		}

		$total = max((int)($summary['total'] ?? 0), count($members));
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

		$peopleSlots = max(0, $limit - 1);
		$people = array_slice($copy->sortPeopleByStatus($members), 0, $peopleSlots);

		foreach ($people as $member) {
			$memberId = (string)($member['userId'] ?? '');
			$items[] = new WidgetItem(
				(string)($member['displayName'] ?? $memberId),
				$copy->personSubtitle(
					(string)($member['status'] ?? 'clocked_out'),
					(float)($member['workingTodayHours'] ?? 0.0)
				),
				$dashboardUrl,
				$icon,
				$memberId !== '' ? $memberId : ('member-' . count($items))
			);
		}

		return new WidgetItems(
			$items,
			$this->l10n->t('No team members found.'),
			''
		);
	}

	public function getWidgetButtons(string $userId): array {
		return [
			new WidgetButton(
				WidgetButton::TYPE_MORE,
				$this->absoluteRoute('arbeitszeitcheck.manager.dashboard'),
				$this->l10n->t('Open dashboard')
			),
		];
	}

	public function getReloadInterval(): int {
		return 45;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function getManagerData(string $userId, int $limit): array {
		$effectiveLimit = max(1, $limit);
		if ($this->cachedWidgetUserId === $userId && is_array($this->cachedWidgetData)) {
			return $this->cachedWidgetData;
		}

		$data = $this->widgetDataService->getManagerWidgetData($userId, $effectiveLimit);
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
