<?php

declare(strict_types=1);

/**
 * Auto-approve pending absences that have no assignable approver under current team rules
 * (fixes rows stuck before the approver-resolution fix).
 *
 * Idempotent: safe to run multiple times.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Repair;

use OCA\ArbeitszeitCheck\Db\Absence;
use OCA\ArbeitszeitCheck\Db\AbsenceMapper;
use OCA\ArbeitszeitCheck\Service\AbsenceService;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class ReleaseStuckPendingAbsences implements IRepairStep
{
	public function __construct(
		private AbsenceMapper $absenceMapper,
		private AbsenceService $absenceService,
	) {
	}

	public function getName(): string
	{
		return 'Release pending absences with no assignable approver';
	}

	public function run(IOutput $output): void
	{
		$pending = $this->absenceMapper->findByStatus(Absence::STATUS_PENDING);
		$fixed = 0;
		foreach ($pending as $absence) {
			try {
				if ($this->absenceService->autoApprovePendingIfNoAssignableManager($absence->getId())) {
					$fixed++;
				}
			} catch (\Throwable $e) {
				\OCP\Log\logger('arbeitszeitcheck')->warning(
					'ReleaseStuckPendingAbsences: failed for absence ' . $absence->getId(),
					['app' => 'arbeitszeitcheck', 'exception' => $e]
				);
			}
		}
		if ($fixed > 0) {
			$output->info(sprintf('Auto-approved %d pending absence(s) that had no assignable approver.', $fixed));
		}
	}
}
