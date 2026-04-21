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

class ManagerTeamStatusWidget implements IAPIWidgetV2, IButtonWidget, IIconWidget, IReloadableWidget {
	public function __construct(
		private readonly IL10N $l10n,
		private readonly IURLGenerator $urlGenerator,
		private readonly DashboardWidgetDataService $widgetDataService,
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
		return $this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath(Application::APP_ID, 'app.svg'));
	}

	public function getUrl(): ?string {
		return $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute('arbeitszeitcheck.manager.dashboard'));
	}

	public function load(): void {
	}

	public function getItemsV2(string $userId, ?string $since = null, int $limit = 7): WidgetItems {
		$data = $this->widgetDataService->getManagerWidgetData($userId, $limit);
		$members = $data['members'];

		$items = [];
		foreach ($members as $member) {
			$items[] = new WidgetItem(
				(string)$member['displayName'],
				$this->l10n->t('Status: %1$s, Today: %2$s h', [
					$this->statusLabel((string)$member['status']),
					number_format((float)$member['workingTodayHours'], 2),
				]),
				$this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute('arbeitszeitcheck.manager.dashboard')),
				$this->getIconUrl(),
				(string)$member['userId']
			);
		}

		if ($items === []) {
			return new WidgetItems([], $this->l10n->t('No team members found.'));
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
			$this->l10n->t('No team members found.'),
			$this->l10n->t(
				'Working:%1$d, Break:%2$d, Paused:%3$d, Clocked out:%4$d. Absent:%5$d (Vacation:%6$d, Sick:%7$d, Other:%8$d).',
				[
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
				$this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute('arbeitszeitcheck.manager.dashboard')),
				$this->l10n->t('Open manager dashboard')
			),
		];
	}

	public function getReloadInterval(): int {
		return 45;
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
