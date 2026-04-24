<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1021Date20260424100000 extends SimpleMigrationStep {
	public function __construct(
		private IDBConnection $db,
	) {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('at_absences')) {
			$table = $schema->getTable('at_absences');
			if (!$table->hasColumn('approved_by_user_id')) {
				$table->addColumn('approved_by_user_id', Types::STRING, [
					'notnull' => false,
					'length' => 64,
				]);
			}
			if (!$table->hasIndex('at_abs_approved_uid_idx')) {
				$table->addIndex(['approved_by_user_id'], 'at_abs_approved_uid_idx');
			}
		}

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		unset($schemaClosure, $options);
		$output->info('Deduplicating entitlement snapshots before unique key migration...');
		$this->deduplicateEntitlementSnapshots();
	}

	private function deduplicateEntitlementSnapshots(): void {
		$qb = $this->db->getQueryBuilder();
		$qb->select('user_id', 'period_key', 'as_of_date')
			->selectAlias($qb->createFunction('COUNT(*)'), 'dup_count')
			->from('at_entitlement_snapshots')
			->groupBy('user_id', 'period_key', 'as_of_date')
			->having($qb->expr()->gt($qb->createFunction('COUNT(*)'), $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)));

		$groups = $qb->executeQuery()->fetchAll();
		if (!is_array($groups)) {
			return;
		}

		foreach ($groups as $group) {
			$userId = (string)($group['user_id'] ?? '');
			$periodKey = (string)($group['period_key'] ?? '');
			$asOfDate = (string)($group['as_of_date'] ?? '');
			if ($userId === '' || $periodKey === '' || $asOfDate === '') {
				continue;
			}

			$idsQb = $this->db->getQueryBuilder();
			$idsQb->select('id')
				->from('at_entitlement_snapshots')
				->where($idsQb->expr()->eq('user_id', $idsQb->createNamedParameter($userId)))
				->andWhere($idsQb->expr()->eq('period_key', $idsQb->createNamedParameter($periodKey)))
				->andWhere($idsQb->expr()->eq('as_of_date', $idsQb->createNamedParameter($asOfDate, IQueryBuilder::PARAM_STR)))
				->orderBy('computed_at', 'DESC')
				->addOrderBy('id', 'DESC');
			$rows = $idsQb->executeQuery()->fetchAll();
			if (!is_array($rows) || count($rows) <= 1) {
				continue;
			}

			$deleteIds = [];
			foreach (array_slice($rows, 1) as $row) {
				$id = (int)($row['id'] ?? 0);
				if ($id > 0) {
					$deleteIds[] = $id;
				}
			}
			if ($deleteIds === []) {
				continue;
			}

			$deleteQb = $this->db->getQueryBuilder();
			$deleteQb->delete('at_entitlement_snapshots')
				->where($deleteQb->expr()->in('id', $deleteQb->createNamedParameter($deleteIds, IQueryBuilder::PARAM_INT_ARRAY)));
			$deleteQb->executeStatement();
		}
	}
}
