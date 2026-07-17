<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Service\Kiosk;

use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Db\AuditLogMapper;
use OCA\ArbeitszeitCheck\Db\KioskEnrollment;
use OCA\ArbeitszeitCheck\Db\KioskEnrollmentMapper;
use OCA\ArbeitszeitCheck\Db\KioskTerminalMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IUserManager;
use OCP\Lock\ILockingProvider;

class KioskEnrollmentService
{
	public function __construct(
		private readonly KioskEnrollmentMapper $enrollmentMapper,
		private readonly KioskTerminalMapper $terminalMapper,
		private readonly KioskCredentialService $credentialService,
		private readonly KioskSettingsService $settingsService,
		private readonly IUserManager $userManager,
		private readonly AuditLogMapper $auditLogMapper,
		private readonly ITimeFactory $timeFactory,
		private readonly ILockingProvider $lockingProvider,
	) {
	}

	/**
	 * @return array{enrollmentId: int, terminalId: string, userId: string, displayName: string, expiresAt: string}
	 */
	public function start(string $userId, string $terminalId, string $createdBy): array
	{
		$this->credentialService->assertUserKioskAllowed($userId);
		$terminal = $this->terminalMapper->findByTerminalId($terminalId);
		if ($terminal === null) {
			throw new KioskException('KIOSK_TERMINAL_NOT_FOUND');
		}
		if ($terminal->getStatus() !== 'active') {
			throw new KioskException('KIOSK_TERMINAL_NOT_ACTIVE');
		}
		$user = $this->userManager->get($userId);
		if ($user === null) {
			throw new KioskException('KIOSK_USER_NOT_ALLOWED');
		}

		$lockKey = 'arbeitszeitcheck/kiosk_enroll/' . $terminalId;
		$this->lockingProvider->acquireLock($lockKey, ILockingProvider::LOCK_EXCLUSIVE, 'Kiosk enrollment start');
		try {
			$this->enrollmentMapper->cancelForTerminal($terminalId);
			$now = $this->timeFactory->getDateTime();
			$expires = (clone $now)->modify('+' . Constants::KIOSK_ENROLLMENT_TTL_SECONDS . ' seconds');

			$enrollment = new KioskEnrollment();
			$enrollment->setTerminalId($terminalId);
			$enrollment->setUserId($userId);
			$enrollment->setExpiresAt($expires);
			$enrollment->setCreatedBy($createdBy);
			$enrollment->setCreatedAt($now);
			$enrollment = $this->enrollmentMapper->insert($enrollment);

			$this->auditLogMapper->logAction($userId, 'kiosk_enrollment_started', 'kiosk_enrollment', $enrollment->getId(), null, [
				'terminalId' => $terminalId,
			], $createdBy);

			return [
				'enrollmentId' => $enrollment->getId(),
				'terminalId' => $terminalId,
				'userId' => $userId,
				'displayName' => $user->getDisplayName(),
				'expiresAt' => $expires->format('c'),
			];
		} finally {
			$this->lockingProvider->releaseLock($lockKey, ILockingProvider::LOCK_EXCLUSIVE);
		}
	}

	/**
	 * @return array{status: string, userId?: string, displayName?: string, expiresAt?: string, completedAt?: string}
	 */
	public function getStatus(string $terminalId): array
	{
		$now = $this->timeFactory->getDateTime();
		$active = $this->enrollmentMapper->findActiveByTerminalId($terminalId, $now);
		if ($active !== null) {
			$user = $this->userManager->get($active->getUserId());
			return [
				'status' => 'pending',
				'userId' => $active->getUserId(),
				'displayName' => $user !== null ? $user->getDisplayName() : $active->getUserId(),
				'expiresAt' => $active->getExpiresAt()->format('c'),
			];
		}

		$completed = $this->enrollmentMapper->findLatestCompletedByTerminalId($terminalId);
		if ($completed !== null && $completed->getCompletedAt() !== null) {
			$age = $now->getTimestamp() - $completed->getCompletedAt()->getTimestamp();
			if ($age < 120) {
				return [
					'status' => 'completed',
					'userId' => $completed->getUserId(),
					'completedAt' => $completed->getCompletedAt()->format('c'),
				];
			}
		}

		return ['status' => 'expired'];
	}

	public function cancel(string $terminalId, string $actorUserId): void
	{
		$this->enrollmentMapper->cancelForTerminal($terminalId);
		$this->auditLogMapper->logAction($actorUserId, 'kiosk_enrollment_cancelled', 'kiosk_enrollment', null, null, [
			'terminalId' => $terminalId,
		], $actorUserId);
	}

	/**
	 * @return array{displayName: string, message: string}
	 */
	public function completeScan(string $terminalId, string $rfidUid, string $actorLabel = 'enroll-scan'): array
	{
		$lockKey = 'arbeitszeitcheck/kiosk_enroll/' . $terminalId;
		$this->lockingProvider->acquireLock($lockKey, ILockingProvider::LOCK_EXCLUSIVE, 'Kiosk enrollment scan');
		try {
			$now = $this->timeFactory->getDateTime();
			$enrollment = $this->enrollmentMapper->findActiveByTerminalId($terminalId, $now);
			if ($enrollment === null) {
				throw new KioskException('ENROLLMENT_NOT_ACTIVE');
			}

			$enrollmentId = $enrollment->getId();
			if ($enrollmentId === null) {
				throw new KioskException('ENROLLMENT_NOT_ACTIVE');
			}

			// Claim first so a second concurrent scan cannot assign a different card.
			if (!$this->enrollmentMapper->claimComplete($enrollmentId, $now, $now)) {
				throw new KioskException('ENROLLMENT_NOT_ACTIVE');
			}

			try {
				$result = $this->credentialService->assignRfid(
					$enrollment->getUserId(),
					$rfidUid,
					$enrollment->getCreatedBy(),
				);
			} catch (\Throwable $e) {
				// Roll back the claim so the admin can retry enrollment.
				$enrollment->setCompletedAt(null);
				$this->enrollmentMapper->update($enrollment);
				throw $e;
			}

			$user = $this->userManager->get($enrollment->getUserId());
			$this->auditLogMapper->logAction($enrollment->getUserId(), 'kiosk_credential_assigned', 'kiosk_cred', $result['id'], null, [
				'type' => 'rfid',
				'method' => $actorLabel,
				'terminalId' => $terminalId,
			], $enrollment->getCreatedBy());

			return [
				'displayName' => $user !== null ? $user->getDisplayName() : $enrollment->getUserId(),
				'message' => 'Ausweis zugeordnet',
			];
		} finally {
			$this->lockingProvider->releaseLock($lockKey, ILockingProvider::LOCK_EXCLUSIVE);
		}
	}

	/** @return array{active: bool, message?: string, expiresAt?: string}|null */
	public function getConfigEnrollment(string $terminalId): ?array
	{
		$now = $this->timeFactory->getDateTime();
		$enrollment = $this->enrollmentMapper->findActiveByTerminalId($terminalId, $now);
		if ($enrollment === null) {
			return null;
		}
		return [
			'active' => true,
			'message' => 'Halten Sie die neue Karte an das Leserfeld',
			'expiresAt' => $enrollment->getExpiresAt()->format('c'),
		];
	}
}
