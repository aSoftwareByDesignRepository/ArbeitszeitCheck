<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class TariffRuleModuleMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'at_tariff_rule_modules', TariffRuleModule::class);
	}

	public function findByRuleSetId(int $ruleSetId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('rule_set_id', $qb->createNamedParameter($ruleSetId)))
			->orderBy('sort_order', 'ASC')
			->addOrderBy('id', 'ASC');
		return $this->findEntities($qb);
	}

	public function deleteByRuleSetId(int $ruleSetId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('rule_set_id', $qb->createNamedParameter($ruleSetId)));
		return $qb->executeStatement();
	}
}

