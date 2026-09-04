<?php

declare(strict_types=1);

/**
 * Keeps ProjectCheck billing hours (pc_time_entries) aligned with completed ArbeitszeitCheck
 * manual entries that carry a ProjectCheck project link.
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Service;

use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Db\TimeEntry;
use OCA\ArbeitszeitCheck\Db\TimeEntryMapper;
use OCP\App\IAppManager;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

class ProjectCheckLaborTimeSyncService
{
	public function __construct(
		private readonly IAppManager $appManager,
		private readonly TimeEntryMapper $timeEntryMapper,
		private readonly TimeZoneService $timeZoneService,
		private readonly IConfig $config,
		private readonly LoggerInterface $logger,
		private readonly ?object $projectCheckTimeEntryService = null,
	) {
	}

	/**
	 * @return array{success: bool, projectCheckTimeEntryId: ?int, message: ?string}
	 */
	public function syncFromTimeEntry(TimeEntry $entry, string $actorUserId): array
	{
		if ($this->appManager->isInstalled(Constants::APP_ID_PROJECTCHECK) !== true) {
			return ['success' => true, 'projectCheckTimeEntryId' => null, 'message' => null];
		}
		$svc = $this->projectCheckTimeEntryService;
		if ($svc === null || !\is_object($svc) || !\method_exists($svc, 'upsertFromArbeitszeitCheckBilling')) {
			return ['success' => true, 'projectCheckTimeEntryId' => null, 'message' => null];
		}

		$billableUserId = $entry->getUserId();
		$rawPid = $entry->getProjectCheckProjectId();
		$pid = $rawPid !== null && $rawPid !== '' ? (int)trim($rawPid) : 0;

		$existingPcId = $entry->getProjectCheckTimeEntryId();

		if ($entry->getStatus() !== TimeEntry::STATUS_COMPLETED || $entry->getEndTime() === null) {
			if ($existingPcId !== null && $existingPcId > 0) {
				return $this->deleteLinkedRow($svc, $actorUserId, $billableUserId, $existingPcId, $entry->getId());
			}
			return ['success' => true, 'projectCheckTimeEntryId' => null, 'message' => null];
		}

		if ($pid <= 0) {
			if ($existingPcId !== null && $existingPcId > 0) {
				return $this->deleteLinkedRow($svc, $actorUserId, $billableUserId, $existingPcId, $entry->getId());
			}
			return ['success' => true, 'projectCheckTimeEntryId' => null, 'message' => null];
		}

		$hours = $entry->getWorkingDurationHours();
		if ($hours === null || $hours <= 0.0) {
			if ($existingPcId !== null && $existingPcId > 0) {
				return $this->deleteLinkedRow($svc, $actorUserId, $billableUserId, $existingPcId, $entry->getId());
			}
			return ['success' => true, 'projectCheckTimeEntryId' => null, 'message' => null];
		}

		$start = $entry->getStartTime();
		if (!$start instanceof \DateTimeInterface) {
			return ['success' => false, 'projectCheckTimeEntryId' => $existingPcId, 'message' => 'missing_start'];
		}

		$dateStr = $this->timeZoneService->formatForDisplay($start, 'Y-m-d', $billableUserId);
		try {
			$dateOnly = new \DateTimeImmutable($dateStr . ' 00:00:00');
		} catch (\Throwable $e) {
			$this->logger->warning('ProjectCheck billing sync: invalid work date', ['exception' => $e]);
			return ['success' => false, 'projectCheckTimeEntryId' => $existingPcId, 'message' => 'invalid_date'];
		}

		$description = (string)($entry->getDescription() ?? '');
		try {
			/** @var mixed $newId */
			$newId = $svc->upsertFromArbeitszeitCheckBilling(
				$actorUserId,
				$billableUserId,
				$existingPcId !== null && $existingPcId > 0 ? $existingPcId : null,
				$pid,
				$dateOnly,
				$hours,
				$description,
			);
			$newId = (int)$newId;
			if ($newId > 0 && $newId !== $existingPcId) {
				$entry->setProjectCheckTimeEntryId($newId);
				$entry->setUpdatedAt(\OCA\ArbeitszeitCheck\Service\AppLocalNaiveDateTimeNormalizer::nowMutableInAppStorage($this->config));
				$this->timeEntryMapper->update($entry);
			}
			return ['success' => true, 'projectCheckTimeEntryId' => $newId, 'message' => null];
		} catch (\Throwable $e) {
			$this->logger->warning('ProjectCheck billing sync failed: ' . $e->getMessage(), ['exception' => $e]);
			return ['success' => false, 'projectCheckTimeEntryId' => $existingPcId, 'message' => $e->getMessage()];
		}
	}

	/**
	 * @param array<string, mixed> $deletedSummary from {@see TimeEntry::getSummary()} before delete
	 */
	public function onTimeEntryDeleted(array $deletedSummary, string $actorUserId): void
	{
		if ($this->appManager->isInstalled(Constants::APP_ID_PROJECTCHECK) !== true) {
			return;
		}
		$svc = $this->projectCheckTimeEntryService;
		if ($svc === null || !\method_exists($svc, 'deleteFromArbeitszeitCheckBilling')) {
			return;
		}
		$pcId = isset($deletedSummary['projectCheckTimeEntryId']) ? (int)$deletedSummary['projectCheckTimeEntryId'] : 0;
		if ($pcId <= 0) {
			return;
		}
		$billable = isset($deletedSummary['userId']) ? (string)$deletedSummary['userId'] : '';
		if ($billable === '') {
			return;
		}
		try {
			$svc->deleteFromArbeitszeitCheckBilling($actorUserId, $billable, $pcId);
		} catch (\Throwable $e) {
			$this->logger->warning('ProjectCheck billing delete failed: ' . $e->getMessage(), ['exception' => $e]);
		}
	}

	/**
	 * @param object $svc
	 * @return array{success: bool, projectCheckTimeEntryId: ?int, message: ?string}
	 */
	private function deleteLinkedRow($svc, string $actorUserId, string $billableUserId, int $pcId, int $atEntryId): array
	{
		try {
			$svc->deleteFromArbeitszeitCheckBilling($actorUserId, $billableUserId, $pcId);
			$entry = $this->timeEntryMapper->find($atEntryId);
			$entry->setProjectCheckTimeEntryId(null);
			$entry->setUpdatedAt(AppLocalNaiveDateTimeNormalizer::nowMutableInAppStorage($this->config));
			$this->timeEntryMapper->update($entry);
			return ['success' => true, 'projectCheckTimeEntryId' => null, 'message' => null];
		} catch (\Throwable $e) {
			$this->logger->warning('ProjectCheck billing unlink failed: ' . $e->getMessage(), ['exception' => $e]);
			return ['success' => false, 'projectCheckTimeEntryId' => $pcId, 'message' => $e->getMessage()];
		}
	}
}
