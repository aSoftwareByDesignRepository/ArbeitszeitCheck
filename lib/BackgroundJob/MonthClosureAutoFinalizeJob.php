<?php

declare(strict_types=1);

/**
 * Daily job: auto-finalize calendar months after grace period (see MonthClosureService).
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\BackgroundJob;

use OCA\ArbeitszeitCheck\Service\MonthClosureService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

class MonthClosureAutoFinalizeJob extends TimedJob
{
	public function __construct(
		ITimeFactory $timeFactory,
		private MonthClosureService $monthClosureService,
		private LoggerInterface $logger,
	) {
		parent::__construct($timeFactory);
		$this->setInterval(24 * 60 * 60);
	}

	protected function run($argument): void
	{
		try {
			$today = $this->time->getDateTime();
			$stats = $this->monthClosureService->runAutomaticFinalizeForAllUsers($today);
			$this->logger->info('Month closure auto-finalize job finished', [
				'app' => 'arbeitszeitcheck',
				'finalized' => $stats['finalized'],
				'pending_correction' => $stats['pending_correction'],
				'errors' => $stats['errors'],
			]);
		} catch (\Throwable $e) {
			$this->logger->error('Month closure auto-finalize job failed', [
				'app' => 'arbeitszeitcheck',
				'exception' => $e,
			]);
		}
	}
}
