<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Db;

use OCA\ArbeitszeitCheck\Constants;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class TariffRuleSetMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'at_tariff_rule_sets', TariffRuleSet::class);
	}

	/**
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 */
	public function find(int $id): TariffRuleSet {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->setMaxResults(1);
		return $this->findEntity($qb);
	}

	public function findByCodeAndVersion(string $tariffCode, string $version): ?TariffRuleSet {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('tariff_code', $qb->createNamedParameter($tariffCode)))
			->andWhere($qb->expr()->eq('version', $qb->createNamedParameter($version)))
			->setMaxResults(1);
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/**
	 * @return TariffRuleSet[]
	 */
	public function findAllOrdered(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->orderBy('status', 'ASC')
			->addOrderBy('tariff_code', 'ASC')
			->addOrderBy('version', 'DESC');
		return $this->findEntities($qb);
	}

	public function findActiveForDate(\DateTimeInterface $asOfDate): array {
		$date = $asOfDate->format('Y-m-d');
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('status', $qb->createNamedParameter(Constants::TARIFF_RULE_SET_STATUS_ACTIVE)))
			->andWhere($qb->expr()->lte('valid_from', $qb->createNamedParameter($date, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->orX(
				$qb->expr()->isNull('valid_to'),
				$qb->expr()->gte('valid_to', $qb->createNamedParameter($date, IQueryBuilder::PARAM_STR))
			))
			->orderBy('tariff_code', 'ASC')
			->addOrderBy('version', 'DESC');
		return $this->findEntities($qb);
	}

	/**
	 * @return TariffRuleSet[]
	 */
	public function findActiveByTariffCode(string $tariffCode): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('status', $qb->createNamedParameter(Constants::TARIFF_RULE_SET_STATUS_ACTIVE)))
			->andWhere($qb->expr()->eq('tariff_code', $qb->createNamedParameter($tariffCode)))
			->orderBy('valid_from', 'ASC')
			->addOrderBy('id', 'ASC');
		return $this->findEntities($qb);
	}
}

