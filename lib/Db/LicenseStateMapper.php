<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class LicenseStateMapper extends QBMapper
{
	public function __construct(IDBConnection $db)
	{
		parent::__construct($db, 'azc_license_state', LicenseState::class);
	}

	public function findCurrent(): ?LicenseState
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->setMaxResults(1)
			->orderBy('id', 'DESC');
		try {
			return $this->findEntity($qb);
		} catch (\OCP\AppFramework\Db\DoesNotExistException) {
			return null;
		} catch (\OCP\AppFramework\Db\MultipleObjectsReturnedException) {
			return null;
		}
	}

	/**
	 * Replace-or-insert the single license row atomically so two concurrent
	 * applies cannot leave duplicate rows behind.
	 */
	public function upsert(LicenseState $state): LicenseState
	{
		$this->db->beginTransaction();
		try {
			$existing = $this->findCurrent();
			if ($existing !== null) {
				$existing->setCustomerId($state->getCustomerId());
				$existing->setValidUntil($state->getValidUntil());
				$existing->setMobileSeats($state->getMobileSeats());
				$existing->setTerminalDevices($state->getTerminalDevices());
				$existing->setBundle($state->getBundle());
				$existing->setKeyAppliedAt($state->getKeyAppliedAt());
				$existing->setPayloadB64($state->getPayloadB64());
				$existing->setSignatureB64($state->getSignatureB64());
				$existing->setBoundInstanceId($state->getBoundInstanceId());
				$existing->setLicenseFingerprint($state->getLicenseFingerprint());
				$result = $this->update($existing);
			} else {
				$result = $this->insert($state);
			}
			$this->db->commit();
			return $result;
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}

	public function deleteAll(): void
	{
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())->executeStatement();
	}
}
