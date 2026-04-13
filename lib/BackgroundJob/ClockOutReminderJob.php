<?php

declare(strict_types=1);

/**
 * Clock-out reminder background job for the arbeitszeitcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\BackgroundJob;

use OCA\ArbeitszeitCheck\Db\TimeEntryMapper;
use OCA\ArbeitszeitCheck\Db\UserSettingsMapper;
use OCA\ArbeitszeitCheck\Service\NotificationService;
use OCA\ArbeitszeitCheck\Service\PermissionService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\IConfig;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

/**
 * Clock-out reminder job
 *
 * Checks for users who are still clocked in and sends reminders
 * Runs every hour during business hours
 */
class ClockOutReminderJob extends TimedJob
{
	private TimeEntryMapper $timeEntryMapper;
	private UserSettingsMapper $userSettingsMapper;
	private NotificationService $notificationService;
	private IUserManager $userManager;
	private IConfig $config;
	private LoggerInterface $logger;
	private PermissionService $permissionService;

	public function __construct(
		ITimeFactory $timeFactory,
		TimeEntryMapper $timeEntryMapper,
		UserSettingsMapper $userSettingsMapper,
		NotificationService $notificationService,
		IUserManager $userManager,
		IConfig $config,
		LoggerInterface $logger,
		PermissionService $permissionService
	) {
		parent::__construct($timeFactory);
		$this->timeEntryMapper = $timeEntryMapper;
		$this->userSettingsMapper = $userSettingsMapper;
		$this->notificationService = $notificationService;
		$this->userManager = $userManager;
		$this->config = $config;
		$this->logger = $logger;
		$this->permissionService = $permissionService;

		// Run every hour
		$this->setInterval(60 * 60);
	}

	/**
	 * @inheritDoc
	 */
	protected function run($argument): void
	{
		$currentHour = (int)date('G');
		
		// Only run during business hours (6 AM to 10 PM)
		if ($currentHour < 6 || $currentHour >= 22) {
			return;
		}

		$this->logger->info('Starting clock-out reminder check');

		try {
			$remindersSent = 0;

			// Check all users
			$this->userManager->callForAllUsers(function ($user) use (&$remindersSent) {
				$userId = $user->getUID();

				if (!$user->isEnabled()) {
					return;
				}
				if (!$this->permissionService->isUserAllowedByAccessGroups($userId)) {
					return;
				}

				$notificationsEnabled = $this->userSettingsMapper->getBooleanSetting(
					$userId,
					'notifications_enabled',
					true
				);
				if (!$notificationsEnabled) {
					return;
				}

				// Find active time entries
				$activeEntry = $this->timeEntryMapper->findActiveByUser($userId);
				
				if ($activeEntry === null) {
					return;
				}

				// Check if entry is older than 8 hours (likely forgot to clock out)
				$startTime = $activeEntry->getStartTime();
				$now = new \DateTime();
				$hoursWorked = ($now->getTimestamp() - $startTime->getTimestamp()) / 3600;

				// Send reminder if worked more than 8 hours
				if ($hoursWorked >= 8) {
					$lastReminderKey = 'last_clock_out_reminder_' . $activeEntry->getId();
					$lastReminderTime = (int)$this->config->getUserValue(
						$userId,
						'arbeitszeitcheck',
						$lastReminderKey,
						'0'
					);

					if ($lastReminderTime < (time() - 3600)) {
						$this->notificationService->notifyClockOutReminder($userId, [
							'id' => $activeEntry->getId(),
							'start_time' => $startTime->format('H:i'),
							'hours_worked' => round($hoursWorked, 2)
						]);
						$this->config->setUserValue(
							$userId,
							'arbeitszeitcheck',
							$lastReminderKey,
							(string)time()
						);
						
						$remindersSent++;
						
						$this->logger->info('Clock-out reminder sent', [
							'user_id' => $userId,
							'entry_id' => $activeEntry->getId(),
							'hours_worked' => round($hoursWorked, 2)
						]);
					}
				}
			});

			if ($remindersSent > 0) {
				$this->logger->info('Clock-out reminder check completed', [
					'reminders_sent' => $remindersSent
				]);
			}
		} catch (\Exception $e) {
			$this->logger->error('Clock-out reminder check failed', [
				'exception' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			]);
		}
	}
}
