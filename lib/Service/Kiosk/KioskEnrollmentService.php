<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Service\Kiosk;

use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Db\AuditLogMapper;
use OCA\ArbeitszeitCheck\Db\KioskEnrollment;
use OCA\ArbeitszeitCheck\Db\KioskEnrollmentMapper;
use OCA\ArbeitszeitCheck\Db\KioskTerminalMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\ICacheFactory;
use OCP\IUserManager;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;

class KioskEnrollmentService
{
	private const SCAN_ERROR_TTL = 300;

	public function __construct(
		private readonly KioskEnrollmentMapper $enrollmentMapper,
		private readonly KioskTerminalMapper $terminalMapper,
		private readonly KioskCredentialService $credentialService,
		private readonly IUserManager $userManager,
		private readonly AuditLogMapper $auditLogMapper,
		private readonly ITimeFactory $timeFactory,
		private readonly ILockingProvider $lockingProvider,
		private readonly ICacheFactory $cacheFactory,
		private readonly KioskErrorMessages $errorMessages,
	) {
	}

	/**
	 * @return array{enrollmentId: int, terminalId: string, userId: string, displayName: string, expiresAt: string}
	 */
	public function start(string $userId, string $terminalId, string $createdBy): array
	{
		$userId = trim($userId);
		$terminalId = trim($terminalId);
		if ($userId === '') {
			throw new KioskException('KIOSK_USER_NOT_ALLOWED');
		}
		if ($terminalId === '') {
			throw new KioskException('KIOSK_TERMINAL_NOT_FOUND');
		}

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

		// Consistent lock order (user → terminal) avoids deadlocks with completeScan.
		$userLockKey = $this->userEnrollmentLockKey($userId);
		$terminalLockKey = $this->terminalEnrollmentLockKey($terminalId);
		$this->acquireExclusive($userLockKey, 'Kiosk enrollment start user');
		try {
			$this->acquireExclusive($terminalLockKey, 'Kiosk enrollment start terminal');
			try {
				$this->enrollmentMapper->cancelForTerminal($terminalId);
				$this->clearScanError($terminalId);
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
				$this->lockingProvider->releaseLock($terminalLockKey, ILockingProvider::LOCK_EXCLUSIVE);
			}
		} finally {
			$this->lockingProvider->releaseLock($userLockKey, ILockingProvider::LOCK_EXCLUSIVE);
		}
	}

	/**
	 * @return array{
	 *   status: string,
	 *   userId?: string,
	 *   displayName?: string,
	 *   expiresAt?: string,
	 *   completedAt?: string,
	 *   lastError?: string,
	 *   lastErrorMessage?: string
	 * }
	 */
	public function getStatus(string $terminalId): array
	{
		$terminalId = trim($terminalId);
		$now = $this->timeFactory->getDateTime();
		$active = $this->enrollmentMapper->findActiveByTerminalId($terminalId, $now);
		if ($active !== null) {
			$user = $this->userManager->get($active->getUserId());
			$out = [
				'status' => 'pending',
				'userId' => $active->getUserId(),
				'displayName' => $user !== null ? $user->getDisplayName() : $active->getUserId(),
				'expiresAt' => $active->getExpiresAt()->format('c'),
			];
			return $this->withLastError($terminalId, $out);
		}

		$completed = $this->enrollmentMapper->findLatestCompletedByTerminalId($terminalId);
		if ($completed !== null && $completed->getCompletedAt() !== null) {
			$age = $now->getTimestamp() - $completed->getCompletedAt()->getTimestamp();
			// Keep “completed” visible for the full admin poll window (TTL + buffer).
			if ($age < Constants::KIOSK_ENROLLMENT_TTL_SECONDS + 60) {
				$this->clearScanError($terminalId);
				return [
					'status' => 'completed',
					'userId' => $completed->getUserId(),
					'completedAt' => $completed->getCompletedAt()->format('c'),
				];
			}
		}

		$out = ['status' => 'expired'];
		return $this->withLastError($terminalId, $out);
	}

	/**
	 * Abort a pending badge enrollment for a terminal.
	 *
	 * Lock order is always user → terminal (same as start/completeScan) to avoid
	 * deadlocks. When no active enrollment is known yet, the terminal lock is taken
	 * alone first; if an enrollment appears, that lock is released before taking
	 * the full ordered pair.
	 *
	 * @return array{status: 'cancelled'|'already_idle'|'already_completed', enrollmentId?: int}
	 */
	public function cancel(string $terminalId, string $actorUserId): array
	{
		$terminalId = trim($terminalId);
		if ($terminalId === '') {
			throw new KioskException('KIOSK_TERMINAL_NOT_FOUND');
		}

		$now = $this->timeFactory->getDateTime();
		$preview = $this->enrollmentMapper->findActiveByTerminalId($terminalId, $now);
		if ($preview !== null) {
			return $this->cancelUnderUserTerminalLocks(
				$preview->getUserId(),
				$terminalId,
				$actorUserId,
			);
		}

		// Serialize cleanup with start/complete/identify without guessing a user lock.
		$terminalLockKey = $this->terminalEnrollmentLockKey($terminalId);
		$this->acquireExclusiveWithRetry($terminalLockKey, 'Kiosk enrollment cancel terminal');
		$discoveredUserId = null;
		try {
			$now = $this->timeFactory->getDateTime();
			$active = $this->enrollmentMapper->findActiveByTerminalId($terminalId, $now);
			if ($active === null) {
				$this->enrollmentMapper->cancelForTerminal($terminalId);
				$this->clearScanError($terminalId);
				return $this->cancelOutcomeWhenIdle($terminalId);
			}
			$discoveredUserId = $active->getUserId();
		} finally {
			$this->lockingProvider->releaseLock($terminalLockKey, ILockingProvider::LOCK_EXCLUSIVE);
		}

		return $this->cancelUnderUserTerminalLocks($discoveredUserId, $terminalId, $actorUserId);
	}

	/**
	 * @return array{status: 'cancelled'|'already_idle'|'already_completed', enrollmentId?: int}
	 */
	private function cancelUnderUserTerminalLocks(
		string $userId,
		string $terminalId,
		string $actorUserId,
	): array {
		$userLockKey = $this->userEnrollmentLockKey($userId);
		$terminalLockKey = $this->terminalEnrollmentLockKey($terminalId);
		// Cancel is an admin abort — brief retries beat a flaky 409 while a scan finishes.
		$this->acquireExclusiveWithRetry($userLockKey, 'Kiosk enrollment cancel user');
		try {
			$this->acquireExclusiveWithRetry($terminalLockKey, 'Kiosk enrollment cancel terminal');
			try {
				$now = $this->timeFactory->getDateTime();
				$active = $this->enrollmentMapper->findActiveByTerminalId($terminalId, $now);
				if ($active === null) {
					$this->enrollmentMapper->cancelForTerminal($terminalId);
					$this->clearScanError($terminalId);
					return $this->cancelOutcomeWhenIdle($terminalId);
				}

				$enrollmentId = $active->getId();
				$this->enrollmentMapper->cancelForTerminal($terminalId);
				$this->clearScanError($terminalId);
				$this->auditLogMapper->logAction(
					$active->getUserId(),
					'kiosk_enrollment_cancelled',
					'kiosk_enrollment',
					$enrollmentId,
					null,
					['terminalId' => $terminalId],
					$actorUserId,
				);

				$result = ['status' => 'cancelled'];
				if ($enrollmentId !== null) {
					$result['enrollmentId'] = $enrollmentId;
				}
				return $result;
			} finally {
				$this->lockingProvider->releaseLock($terminalLockKey, ILockingProvider::LOCK_EXCLUSIVE);
			}
		} finally {
			$this->lockingProvider->releaseLock($userLockKey, ILockingProvider::LOCK_EXCLUSIVE);
		}
	}

	/**
	 * @return array{status: 'already_idle'|'already_completed'}
	 */
	private function cancelOutcomeWhenIdle(string $terminalId): array
	{
		$now = $this->timeFactory->getDateTime();
		$completed = $this->enrollmentMapper->findLatestCompletedByTerminalId($terminalId);
		if ($completed !== null && $completed->getCompletedAt() !== null) {
			$age = $now->getTimestamp() - $completed->getCompletedAt()->getTimestamp();
			// Same visibility window as getStatus() — Admin may have raced the tablet save.
			if ($age < Constants::KIOSK_ENROLLMENT_TTL_SECONDS + 60) {
				return ['status' => 'already_completed'];
			}
		}
		return ['status' => 'already_idle'];
	}

	/**
	 * @return array{displayName: string, message: string}
	 */
	public function completeScan(string $terminalId, string $rfidUid, string $actorLabel = 'enroll-scan'): array
	{
		$terminalId = trim($terminalId);
		$now = $this->timeFactory->getDateTime();
		$enrollmentPreview = $this->enrollmentMapper->findActiveByTerminalId($terminalId, $now);
		if ($enrollmentPreview === null) {
			throw new KioskException('ENROLLMENT_NOT_ACTIVE');
		}

		$userLockKey = $this->userEnrollmentLockKey($enrollmentPreview->getUserId());
		$terminalLockKey = $this->terminalEnrollmentLockKey($terminalId);
		$this->acquireExclusive($userLockKey, 'Kiosk enrollment scan user');
		try {
			$this->acquireExclusive($terminalLockKey, 'Kiosk enrollment scan terminal');
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
						null,
						$actorLabel,
						false, // enrollment logs the assign below — avoid duplicate audit rows
					);
				} catch (\Throwable $e) {
					// Roll back the claim so the admin can retry enrollment.
					$enrollment->setCompletedAt(null);
					$this->enrollmentMapper->update($enrollment);
					if ($e instanceof KioskException) {
						$this->rememberScanError($terminalId, $e->getErrorCode());
						throw $e;
					}
					if ($e instanceof LockedException) {
						$this->rememberScanError($terminalId, 'KIOSK_BUSY');
						throw new KioskException('KIOSK_BUSY');
					}
					$this->rememberScanError($terminalId, 'KIOSK_SCAN_FAILED');
					throw new KioskException('KIOSK_SCAN_FAILED');
				}

				$this->clearScanError($terminalId);
				$user = $this->userManager->get($enrollment->getUserId());
				$this->auditLogMapper->logAction($enrollment->getUserId(), 'kiosk_credential_assigned', 'kiosk_cred', $result['id'], null, [
					'type' => 'rfid',
					'method' => $actorLabel,
					'terminalId' => $terminalId,
				], $enrollment->getCreatedBy());

				return [
					'displayName' => $user !== null ? $user->getDisplayName() : $enrollment->getUserId(),
					// Client localizes success copy (tablet locale ≠ server Accept-Language).
					'message' => '',
				];
			} finally {
				$this->lockingProvider->releaseLock($terminalLockKey, ILockingProvider::LOCK_EXCLUSIVE);
			}
		} finally {
			$this->lockingProvider->releaseLock($userLockKey, ILockingProvider::LOCK_EXCLUSIVE);
		}
	}

	/**
	 * @return array{active: bool, displayName?: string, expiresAt?: string}|null
	 */
	public function getConfigEnrollment(string $terminalId): ?array
	{
		$now = $this->timeFactory->getDateTime();
		$enrollment = $this->enrollmentMapper->findActiveByTerminalId($terminalId, $now);
		if ($enrollment === null) {
			return null;
		}
		$user = $this->userManager->get($enrollment->getUserId());
		return [
			'active' => true,
			'displayName' => $user !== null ? $user->getDisplayName() : $enrollment->getUserId(),
			'expiresAt' => $enrollment->getExpiresAt()->format('c'),
		];
	}

	private function acquireExclusive(string $lockKey, string $label): void
	{
		try {
			$this->lockingProvider->acquireLock($lockKey, ILockingProvider::LOCK_EXCLUSIVE, $label);
		} catch (LockedException) {
			throw new KioskException('KIOSK_BUSY');
		}
	}

	/**
	 * Acquire an exclusive lock with short retries. Used by cancel so a brief
	 * completeScan/identify window does not surface as a hard failure to Admin.
	 */
	private function acquireExclusiveWithRetry(string $lockKey, string $label, int $attempts = 4): void
	{
		$attempts = max(1, $attempts);
		$delayUs = 40000; // 40ms, then 80ms, 160ms…
		for ($i = 0; $i < $attempts; $i++) {
			try {
				$this->lockingProvider->acquireLock($lockKey, ILockingProvider::LOCK_EXCLUSIVE, $label);
				return;
			} catch (LockedException) {
				if ($i + 1 >= $attempts) {
					break;
				}
				usleep($delayUs);
				$delayUs = min(200000, $delayUs * 2);
			}
		}
		throw new KioskException('KIOSK_BUSY');
	}

	/**
	 * @param array<string, mixed> $out
	 * @return array<string, mixed>
	 */
	private function withLastError(string $terminalId, array $out): array
	{
		$code = $this->peekScanError($terminalId);
		if ($code === null || $code === '') {
			return $out;
		}
		$out['lastError'] = $code;
		$out['lastErrorMessage'] = $this->errorMessages->message($code);
		return $out;
	}

	private function rememberScanError(string $terminalId, string $code): void
	{
		$this->cacheFactory->createDistributed('azc_kiosk_enroll')->set(
			$this->scanErrorKey($terminalId),
			$code,
			self::SCAN_ERROR_TTL,
		);
	}

	private function peekScanError(string $terminalId): ?string
	{
		$value = $this->cacheFactory->createDistributed('azc_kiosk_enroll')->get(
			$this->scanErrorKey($terminalId),
		);
		return is_string($value) && $value !== '' ? $value : null;
	}

	private function clearScanError(string $terminalId): void
	{
		$this->cacheFactory->createDistributed('azc_kiosk_enroll')->remove(
			$this->scanErrorKey($terminalId),
		);
	}

	private function scanErrorKey(string $terminalId): string
	{
		return 'err_' . hash('sha256', $terminalId);
	}

	private function userEnrollmentLockKey(string $userId): string
	{
		return KioskEnrollmentLockKeys::forUser($userId);
	}

	private function terminalEnrollmentLockKey(string $terminalId): string
	{
		return KioskEnrollmentLockKeys::forTerminal($terminalId);
	}
}
