<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\BackgroundJob;

use OCA\ArbeitszeitCheck\Service\LicenseEnforcementService;
use OCA\ArbeitszeitCheck\Service\LicenseService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

/**
 * Daily enforcement when AZC2 licenses expire or limits shrink overnight.
 */
class LicenseMaintenanceJob extends TimedJob
{
	public function __construct(
		ITimeFactory $timeFactory,
		private readonly LicenseService $licenseService,
		private readonly LicenseEnforcementService $licenseEnforcementService,
		private readonly LoggerInterface $logger,
	) {
		parent::__construct($timeFactory);
		$this->setInterval(24 * 60 * 60);
	}

	protected function run($argument): void
	{
		if (!$this->licenseService->hasStoredLicense()) {
			return;
		}

		$enforced = $this->licenseEnforcementService->enforceCurrentLimits();
		if ($this->licenseService->isStoredLicenseExpired()) {
			$this->logger->info('AZC2 license maintenance: expired license trimmed', $enforced);
			return;
		}

		if (($enforced['mobileSeatsRemoved'] ?? 0) > 0 || ($enforced['terminalsRevoked'] ?? 0) > 0) {
			$this->logger->info('AZC2 license maintenance: limits enforced', $enforced);
		}
	}
}
