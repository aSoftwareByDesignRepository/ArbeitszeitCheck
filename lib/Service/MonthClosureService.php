<?php

declare(strict_types=1);

/**
 * Revision-safe month closure: canonical snapshot, hash chain, finalize/reopen.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Service;

use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Exception\MonthFinalizedException;
use OCA\ArbeitszeitCheck\Db\Absence;
use OCA\ArbeitszeitCheck\Db\AbsenceMapper;
use OCA\ArbeitszeitCheck\Db\AuditLogMapper;
use OCA\ArbeitszeitCheck\Db\MonthClosure;
use OCA\ArbeitszeitCheck\Db\MonthClosureMapper;
use OCA\ArbeitszeitCheck\Db\MonthClosureRevision;
use OCA\ArbeitszeitCheck\Db\MonthClosureRevisionMapper;
use OCA\ArbeitszeitCheck\Db\TimeEntry;
use OCA\ArbeitszeitCheck\Db\TimeEntryMapper;
use OCP\IDBConnection;
use OCP\IConfig;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class MonthClosureService
{
	/** Stored in finalized_by / audit when the daily job seals a month. */
	public const AUTO_FINALIZE_ACTOR_ID = 'system:month_closure_auto';

	private MonthClosureMapper $closureMapper;
	private MonthClosureRevisionMapper $revisionMapper;
	private ReportingService $reportingService;
	private TimeEntryMapper $timeEntryMapper;
	private AbsenceMapper $absenceMapper;
	private AuditLogMapper $auditLogMapper;
	private IDBConnection $db;
	private IConfig $config;
	private IUserManager $userManager;
	private LoggerInterface $logger;

	public function __construct(
		MonthClosureMapper $closureMapper,
		MonthClosureRevisionMapper $revisionMapper,
		ReportingService $reportingService,
		TimeEntryMapper $timeEntryMapper,
		AbsenceMapper $absenceMapper,
		AuditLogMapper $auditLogMapper,
		IDBConnection $db,
		IConfig $config,
		IUserManager $userManager,
		LoggerInterface $logger
	) {
		$this->closureMapper = $closureMapper;
		$this->revisionMapper = $revisionMapper;
		$this->reportingService = $reportingService;
		$this->timeEntryMapper = $timeEntryMapper;
		$this->absenceMapper = $absenceMapper;
		$this->auditLogMapper = $auditLogMapper;
		$this->db = $db;
		$this->config = $config;
		$this->userManager = $userManager;
		$this->logger = $logger;
	}

	public function getGraceDaysAfterEndOfMonth(): int
	{
		$v = (int)$this->config->getAppValue('arbeitszeitcheck', Constants::CONFIG_MONTH_CLOSURE_GRACE_DAYS_AFTER_EOM, '0');
		return max(0, min(90, $v));
	}

	/**
	 * Last calendar day (00:00) on which the employee can still finalize manually before auto job may seal (inclusive).
	 * If grace is 0, returns null (no automatic deadline).
	 */
	public function getManualFinalizeDeadlineDate(int $year, int $month): ?\DateTimeImmutable
	{
		$n = $this->getGraceDaysAfterEndOfMonth();
		if ($n < 1) {
			return null;
		}
		$d = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
		$d = $d->modify('last day of this month');
		$d = $d->modify('+' . $n . ' days');
		return $d->setTime(0, 0, 0);
	}

	public function isPastAutoFinalizeDeadline(int $year, int $month, \DateTimeInterface $today): bool
	{
		if ($this->getGraceDaysAfterEndOfMonth() < 1) {
			return false;
		}
		$deadline = $this->getManualFinalizeDeadlineDate($year, $month);
		if ($deadline === null) {
			return false;
		}
		$todayNorm = new \DateTimeImmutable($today->format('Y-m-d'));
		$todayNorm = $todayNorm->setTime(0, 0, 0);
		return $todayNorm > $deadline;
	}

	/**
	 * True if the given calendar month is strictly after the current calendar month (server date).
	 * Finalization is not allowed for future months.
	 */
	public function isCalendarMonthStrictlyAfterCurrent(int $year, int $month): bool
	{
		$today = new \DateTimeImmutable('today');
		$curY = (int)$today->format('Y');
		$curM = (int)$today->format('n');
		return ($year > $curY) || ($year === $curY && $month > $curM);
	}

	/**
	 * Pending time entry corrections (manager approval) or open absence workflow in this month block sealing.
	 */
	public function monthBlocksFinalization(string $targetUserId, int $year, int $month): bool
	{
		return $this->monthHasPendingTimeEntryApproval($targetUserId, $year, $month)
			|| $this->monthHasOpenAbsenceWorkflow($targetUserId, $year, $month);
	}

	/**
	 * Daily job: auto-seal months whose grace period has passed.
	 *
	 * @return array{finalized: int, pending_correction: int, errors: int}
	 */
	public function runAutomaticFinalizeForAllUsers(?\DateTimeInterface $today = null): array
	{
		$stats = ['finalized' => 0, 'pending_correction' => 0, 'errors' => 0];
		if (!MonthClosureFeature::isEnabledFromIConfig($this->config) || $this->getGraceDaysAfterEndOfMonth() < 1) {
			return $stats;
		}
		$today = $today ?? new \DateTime('today');
		$this->userManager->callForAllUsers(function (\OCP\IUser $user) use ($today, &$stats): void {
			if ($user->isEnabled() !== true) {
				return;
			}
			$uid = $user->getUID();
			for ($i = 1; $i <= 36; $i++) {
				$d = new \DateTime($today->format('Y-m-d'));
				$d->modify('first day of this month');
				$d->modify('-' . $i . ' months');
				$y = (int)$d->format('Y');
				$mo = (int)$d->format('n');
				try {
					$r = $this->tryAutomaticFinalize($uid, $y, $mo, $today);
					if ($r === 'finalized') {
						$stats['finalized']++;
					} elseif ($r === 'pending_correction') {
						$stats['pending_correction']++;
					}
				} catch (\Throwable $e) {
					$stats['errors']++;
					$this->logger->warning('Month closure auto-finalize: single user/month failed', [
						'app' => 'arbeitszeitcheck',
						'user' => $uid,
						'year' => $y,
						'month' => $mo,
						'exception' => $e,
					]);
				}
			}
		});
		return $stats;
	}

	/**
	 * @return 'finalized'|'skipped'|'pending_correction'
	 */
	public function tryAutomaticFinalize(string $targetUserId, int $year, int $month, \DateTimeInterface $today): string
	{
		if (!MonthClosureFeature::isEnabledFromIConfig($this->config)) {
			return 'skipped';
		}
		if ($this->getGraceDaysAfterEndOfMonth() < 1) {
			return 'skipped';
		}
		if ($this->isCalendarMonthStrictlyAfterCurrent($year, $month)) {
			return 'skipped';
		}
		if (!$this->isPastAutoFinalizeDeadline($year, $month, $today)) {
			return 'skipped';
		}
		$existing = $this->closureMapper->findByUserAndMonthOptional($targetUserId, $year, $month);
		if ($existing !== null && $existing->getStatus() === MonthClosure::STATUS_FINALIZED) {
			return 'skipped';
		}
		if ($this->monthBlocksFinalization($targetUserId, $year, $month)) {
			return 'pending_correction';
		}
		$this->persistFinalizedMonth(self::AUTO_FINALIZE_ACTOR_ID, $targetUserId, $year, $month, $existing, 'month_closure_auto_finalized');
		return 'finalized';
	}

	private function monthHasPendingTimeEntryApproval(string $targetUserId, int $year, int $month): bool
	{
		$start = new \DateTime(sprintf('%04d-%02d-01', $year, $month));
		$start->setTime(0, 0, 0);
		$end = clone $start;
		$end->modify('last day of this month');
		$end->setTime(23, 59, 59);
		$entries = $this->timeEntryMapper->findByUserAndDateRange($targetUserId, $start, $end);
		foreach ($entries as $e) {
			if ($e->getStatus() === TimeEntry::STATUS_PENDING_APPROVAL) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Absences that still need workflow action would produce an unstable snapshot — block finalize.
	 */
	private function monthHasOpenAbsenceWorkflow(string $targetUserId, int $year, int $month): bool
	{
		$start = new \DateTime(sprintf('%04d-%02d-01', $year, $month));
		$start->setTime(0, 0, 0);
		$end = clone $start;
		$end->modify('last day of this month');
		$end->setTime(23, 59, 59);
		$absences = $this->absenceMapper->findByUserAndDateRange($targetUserId, $start, $end);
		$blocking = [
			Absence::STATUS_PENDING,
			Absence::STATUS_SUBSTITUTE_PENDING,
			Absence::STATUS_SUBSTITUTE_DECLINED,
		];
		foreach ($absences as $a) {
			if (in_array($a->getStatus(), $blocking, true)) {
				return true;
			}
		}
		return false;
	}

	public function isMonthFinalized(string $userId, int $year, int $month): bool
	{
		$row = $this->closureMapper->findByUserAndMonthOptional($userId, $year, $month);
		return $row !== null && $row->getStatus() === MonthClosure::STATUS_FINALIZED;
	}

	public function getClosureRow(string $userId, int $year, int $month): ?MonthClosure
	{
		return $this->closureMapper->findByUserAndMonthOptional($userId, $year, $month);
	}

	/**
	 * Locked months cannot be mutated via the app (even if the feature toggle is off).
	 */
	public function assertDateRangeMutable(string $userId, \DateTime $rangeStart, \DateTime $rangeEnd): void
	{
		$months = $this->monthsOverlappingRange($rangeStart, $rangeEnd);
		foreach ($months as [$y, $m]) {
			if ($this->isMonthFinalized($userId, $y, $m)) {
				throw new MonthFinalizedException('month_finalized');
			}
		}
	}

	/**
	 * @return array{0: int, 1: int}[]
	 */
	public function monthsOverlappingRange(\DateTime $rangeStart, \DateTime $rangeEnd): array
	{
		$a = clone $rangeStart;
		$a->modify('first day of this month')->setTime(0, 0, 0);
		$b = clone $rangeEnd;
		$b->modify('first day of this month')->setTime(0, 0, 0);
		$out = [];
		$cur = clone $a;
		while ($cur <= $b) {
			$out[] = [(int)$cur->format('Y'), (int)$cur->format('n')];
			$cur->modify('+1 month');
		}
		return $out;
	}

	/**
	 * Build canonical snapshot payload (same input for hash and PDF/report).
	 *
	 * @return array<string, mixed>
	 */
	public function buildCanonicalPayload(string $userId, int $year, int $month): array
	{
		$monthDate = new \DateTime(sprintf('%04d-%02d-01', $year, $month));
		$start = clone $monthDate;
		$start->setTime(0, 0, 0);
		$end = clone $monthDate;
		$end->modify('last day of this month');
		$end->setTime(23, 59, 59);

		$report = $this->reportingService->generateMonthlyReport($monthDate, $userId);
		$entries = $this->timeEntryMapper->findByUserAndDateRange($userId, $start, $end);
		$absences = $this->absenceMapper->findByUserAndDateRange($userId, $start, $end);

		usort($entries, static function (TimeEntry $a, TimeEntry $b) {
			return $a->getId() <=> $b->getId();
		});
		usort($absences, static function (Absence $a, Absence $b) {
			return $a->getId() <=> $b->getId();
		});

		$entryRows = array_map(function (TimeEntry $e) {
			return [
				'id' => $e->getId(),
				'start' => $e->getStartTime() ? $e->getStartTime()->format(\DateTimeInterface::ATOM) : null,
				'end' => $e->getEndTime() ? $e->getEndTime()->format(\DateTimeInterface::ATOM) : null,
				'status' => $e->getStatus(),
				'is_manual' => $e->getIsManualEntry(),
				'breaks' => $e->getBreaks(),
				'description' => $e->getDescription(),
			];
		}, $entries);

		$absenceRows = array_map(function (Absence $a) {
			return [
				'id' => $a->getId(),
				'type' => $a->getType(),
				'start_date' => $a->getStartDate()->format('Y-m-d'),
				'end_date' => $a->getEndDate()->format('Y-m-d'),
				'days' => $a->getDays(),
				'status' => $a->getStatus(),
			];
		}, $absences);

		return [
			'schema' => MonthClosureCanonical::SCHEMA_V1,
			'user_id' => $userId,
			'year' => $year,
			'month' => $month,
			'period' => [
				'start' => $start->format('Y-m-d'),
				'end' => $end->format('Y-m-d'),
			],
			'report' => $report,
			'time_entries' => $entryRows,
			'absences' => $absenceRows,
		];
	}

	public function getSnapshotReportArray(string $userId, int $year, int $month): ?array
	{
		$row = $this->closureMapper->findByUserAndMonthOptional($userId, $year, $month);
		if ($row === null || $row->getStatus() !== MonthClosure::STATUS_FINALIZED || $row->getCanonicalPayload() === null) {
			return null;
		}
		$data = json_decode($row->getCanonicalPayload(), true);
		return is_array($data) ? $data : null;
	}

	/**
	 * Monthly report payload for API when the month is finalized (immutable snapshot).
	 *
	 * @return array<string, mixed>|null
	 */
	public function getFinalizedMonthlyReportForUser(string $userId, int $year, int $month): ?array
	{
		$row = $this->closureMapper->findByUserAndMonthOptional($userId, $year, $month);
		if ($row === null || $row->getStatus() !== MonthClosure::STATUS_FINALIZED || $row->getCanonicalPayload() === null) {
			return null;
		}
		$data = json_decode($row->getCanonicalPayload(), true);
		if (!is_array($data) || !isset($data['report']) || !is_array($data['report'])) {
			return null;
		}
		$r = $data['report'];
		$r['from_month_closure_snapshot'] = true;
		$r['snapshot_hash'] = $row->getSnapshotHash();
		$r['month_closure_version'] = $row->getVersion();
		return $r;
	}

	/**
	 * @throws \RuntimeException
	 */
	public function finalizeMonth(string $actorUserId, string $targetUserId, int $year, int $month): MonthClosure
	{
		if (!MonthClosureFeature::isEnabledFromIConfig($this->config)) {
			throw new \RuntimeException('feature_disabled');
		}
		if ($actorUserId !== $targetUserId) {
			throw new \RuntimeException('forbidden');
		}

		$existing = $this->closureMapper->findByUserAndMonthOptional($targetUserId, $year, $month);
		if ($existing !== null && $existing->getStatus() === MonthClosure::STATUS_FINALIZED) {
			throw new \RuntimeException('already_finalized');
		}

		if ($this->isCalendarMonthStrictlyAfterCurrent($year, $month)) {
			throw new \RuntimeException('future_month');
		}

		if ($this->monthBlocksFinalization($targetUserId, $year, $month)) {
			throw new \RuntimeException('pending_correction');
		}

		return $this->persistFinalizedMonth($actorUserId, $targetUserId, $year, $month, $existing, 'month_closure_finalized');
	}

	/**
	 * @throws \Throwable
	 */
	private function persistFinalizedMonth(
		string $actorUserId,
		string $targetUserId,
		int $year,
		int $month,
		?MonthClosure $existing,
		string $auditEventName
	): MonthClosure {
		$payloadArr = $this->buildCanonicalPayload($targetUserId, $year, $month);
		$canonicalJson = MonthClosureCanonical::encode($payloadArr);

		$prevRow = $this->closureMapper->findLatestFinalizedBefore($targetUserId, $year, $month);
		$prevHash = ($prevRow !== null && $prevRow->getSnapshotHash() !== null) ? $prevRow->getSnapshotHash() : '';

		$newVersion = ($existing !== null) ? ($existing->getVersion() + 1) : 1;
		$snapshotHash = MonthClosureCanonical::hashChain($prevHash, $targetUserId, $year, $month, $newVersion, $canonicalJson);

		$this->db->beginTransaction();
		try {
			$now = new \DateTime('now', new \DateTimeZone('UTC'));
			if ($existing === null) {
				$c = new MonthClosure();
				$c->setUserId($targetUserId);
				$c->setYear($year);
				$c->setMonth($month);
				$c->setVersion($newVersion);
				$c->setStatus(MonthClosure::STATUS_FINALIZED);
				$c->setSnapshotHash($snapshotHash);
				$c->setPrevSnapshotHash($prevHash !== '' ? $prevHash : null);
				$c->setCanonicalPayload($canonicalJson);
				$c->setFinalizedAt($now);
				$c->setFinalizedBy($actorUserId);
				$c->setReopenedAt(null);
				$c->setReopenedBy(null);
				$c->setReopenReason(null);
				$c = $this->closureMapper->insert($c);
			} else {
				$existing->setVersion($newVersion);
				$existing->setStatus(MonthClosure::STATUS_FINALIZED);
				$existing->setSnapshotHash($snapshotHash);
				$existing->setPrevSnapshotHash($prevHash !== '' ? $prevHash : null);
				$existing->setCanonicalPayload($canonicalJson);
				$existing->setFinalizedAt($now);
				$existing->setFinalizedBy($actorUserId);
				$existing->setReopenedAt(null);
				$existing->setReopenedBy(null);
				$existing->setReopenReason(null);
				$c = $this->closureMapper->update($existing);
			}

			$rev = new MonthClosureRevision();
			$rev->setClosureId($c->getId());
			$rev->setVersion($newVersion);
			$rev->setSnapshotHash($snapshotHash);
			$rev->setPrevSnapshotHash($prevHash !== '' ? $prevHash : null);
			$rev->setCanonicalPayload($canonicalJson);
			$rev->setSealedAt($now);
			$rev->setSealedBy($actorUserId);
			$this->revisionMapper->insert($rev);

			$this->auditLogMapper->logAction(
				$targetUserId,
				$auditEventName,
				'month_closure',
				$c->getId(),
				null,
				['year' => $year, 'month' => $month, 'version' => $newVersion, 'snapshot_hash' => $snapshotHash],
				$actorUserId
			);

			$this->db->commit();
			return $c;
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}

	/**
	 * Admin-only reopen (caller must enforce).
	 *
	 * @throws \RuntimeException
	 */
	public function reopenMonth(string $adminUserId, string $targetUserId, int $year, int $month, string $reason): MonthClosure
	{
		if ($reason === '') {
			throw new \RuntimeException('reason_required');
		}
		$existing = $this->closureMapper->findByUserAndMonthOptional($targetUserId, $year, $month);
		if ($existing === null || $existing->getStatus() !== MonthClosure::STATUS_FINALIZED) {
			throw new \RuntimeException('not_finalized');
		}

		$this->db->beginTransaction();
		try {
			$now = new \DateTime('now', new \DateTimeZone('UTC'));
			$existing->setStatus(MonthClosure::STATUS_OPEN);
			$existing->setSnapshotHash(null);
			$existing->setPrevSnapshotHash(null);
			$existing->setCanonicalPayload(null);
			$existing->setFinalizedAt(null);
			$existing->setFinalizedBy(null);
			$existing->setReopenedAt($now);
			$existing->setReopenedBy($adminUserId);
			$existing->setReopenReason($reason);
			$u = $this->closureMapper->update($existing);

			$this->auditLogMapper->logAction(
				$targetUserId,
				'month_closure_reopened',
				'month_closure',
				$u->getId(),
				null,
				['year' => $year, 'month' => $month, 'reason' => $reason],
				$adminUserId
			);

			$this->db->commit();
			return $u;
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}

	public function buildPdfContent(string $userId, int $year, int $month, string $displayName): string
	{
		$snap = $this->getSnapshotReportArray($userId, $year, $month);
		$row = $this->closureMapper->findByUserAndMonthOptional($userId, $year, $month);
		if ($snap === null || $row === null || $row->getStatus() !== MonthClosure::STATUS_FINALIZED) {
			throw new \RuntimeException('not_finalized');
		}
		$title = 'ArbeitszeitCheck — Monatsnachweis ' . sprintf('%04d-%02d', $year, $month);
		$lines = [
			'Name / Kennung: ' . $displayName . ' (' . $userId . ')',
			'Zeitraum: ' . ($snap['period']['start'] ?? '') . ' — ' . ($snap['period']['end'] ?? ''),
			'Snapshot-Hash (SHA-256): ' . ($row->getSnapshotHash() ?? ''),
			'Vorheriger Hash: ' . ($row->getPrevSnapshotHash() ?? '(keiner)'),
			'Version: ' . $row->getVersion(),
			'',
			'Summe (Report): Std. gesamt: ' . ($this->fmtNum($snap['report']['total_hours'] ?? null)),
			'Ueberstunden: ' . $this->fmtNum($snap['report']['total_overtime'] ?? null),
			'Verstoesse: ' . (string)($snap['report']['violations_count'] ?? 0),
		];
		return MinimalPdfBuilder::build($title, $lines);
	}

	private function fmtNum($v): string
	{
		if ($v === null) {
			return '-';
		}
		return is_float($v) || is_int($v) ? (string)round((float)$v, 2) : (string)$v;
	}
}
