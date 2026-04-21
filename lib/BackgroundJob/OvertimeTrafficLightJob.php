<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\BackgroundJob;

use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Service\NotificationService;
use OCA\ArbeitszeitCheck\Service\OvertimeNotificationMailService;
use OCA\ArbeitszeitCheck\Service\OvertimeService;
use OCA\ArbeitszeitCheck\Service\OvertimeTrafficLightService;
use OCA\ArbeitszeitCheck\Service\PermissionService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\IConfig;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class OvertimeTrafficLightJob extends TimedJob
{
	private const USER_VALUE_LAST_STATE = 'overtime_traffic_light_last_state';

	public function __construct(
		ITimeFactory $timeFactory,
		private OvertimeService $overtimeService,
		private OvertimeTrafficLightService $trafficLightService,
		private NotificationService $notificationService,
		private OvertimeNotificationMailService $mailService,
		private IUserManager $userManager,
		private IConfig $config,
		private PermissionService $permissionService,
		private LoggerInterface $logger,
	) {
		parent::__construct($timeFactory);
		$this->setInterval(24 * 60 * 60);
	}

	protected function run($argument): void
	{
		if (!$this->trafficLightService->isEnabled()) {
			return;
		}

		$thresholds = $this->trafficLightService->getThresholds();
		$matrix = $this->readMatrix();
		$recipients = $this->readRecipients();
		$yearStart = new \DateTime(date('Y-01-01 00:00:00'));
		$now = new \DateTime();
		$now->setTime(23, 59, 59);

		$this->userManager->callForAllUsers(function ($user) use ($thresholds, $matrix, $recipients, $yearStart, $now): void {
			$userId = $user->getUID();
			if (!$user->isEnabled() || !$this->permissionService->isUserAllowedByAccessGroups($userId)) {
				return;
			}
			try {
				$data = $this->overtimeService->calculateOvertime($userId, clone $yearStart, clone $now);
				$balance = (float)($data['cumulative_balance'] ?? 0.0);
				$classification = $this->trafficLightService->classify($balance, $thresholds);
				$currentState = (string)$classification['state'];
				$lastState = $this->config->getUserValue($userId, 'arbeitszeitcheck', self::USER_VALUE_LAST_STATE, 'green');
				if ($currentState === $lastState || $currentState === 'green') {
					$this->config->setUserValue($userId, 'arbeitszeitcheck', self::USER_VALUE_LAST_STATE, $currentState);
					return;
				}

				$direction = (string)($classification['direction'] ?? '');
				$level = (string)($classification['level'] ?? '');
				if (!isset($matrix[$direction][$level]) || $matrix[$direction][$level] !== true) {
					$this->config->setUserValue($userId, 'arbeitszeitcheck', self::USER_VALUE_LAST_STATE, $currentState);
					return;
				}

				$payload = [
					'state' => $currentState,
					'direction' => $direction,
					'level' => $level,
					'balance' => $balance,
					'user_id' => $userId,
				];
				$this->notificationService->notifyOvertimeTrafficLight($userId, $payload);
				$this->mailService->sendTrafficLightNotification($recipients, $payload);
				$this->config->setUserValue($userId, 'arbeitszeitcheck', self::USER_VALUE_LAST_STATE, $currentState);
			} catch (\Throwable $e) {
				$this->logger->warning('Overtime traffic-light job failed for user', [
					'user_id' => $userId,
					'exception' => $e->getMessage(),
				]);
			}
		});
	}

	/**
	 * @return array<string, array<string, bool>>
	 */
	private function readMatrix(): array
	{
		$raw = json_decode($this->config->getAppValue('arbeitszeitcheck', Constants::CONFIG_OVERTIME_NOTIFICATION_MATRIX_V1, '[]'), true);
		$matrix = [];
		foreach (Constants::OVERTIME_DIRECTIONS as $direction) {
			$matrix[$direction] = [];
			$inType = (is_array($raw) && isset($raw[$direction]) && is_array($raw[$direction])) ? $raw[$direction] : [];
			foreach (Constants::OVERTIME_LEVELS as $level) {
				$matrix[$direction][$level] = ($inType[$level] ?? false) === true;
			}
		}
		return $matrix;
	}

	/**
	 * @return list<string>
	 */
	private function readRecipients(): array
	{
		$raw = (string)$this->config->getAppValue('arbeitszeitcheck', Constants::CONFIG_OVERTIME_NOTIFICATION_RECIPIENTS, '');
		$parts = explode(',', $raw);
		$unique = [];
		foreach ($parts as $part) {
			$email = strtolower(trim($part));
			if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
				continue;
			}
			$unique[$email] = true;
		}
		return array_keys($unique);
	}
}

