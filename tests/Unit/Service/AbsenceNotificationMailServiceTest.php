<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service;

use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Db\Absence;
use OCA\ArbeitszeitCheck\Service\AbsenceNotificationMailService;
use OCA\ArbeitszeitCheck\Service\TeamResolverService;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Mail\IMailer;
use OCP\Mail\IMessage;
use PHPUnit\Framework\TestCase;

class AbsenceNotificationMailServiceTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		if (!interface_exists(IMailer::class) || !interface_exists(IMessage::class)) {
			$this->markTestSkipped('Nextcloud OCP mail interfaces are not available in this isolated PHPUnit runtime.');
		}
	}

	public function testSendHrOfficeNotificationSendsToNormalizedRecipients(): void
	{
		$mailer = $this->createMock(IMailer::class);
		$config = $this->createMock(IConfig::class);
		$l10n = $this->createMock(IL10N::class);
		$userManager = $this->createMock(IUserManager::class);
		$urlGenerator = $this->createMock(IURLGenerator::class);
		$teamResolver = $this->createMock(TeamResolverService::class);
		$message = $this->createMock(IMessage::class);

		$l10n->method('t')->willReturnCallback(static fn (string $text, array $params = []): string => $params === [] ? $text : vsprintf($text, $params));
		$urlGenerator->method('linkToRouteAbsolute')->willReturn('https://example.local/link');
		$mailer->method('validateMailAddress')->willReturnCallback(static fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false);
		$mailer->method('createMessage')->willReturn($message);
		$mailer->expects($this->exactly(2))->method('send');

		$config->method('getAppValue')
			->willReturnCallback(function (string $app, string $key, string $default = ''): string {
				if ($key === Constants::CONFIG_HR_NOTIFICATIONS_ENABLED) {
					return '1';
				}
				if ($key === Constants::CONFIG_HR_NOTIFICATION_RECIPIENTS) {
					return 'hr@example.com, HR@example.com, ops@example.com,invalid';
				}
				if ($key === Constants::CONFIG_HR_NOTIFICATION_MATRIX_V1) {
					return '{"vacation":{"request_created":true}}';
				}
				return $default;
			});

		$employee = $this->createMock(IUser::class);
		$employee->method('getDisplayName')->willReturn('Max Mustermann');
		$userManager->method('get')->willReturn($employee);

		$absence = new Absence();
		$absence->setUserId('employee1');
		$absence->setType('vacation');
		$absence->setStartDate(new \DateTime('2026-05-01'));
		$absence->setEndDate(new \DateTime('2026-05-03'));
		$absence->setDays(3.0);

		$service = new AbsenceNotificationMailService(
			$mailer,
			$config,
			$l10n,
			$userManager,
			$urlGenerator,
			$teamResolver
		);

		$service->sendHrOfficeNotification($absence, 'request_created', 'manager1');
	}

	public function testSendHrOfficeNotificationSkipsWhenMatrixDisabled(): void
	{
		$mailer = $this->createMock(IMailer::class);
		$config = $this->createMock(IConfig::class);
		$l10n = $this->createMock(IL10N::class);
		$userManager = $this->createMock(IUserManager::class);
		$urlGenerator = $this->createMock(IURLGenerator::class);
		$teamResolver = $this->createMock(TeamResolverService::class);

		$l10n->method('t')->willReturnCallback(static fn (string $text, array $params = []): string => $params === [] ? $text : vsprintf($text, $params));
		$config->method('getAppValue')
			->willReturnCallback(function (string $app, string $key, string $default = ''): string {
				if ($key === Constants::CONFIG_HR_NOTIFICATIONS_ENABLED) {
					return '1';
				}
				if ($key === Constants::CONFIG_HR_NOTIFICATION_RECIPIENTS) {
					return 'hr@example.com';
				}
				if ($key === Constants::CONFIG_HR_NOTIFICATION_MATRIX_V1) {
					return '{"vacation":{"request_created":false}}';
				}
				return $default;
			});

		$mailer->expects($this->never())->method('send');
		$mailer->expects($this->never())->method('createMessage');

		$absence = new Absence();
		$absence->setUserId('employee1');
		$absence->setType('vacation');
		$absence->setStartDate(new \DateTime('2026-05-01'));
		$absence->setEndDate(new \DateTime('2026-05-03'));
		$absence->setDays(3.0);

		$service = new AbsenceNotificationMailService(
			$mailer,
			$config,
			$l10n,
			$userManager,
			$urlGenerator,
			$teamResolver
		);

		$service->sendHrOfficeNotification($absence, 'request_created');
	}
}
