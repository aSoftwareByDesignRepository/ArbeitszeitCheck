<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\BackgroundJob;

use OCA\ArbeitszeitCheck\Db\KioskEnrollmentMapper;
use OCA\ArbeitszeitCheck\Service\Kiosk\KioskTerminalService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

/**
 * Expires stale kiosk pairing codes, clears expired incomplete enrollments,
 * and keeps terminal tables tidy.
 */
class KioskMaintenanceJob extends TimedJob
{
	public function __construct(
		ITimeFactory $timeFactory,
		private readonly KioskTerminalService $kioskTerminalService,
		private readonly KioskEnrollmentMapper $enrollmentMapper,
		private readonly LoggerInterface $logger,
	) {
		parent::__construct($timeFactory);
		$this->setInterval(15 * 60);
	}

	protected function run($argument): void
	{
		$expired = $this->kioskTerminalService->expireStalePendingTerminals();
		if ($expired > 0) {
			$this->logger->info('Kiosk maintenance: expired pending terminals', ['count' => $expired]);
		}

		$enrollments = $this->enrollmentMapper->deleteExpiredIncomplete($this->time->getDateTime());
		if ($enrollments > 0) {
			$this->logger->info('Kiosk maintenance: deleted expired incomplete enrollments', [
				'count' => $enrollments,
			]);
		}
	}
}
