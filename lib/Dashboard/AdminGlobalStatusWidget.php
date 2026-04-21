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

class AdminGlobalStatusWidget implements IAPIWidgetV2, IButtonWidget, IIconWidget, IReloadableWidget {
	public function __construct(
		private readonly IL10N $l10n,
		private readonly IURLGenerator $urlGenerator,
		private readonly DashboardWidgetDataService $widgetDataService,
	) {
	}

	public function getId(): string {
		return Application::APP_ID . '-admin-global-status';
	}

	public function getTitle(): string {
		return $this->l10n->t('Company status overview');
	}

	public function getOrder(): int {
		return 50;
	}

	public function getIconClass(): string {
		return 'icon-dashboard';
	}

	public function getIconUrl(): string {
		return $this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath(Application::APP_ID, 'app.svg'));
	}

	public function getUrl(): ?string {
		return $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute('arbeitszeitcheck.admin.dashboard'));
	}

	public function load(): void {
	}

	public function getItemsV2(string $userId, ?string $since = null, int $limit = 7): WidgetItems {
		$data = $this->widgetDataService->getAdminWidgetData($userId, $limit);
		if (!(bool)$data['authorized']) {
			return new WidgetItems([], $this->l10n->t('This widget is only available for app administrators.'));
		}

		$items = [];
		foreach ($data['users'] as $user) {
			$items[] = new WidgetItem(
				(string)$user['displayName'],
				$this->l10n->t('Status: %1$s, Today: %2$s h', [
					$this->statusLabel((string)$user['status']),
					number_format((float)$user['workingTodayHours'], 2),
				]),
				$this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute('arbeitszeitcheck.admin.dashboard')),
				$this->getIconUrl(),
				(string)$user['userId']
			);
		}

		$summary = $data['summary'];
		$absenceSummary = $data['absenceSummary'] ?? [
			'vacation' => 0,
			'sick' => 0,
			'other_absent' => 0,
			'total_absent' => 0,
		];
		return new WidgetItems(
			$items,
			$this->l10n->t('No users found.'),
			$this->l10n->t(
				'Total:%1$d, Working:%2$d, Break:%3$d, Paused:%4$d, Clocked out:%5$d. Absent:%6$d (Vacation:%7$d, Sick:%8$d, Other:%9$d).',
				[
					(int)$summary['total'],
					(int)$summary['active'],
					(int)$summary['break'],
					(int)$summary['paused'],
					(int)$summary['clocked_out'],
					(int)$absenceSummary['total_absent'],
					(int)$absenceSummary['vacation'],
					(int)$absenceSummary['sick'],
					(int)$absenceSummary['other_absent'],
				]
			)
		);
	}

	public function getWidgetButtons(string $userId): array {
		return [
			new WidgetButton(
				WidgetButton::TYPE_MORE,
				$this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute('arbeitszeitcheck.admin.dashboard')),
				$this->l10n->t('Open admin dashboard')
			),
		];
	}

	public function getReloadInterval(): int {
		return 60;
	}

	private function statusLabel(string $status): string {
		return match ($status) {
			'active' => $this->l10n->t('Working'),
			'break' => $this->l10n->t('On Break'),
			'paused' => $this->l10n->t('Paused'),
			default => $this->l10n->t('Clocked Out'),
		};
	}
}
