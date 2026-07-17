<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class KioskSessionMapper extends QBMapper
{
	public function __construct(IDBConnection $db)
	{
		parent::__construct($db, 'at_kiosk_sessions', KioskSession::class);
	}

	public function deleteExpiredForTerminal(string $terminalId, \DateTimeInterface $now): void
	{
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('terminal_id', $qb->createNamedParameter($terminalId)))
			->andWhere($qb->expr()->lt('expires_at', $qb->createNamedParameter($now->format('Y-m-d H:i:s'))));
		$qb->executeStatement();
	}

	public function deleteByUserId(string $userId): void
	{
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
		$qb->executeStatement();
	}

	public function findValidSession(string $terminalId, string $sessionToken, \DateTimeInterface $now): ?KioskSession
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('terminal_id', $qb->createNamedParameter($terminalId)))
			->andWhere($qb->expr()->gt('expires_at', $qb->createNamedParameter($now->format('Y-m-d H:i:s'))))
			->andWhere($qb->expr()->isNull('used_at'));
		foreach ($this->findEntities($qb) as $session) {
			if (\OCA\ArbeitszeitCheck\Kiosk\KioskCrypto::verifySecret($sessionToken, $session->getTokenHash())) {
				return $session;
			}
		}
		return null;
	}

	public function markUsed(KioskSession $session): void
	{
		$session->setUsedAt(new \DateTime());
		$this->update($session);
	}

	/**
	 * Atomically consume a one-shot session. Returns true only for the winner
	 * under concurrent action requests with the same session token.
	 */
	public function claimUnused(KioskSession $session, \DateTimeInterface $usedAt): bool
	{
		$id = $session->getId();
		if ($id === null) {
			return false;
		}
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('used_at', $qb->createNamedParameter($usedAt->format('Y-m-d H:i:s')))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNull('used_at'));
		$affected = $qb->executeStatement();
		if ($affected === 1) {
			$session->setUsedAt(\DateTime::createFromInterface($usedAt));
			return true;
		}
		return false;
	}
}
