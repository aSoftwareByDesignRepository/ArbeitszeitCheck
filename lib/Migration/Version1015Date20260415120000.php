<?php

declare(strict_types=1);

/**
 * Normalize legacy UTC timestamps to Europe/Berlin and fix paused entries.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Migration;

use Closure;
use DateTime;
use DateTimeZone;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1015Date20260415120000 extends SimpleMigrationStep
{
	private const MIGRATION_DONE_FLAG = 'tz_utc_to_berlin_migration_done';
	private const APP_TIMEZONE_KEY = 'app_timezone';

	/**
	 * @var array<string, list<string>>
	 */
	private const DATETIME_COLUMNS = [
		'at_entries' => ['start_time', 'end_time', 'break_start_time', 'break_end_time', 'created_at', 'updated_at', 'approved_at'],
		'at_absences' => ['approved_at', 'created_at', 'updated_at'],
		'at_violations' => ['resolved_at', 'created_at'],
		'at_audit' => ['created_at'],
		'at_settings' => ['created_at', 'updated_at'],
		'at_models' => ['created_at', 'updated_at'],
		'at_user_models' => ['created_at', 'updated_at'],
		'at_month_closure' => ['finalized_at', 'reopened_at'],
		'at_month_closure_revision' => ['sealed_at'],
		'at_vacation_rollover_log' => ['created_at'],
	];

	public function __construct(
		private IDBConnection $db,
		private IConfig $config
	) {
	}

	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void
	{
		if ($this->config->getAppValue('arbeitszeitcheck', self::MIGRATION_DONE_FLAG, '0') === '1') {
			return;
		}

		$utc = new DateTimeZone('UTC');
		$berlin = new DateTimeZone('Europe/Berlin');

		foreach (self::DATETIME_COLUMNS as $table => $columns) {
			$this->convertTableDatetimes($table, $columns, $utc, $berlin);
		}

		$this->completePausedEntriesWithoutEndTime();

		$this->config->setAppValue('arbeitszeitcheck', self::APP_TIMEZONE_KEY, 'Europe/Berlin');
		$this->config->setAppValue('arbeitszeitcheck', self::MIGRATION_DONE_FLAG, '1');
	}

	private function completePausedEntriesWithoutEndTime(): void
	{
		try {
			$qb = $this->db->getQueryBuilder();
			$qb->update('at_entries')
				->set('end_time', 'updated_at')
				->set('status', $qb->createNamedParameter('completed', IQueryBuilder::PARAM_STR))
				->where($qb->expr()->eq('status', $qb->createNamedParameter('paused', IQueryBuilder::PARAM_STR)))
				->andWhere($qb->expr()->isNull('end_time'));
			$qb->executeStatement();
		} catch (\Throwable $e) {
			if ($this->isMissingTableError($e->getMessage())) {
				return;
			}
			throw $e;
		}
	}

	/**
	 * @param list<string> $columns
	 */
	private function convertTableDatetimes(string $table, array $columns, DateTimeZone $sourceTz, DateTimeZone $targetTz): void
	{
		try {
			$selectQb = $this->db->getQueryBuilder();
			$selectQb->select('id');
			foreach ($columns as $column) {
				$selectQb->addSelect($column);
			}
			$selectQb->from($table);
			$rows = $selectQb->executeQuery()->fetchAll();
		} catch (\Throwable $e) {
			if ($this->isMissingTableError($e->getMessage())) {
				return;
			}
			throw $e;
		}

		if (!is_array($rows) || $rows === []) {
			return;
		}

		foreach ($rows as $row) {
			$updates = [];
			foreach ($columns as $column) {
				$value = $row[$column] ?? null;
				if (!is_string($value) || $value === '') {
					continue;
				}

				$converted = $this->convertUtcStringToTimezone($value, $sourceTz, $targetTz);
				if ($converted !== null && $converted !== $value) {
					$updates[$column] = $converted;
				}
			}

			if ($updates === []) {
				continue;
			}

			$updateQb = $this->db->getQueryBuilder();
			$updateQb->update($table);
			foreach ($updates as $column => $value) {
				$updateQb->set($column, $updateQb->createNamedParameter($value, IQueryBuilder::PARAM_STR));
			}
			$updateQb->where($updateQb->expr()->eq('id', $updateQb->createNamedParameter((int)$row['id'], IQueryBuilder::PARAM_INT)));
			$updateQb->executeStatement();
		}
	}

	private function convertUtcStringToTimezone(string $value, DateTimeZone $sourceTz, DateTimeZone $targetTz): ?string
	{
		$dateTime = DateTime::createFromFormat('Y-m-d H:i:s', $value, $sourceTz);
		if (!$dateTime instanceof DateTime) {
			return null;
		}

		$dateTime->setTimezone($targetTz);
		return $dateTime->format('Y-m-d H:i:s');
	}

	private function isMissingTableError(string $message): bool
	{
		return str_contains($message, "doesn't exist")
			|| str_contains($message, 'does not exist')
			|| str_contains($message, 'no such table')
			|| str_contains($message, 'undefined table')
			|| str_contains($message, 'relation ');
	}
}
