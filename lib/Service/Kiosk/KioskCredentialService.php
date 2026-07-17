<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Service\Kiosk;

use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Db\AuditLogMapper;
use OCA\ArbeitszeitCheck\Db\KioskCred;
use OCA\ArbeitszeitCheck\Db\KioskCredMapper;
use OCA\ArbeitszeitCheck\Kiosk\KioskCrypto;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IUserManager;
use OCP\Lock\ILockingProvider;

class KioskCredentialService
{
	private const IMPORT_MAX_BYTES = 1_048_576;

	public function __construct(
		private readonly KioskCredMapper $credMapper,
		private readonly KioskSettingsService $settingsService,
		private readonly IUserManager $userManager,
		private readonly AuditLogMapper $auditLogMapper,
		private readonly ITimeFactory $timeFactory,
		private readonly ILockingProvider $lockingProvider,
	) {
	}

	public function assertUserKioskAllowed(string $userId): void
	{
		if (!$this->settingsService->isUserKioskAllowed($userId)) {
			throw new KioskException('KIOSK_USER_NOT_ALLOWED');
		}
		if ($this->userManager->get($userId) === null) {
			throw new KioskException('KIOSK_USER_NOT_ALLOWED');
		}
	}

	/**
	 * @return array{id: int, userId: string, type: string}
	 */
	public function assignRfid(string $userId, string $rfidUid, string $createdBy, ?string $label = null): array
	{
		$this->assertUserKioskAllowed($userId);
		$normalized = KioskCrypto::normalizeRfidUid($rfidUid);
		if ($normalized === '') {
			throw new KioskException('KIOSK_RFID_INVALID');
		}
		$lookup = $this->settingsService->rfidLookupHash($normalized);
		if ($this->credMapper->findByLookupHash($lookup) !== null) {
			throw new KioskException('KIOSK_RFID_ALREADY_ASSIGNED');
		}

		$now = $this->timeFactory->getDateTime();
		$cred = $this->credMapper->findByUserAndType($userId, 'rfid');
		if ($cred === null) {
			$cred = new KioskCred();
			$cred->setUserId($userId);
			$cred->setType('rfid');
			$cred->setCreatedBy($createdBy);
			$cred->setCreatedAt($now);
		}
		$cred->setLookupHash($lookup);
		$cred->setSecretHash(null);
		$cred->setLabel($label);
		$cred->setFailedAttempts(0);
		$cred->setLockedUntil(null);
		$cred = $cred->getId() === null ? $this->credMapper->insert($cred) : $this->credMapper->update($cred);

		$this->auditLogMapper->logAction($userId, 'kiosk_credential_assigned', 'kiosk_cred', $cred->getId(), null, [
			'type' => 'rfid',
			'method' => 'manual',
		], $createdBy);

		return ['id' => $cred->getId(), 'userId' => $userId, 'type' => 'rfid'];
	}

	/**
	 * @return array{pin: string, id: int}
	 */
	public function generatePin(string $userId, string $createdBy): array
	{
		$this->assertUserKioskAllowed($userId);
		$pin = KioskCrypto::generatePin();
		$now = $this->timeFactory->getDateTime();
		$cred = $this->credMapper->findByUserAndType($userId, 'pin');
		if ($cred === null) {
			$cred = new KioskCred();
			$cred->setUserId($userId);
			$cred->setType('pin');
			$cred->setCreatedBy($createdBy);
			$cred->setCreatedAt($now);
		}
		$cred->setSecretHash(KioskCrypto::hashSecret($pin));
		$cred->setLookupHash(null);
		$cred->setFailedAttempts(0);
		$cred->setLockedUntil(null);
		$cred = $cred->getId() === null ? $this->credMapper->insert($cred) : $this->credMapper->update($cred);

		$this->auditLogMapper->logAction($userId, 'kiosk_pin_generated', 'kiosk_cred', $cred->getId(), null, null, $createdBy);

		return ['pin' => $pin, 'id' => $cred->getId()];
	}

	public function revoke(int $credId, string $actorUserId): void
	{
		$cred = $this->credMapper->findById($credId);
		if ($cred === null) {
			throw new KioskException('KIOSK_CREDENTIAL_NOT_FOUND');
		}
		$userId = $cred->getUserId();
		$this->credMapper->delete($cred);
		$this->auditLogMapper->logAction($userId, 'kiosk_credential_revoked', 'kiosk_cred', $credId, null, null, $actorUserId);
	}

	public function findCredByRfidUid(string $rfidUid): ?KioskCred
	{
		$normalized = KioskCrypto::normalizeRfidUid($rfidUid);
		if ($normalized === '') {
			return null;
		}
		return $this->credMapper->findByLookupHash($this->settingsService->rfidLookupHash($normalized));
	}

	public function verifyPin(KioskCred $cred, string $pin): bool
	{
		if ($cred->getSecretHash() === null) {
			return false;
		}
		return KioskCrypto::verifySecret($pin, $cred->getSecretHash());
	}

	public function isLocked(KioskCred $cred): bool
	{
		$lockedUntil = $cred->getLockedUntil();
		if ($lockedUntil === null) {
			return false;
		}
		return $lockedUntil > $this->timeFactory->getDateTime();
	}

	public function recordFailedAttempt(KioskCred $cred): void
	{
		$id = $cred->getId();
		if ($id === null) {
			return;
		}
		$lockKey = 'arbeitszeitcheck/kiosk_cred/' . $id;
		$this->lockingProvider->acquireLock($lockKey, ILockingProvider::LOCK_EXCLUSIVE, 'Kiosk PIN lockout');
		try {
			$fresh = $this->credMapper->findById($id);
			if ($fresh === null) {
				return;
			}
			$attempts = $fresh->getFailedAttempts() + 1;
			$fresh->setFailedAttempts($attempts);
			if ($attempts >= Constants::KIOSK_MAX_FAILED_ATTEMPTS) {
				$locked = (clone $this->timeFactory->getDateTime())
					->modify('+' . Constants::KIOSK_LOCKOUT_SECONDS . ' seconds');
				$fresh->setLockedUntil($locked);
			}
			$this->credMapper->update($fresh);
			$cred->setFailedAttempts($fresh->getFailedAttempts());
			$cred->setLockedUntil($fresh->getLockedUntil());
		} finally {
			$this->lockingProvider->releaseLock($lockKey, ILockingProvider::LOCK_EXCLUSIVE);
		}
	}

	public function resetFailedAttempts(KioskCred $cred): void
	{
		$cred->setFailedAttempts(0);
		$cred->setLockedUntil(null);
		$this->credMapper->update($cred);
	}

	/** @return KioskCred[] */
	public function listCredentials(?string $userIdFilter = null): array
	{
		if ($userIdFilter !== null && $userIdFilter !== '') {
			return $this->credMapper->findByUserId($userIdFilter);
		}
		return $this->credMapper->findAll();
	}

	/**
	 * @return array{imported: int, skipped: int, errors: list<string>}
	 */
	public function importCsv(string $csvContent, string $createdBy): array
	{
		if (strlen($csvContent) > self::IMPORT_MAX_BYTES) {
			throw new KioskException('KIOSK_IMPORT_TOO_LARGE');
		}
		$imported = 0;
		$skipped = 0;
		$errors = [];
		$lines = preg_split('/\r\n|\r|\n/', trim($csvContent)) ?: [];
		$lineNo = 0;
		foreach ($lines as $line) {
			$lineNo++;
			$line = trim($line);
			if ($line === '' || str_starts_with($line, '#')) {
				continue;
			}
			if ($lineNo === 1 && str_contains(strtolower($line), 'uid')) {
				continue;
			}
			$parts = str_getcsv($line);
			if (count($parts) < 2) {
				$errors[] = "Line $lineNo: invalid format";
				$skipped++;
				continue;
			}
			$uid = trim($parts[0]);
			$userId = trim($parts[1]);
			$label = isset($parts[2]) ? trim($parts[2]) : null;
			try {
				$this->assignRfid($userId, $uid, $createdBy, $label !== '' ? $label : null);
				$imported++;
			} catch (KioskException $e) {
				$errors[] = "Line $lineNo: {$e->getErrorCode()}";
				$skipped++;
			}
		}
		return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
	}
}
