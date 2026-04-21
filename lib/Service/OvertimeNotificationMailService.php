<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Service;

use OCP\IConfig;
use OCP\IL10N;
use OCP\Mail\IMailer;
use OCP\Mail\IMessage;
use Psr\Log\LoggerInterface;

class OvertimeNotificationMailService
{
	private const MAX_RECIPIENTS = 20;

	public function __construct(
		private IMailer $mailer,
		private IConfig $config,
		private IL10N $l10n,
		private ?LoggerInterface $logger = null,
	) {
	}

	/**
	 * @param list<string> $recipients
	 * @param array{state: string, direction: string, level: string, balance: float, user_id: string} $data
	 */
	public function sendTrafficLightNotification(array $recipients, array $data): void
	{
		$normalized = [];
		foreach ($recipients as $recipient) {
			$email = strtolower(trim($recipient));
			if ($email === '' || !$this->mailer->validateMailAddress($email)) {
				continue;
			}
			$normalized[$email] = true;
			if (count($normalized) >= self::MAX_RECIPIENTS) {
				break;
			}
		}

		if ($normalized === []) {
			return;
		}

		$subject = $this->l10n->t('Balance traffic light: %1$s (%2$s)', [
			(string)($data['user_id'] ?? 'unknown'),
			(string)($data['state'] ?? 'green'),
		]);
		$body = $this->l10n->t('User: %1$s', [(string)($data['user_id'] ?? 'unknown')]) . "\n"
			. $this->l10n->t('State: %1$s', [(string)($data['state'] ?? 'green')]) . "\n"
			. $this->l10n->t('Direction: %1$s', [(string)($data['direction'] ?? '-')]) . "\n"
			. $this->l10n->t('Level: %1$s', [(string)($data['level'] ?? '-')]) . "\n"
			. $this->l10n->t('Balance: %1$s h', [number_format((float)($data['balance'] ?? 0.0), 2)]);

		foreach (array_keys($normalized) as $email) {
			try {
				$message = $this->mailer->createMessage();
				$message->setSubject($subject);
				$message->setPlainBody($body);
				$message->setTo([$email => $email]);
				$this->setFrom($message);
				$this->mailer->send($message);
			} catch (\Throwable $e) {
				$this->logger?->warning('arbeitszeitcheck: Failed to send overtime traffic-light email', [
					'app' => 'arbeitszeitcheck',
					'email' => $email,
					'exception' => $e,
				]);
			}
		}
	}

	private function setFrom(IMessage $message): void
	{
		$fromAddress = (string)$this->config->getSystemValue('mail_from_address', '');
		$fromDomain = (string)$this->config->getSystemValue('mail_domain', 'localhost');
		if ($fromAddress === '') {
			return;
		}
		$from = $fromAddress . '@' . $fromDomain;
		$fromName = (string)$this->config->getSystemValue('mail_from_name', 'ArbeitszeitCheck');
		$message->setFrom([$from => $fromName]);
	}
}

