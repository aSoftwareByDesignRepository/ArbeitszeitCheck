<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Mapper for {@see OutlookIcalSubscriptionToken}.
 */
class OutlookIcalSubscriptionTokenMapper extends QBMapper
{
	public function __construct(IDBConnection $db)
	{
		parent::__construct($db, 'azc_outlook_ical_tokens', OutlookIcalSubscriptionToken::class);
	}

	/**
	 * Find the token row for a tenant/team scope (at most one row after migration 1040).
	 */
	public function findForTeamScope(string $tenantId, int $teamId): ?OutlookIcalSubscriptionToken
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('tenant_id', $qb->createNamedParameter($tenantId)))
			->andWhere($qb->expr()->eq('team_id', $qb->createNamedParameter($teamId, IQueryBuilder::PARAM_INT)));

		$entities = $this->findEntities($qb);
		return $entities[0] ?? null;
	}

	/**
	 * @deprecated Prefer {@see findForTeamScope}; manager is no longer part of the scope key.
	 */
	public function findForScope(string $tenantId, string $managerUserId, int $teamId): ?OutlookIcalSubscriptionToken
	{
		return $this->findForTeamScope($tenantId, $teamId);
	}

	/**
	 * Find active token by tenant + manager + team and token hash.
	 */
	public function findActiveByTokenHash(string $tenantId, string $managerUserId, int $teamId, string $tokenHash): ?OutlookIcalSubscriptionToken
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('tenant_id', $qb->createNamedParameter($tenantId)))
			->andWhere($qb->expr()->eq('manager_user_id', $qb->createNamedParameter($managerUserId)))
			->andWhere($qb->expr()->eq('team_id', $qb->createNamedParameter($teamId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('token_hash', $qb->createNamedParameter($tokenHash)))
			->andWhere($qb->expr()->eq('is_active', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)));

		$entities = $this->findEntities($qb);
		return $entities[0] ?? null;
	}

	/**
	 * Find active token by tenant + team + token hash.
	 * Used by the tokenized Outlook feed endpoint (managerUserId is not provided there).
	 */
	public function findActiveByTeamAndTokenHash(string $tenantId, int $teamId, string $tokenHash): ?OutlookIcalSubscriptionToken
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('tenant_id', $qb->createNamedParameter($tenantId)))
			->andWhere($qb->expr()->eq('team_id', $qb->createNamedParameter($teamId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('token_hash', $qb->createNamedParameter($tokenHash)))
			->andWhere($qb->expr()->eq('is_active', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)));

		$entities = $this->findEntities($qb);
		return $entities[0] ?? null;
	}

	/**
	 * Find the active token for a tenant + manager + team.
	 */
	public function findActiveFor(string $tenantId, string $managerUserId, int $teamId): ?OutlookIcalSubscriptionToken
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('tenant_id', $qb->createNamedParameter($tenantId)))
			->andWhere($qb->expr()->eq('manager_user_id', $qb->createNamedParameter($managerUserId)))
			->andWhere($qb->expr()->eq('team_id', $qb->createNamedParameter($teamId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('is_active', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)));

		$entities = $this->findEntities($qb);
		return $entities[0] ?? null;
	}

	/**
	 * Revoke all active tokens for tenant + manager + team.
	 *
	 * @return int number of revoked rows
	 */
	public function revokeActiveFor(string $tenantId, string $managerUserId, int $teamId, \DateTimeInterface $now): int
	{
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('is_active', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT))
			->set('revoked_at', $qb->createNamedParameter($now->format('Y-m-d H:i:s'), IQueryBuilder::PARAM_STR))
			->where($qb->expr()->eq('tenant_id', $qb->createNamedParameter($tenantId)))
			->andWhere($qb->expr()->eq('manager_user_id', $qb->createNamedParameter($managerUserId)))
			->andWhere($qb->expr()->eq('team_id', $qb->createNamedParameter($teamId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('is_active', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)));

		return $qb->executeStatement();
	}
}

