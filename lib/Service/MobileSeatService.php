<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Service;

use OCA\ArbeitszeitCheck\Db\MobileSeat;
use OCA\ArbeitszeitCheck\Db\MobileSeatMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IDBConnection;
use OCP\IUserManager;
use OCP\Lock\ILockingProvider;

class MobileSeatService
{
	private const CAPACITY_LOCK = 'arbeitszeitcheck/mobile_seat_capacity';

	public function __construct(
		private readonly MobileSeatMapper $mobileSeatMapper,
		private readonly LicenseService $licenseService,
		private readonly IUserManager $userManager,
		private readonly ITimeFactory $timeFactory,
		private readonly IDBConnection $db,
		private readonly ILockingProvider $lockingProvider,
	) {
	}

	public function isUserAllowed(string $userId): bool
	{
		if ($userId === '' || !$this->licenseService->isMobilePlanActive()) {
			return false;
		}
		return $this->mobileSeatMapper->findByUserId($userId) !== null;
	}

	public function getAssignedCount(): int
	{
		return $this->mobileSeatMapper->countSeats();
	}

	public function getSeatLimit(): int
	{
		return $this->licenseService->getMobileSeatLimit();
	}

	/** @return list<array{userId: string, displayName: string, assignedAt: string, assignedBy: string}> */
	public function listSeats(): array
	{
		$seats = [];
		foreach ($this->mobileSeatMapper->findAllOrdered() as $seat) {
			$userId = $seat->getUserId();
			$user = $this->userManager->get($userId);
			$seats[] = [
				'userId' => $userId,
				'displayName' => $user?->getDisplayName() ?? $userId,
				'assignedAt' => $seat->getAssignedAt()?->format('c') ?? '',
				'assignedBy' => $seat->getAssignedBy(),
			];
		}
		return $seats;
	}

	/**
	 * @return array{ok: bool, error?: string}
	 */
	public function assignSeat(string $userId, string $assignedBy): array
	{
		if ($userId === '' || $this->userManager->get($userId) === null) {
			return ['ok' => false, 'error' => 'user_not_found'];
		}
		if (!$this->licenseService->isMobilePlanActive()) {
			return ['ok' => false, 'error' => 'no_mobile_plan'];
		}
		if ($this->mobileSeatMapper->findByUserId($userId) !== null) {
			return ['ok' => true];
		}
		$limit = $this->getSeatLimit();

		// Exclusive capacity lock (parity with TerminalDeviceService::reserveSlot):
		// under READ COMMITTED two concurrent assignSeat calls must not both observe
		// free capacity and exceed the licensed seat limit.
		$this->lockingProvider->acquireLock(self::CAPACITY_LOCK, ILockingProvider::LOCK_EXCLUSIVE, 'Mobile seat capacity');
		try {
			$this->db->beginTransaction();
			try {
				if ($this->mobileSeatMapper->findByUserId($userId) !== null) {
					$this->db->commit();
					return ['ok' => true];
				}
				if ($this->mobileSeatMapper->countSeats() >= $limit) {
					$this->db->rollBack();
					return ['ok' => false, 'error' => 'seat_limit_reached'];
				}

				$seat = new MobileSeat();
				$seat->setUserId($userId);
				$seat->setAssignedAt($this->timeFactory->getDateTime());
				$seat->setAssignedBy($assignedBy);
				$this->mobileSeatMapper->insert($seat);
				$this->db->commit();
			} catch (\OCP\DB\Exception $e) {
				$this->db->rollBack();
				if ($this->mobileSeatMapper->findByUserId($userId) !== null) {
					return ['ok' => true];
				}
				throw $e;
			} catch (\Throwable $e) {
				$this->db->rollBack();
				throw $e;
			}
		} finally {
			$this->lockingProvider->releaseLock(self::CAPACITY_LOCK, ILockingProvider::LOCK_EXCLUSIVE);
		}
		return ['ok' => true];
	}

	/**
	 * @return array{ok: bool, error?: string}
	 */
	public function removeSeat(string $userId): array
	{
		if ($userId === '') {
			return ['ok' => false, 'error' => 'invalid_user'];
		}
		try {
			$seat = $this->mobileSeatMapper->findByUserId($userId);
			if ($seat === null) {
				return ['ok' => true];
			}
			$this->mobileSeatMapper->delete($seat);
		} catch (DoesNotExistException) {
			return ['ok' => true];
		}
		return ['ok' => true];
	}

	/** Remove most recently assigned seats when the license limit shrinks. */
	public function trimToLimit(int $limit): int
	{
		$limit = max(0, $limit);
		$seats = $this->mobileSeatMapper->findAllOrdered();
		if (count($seats) <= $limit) {
			return 0;
		}
		$toRemove = array_slice($seats, $limit);
		$removed = 0;
		foreach ($toRemove as $seat) {
			$this->mobileSeatMapper->delete($seat);
			$removed++;
		}
		return $removed;
	}

	public function removeAllSeats(): int
	{
		$seats = $this->mobileSeatMapper->findAllOrdered();
		foreach ($seats as $seat) {
			$this->mobileSeatMapper->delete($seat);
		}
		return count($seats);
	}
}
