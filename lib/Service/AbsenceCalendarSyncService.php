<?php

declare(strict_types=1);

/**
 * Writes approved absences as all-day events into the user's default writable calendar (Nextcloud Calendar / CalDAV).
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Service;

use OCA\ArbeitszeitCheck\Db\Absence;
use OCA\ArbeitszeitCheck\Db\AbsenceCalendar;
use OCA\ArbeitszeitCheck\Db\AbsenceCalendarMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Calendar\ICreateFromString;
use OCP\Calendar\IManager;
use OCP\Constants;
use OCP\IL10N;
use OCP\IURLGenerator;

class AbsenceCalendarSyncService
{
	public function __construct(
		private IManager $calendarManager,
		private AbsenceCalendarMapper $absenceCalendarMapper,
		private IL10N $l10n,
		private ITimeFactory $timeFactory,
		private IURLGenerator $urlGenerator,
		private ?\OCA\DAV\CalDAV\CalDavBackend $calDavBackend = null,
	) {
	}

	/**
	 * Create or replace the calendar object for an approved absence.
	 */
	public function syncApprovedAbsence(Absence $absence): void
	{
		if ($absence->getStatus() !== Absence::STATUS_APPROVED) {
			return;
		}
		$start = $absence->getStartDate();
		$end = $absence->getEndDate();
		if (!$start || !$end) {
			return;
		}

		$userId = $absence->getUserId();
		$principal = 'principals/users/' . $userId;
		if (!$this->calendarManager->isEnabled()) {
			return;
		}

		$calendar = $this->pickWritableCalendar($principal);
		if (!$calendar instanceof ICreateFromString) {
			return;
		}

		$existing = $this->absenceCalendarMapper->findByAbsenceIdOrNull($absence->getId());
		if ($existing !== null) {
			if ($this->calDavBackend !== null) {
				try {
					$this->calDavBackend->deleteCalendarObject(
						$existing->getCalendarId(),
						$existing->getObjectUri()
					);
				} catch (\Throwable $e) {
					\OCP\Log\logger('arbeitszeitcheck')->warning(
						'Could not delete previous calendar object for absence ' . $absence->getId() . ': ' . $e->getMessage(),
						['exception' => $e]
					);
				}
			}
			$this->absenceCalendarMapper->deleteByAbsenceId($absence->getId());
		}

		$uid = $this->buildUid($absence->getId());
		$summary = $this->summaryForType($absence->getType());
		$ics = $this->buildAllDayIcs($uid, $start, $end, $summary);

		$objectUri = 'arbeitszeitcheck-absence-' . $absence->getId() . '.ics';
		try {
			$calendar->createFromString($objectUri, $ics);
		} catch (\Throwable $e) {
			\OCP\Log\logger('arbeitszeitcheck')->error(
				'Calendar sync failed for absence ' . $absence->getId() . ': ' . $e->getMessage(),
				['exception' => $e]
			);
			return;
		}

		$calendarId = (int)$calendar->getKey();
		$row = new AbsenceCalendar();
		$row->setAbsenceId($absence->getId());
		$row->setUserId($userId);
		$row->setCalendarId($calendarId);
		$row->setObjectUri($objectUri);
		$row->setCreatedAt($this->timeFactory->getDateTime());
		$this->absenceCalendarMapper->insert($row);
	}

	public function removeAbsenceCalendar(int $absenceId): void
	{
		$existing = $this->absenceCalendarMapper->findByAbsenceIdOrNull($absenceId);
		if ($existing === null) {
			return;
		}
		if ($this->calDavBackend !== null) {
			try {
				$this->calDavBackend->deleteCalendarObject(
					$existing->getCalendarId(),
					$existing->getObjectUri()
				);
			} catch (\Throwable $e) {
				\OCP\Log\logger('arbeitszeitcheck')->warning(
					'Could not delete calendar object for absence ' . $absenceId . ': ' . $e->getMessage(),
					['exception' => $e]
				);
			}
		}
		$this->absenceCalendarMapper->deleteByAbsenceId($absenceId);
	}

	private function pickWritableCalendar(string $principalUri): ?object
	{
		try {
			$calendars = $this->calendarManager->getCalendarsForPrincipal($principalUri);
		} catch (\Throwable $e) {
			return null;
		}
		foreach ($calendars as $cal) {
			if (!$cal instanceof ICreateFromString) {
				continue;
			}
			if (($cal->getPermissions() & Constants::PERMISSION_CREATE) !== Constants::PERMISSION_CREATE) {
				continue;
			}
			if ($cal->isDeleted()) {
				continue;
			}
			return $cal;
		}
		return null;
	}

	private function buildUid(int $absenceId): string
	{
		$host = parse_url($this->urlGenerator->getAbsoluteURL('/'), PHP_URL_HOST) ?: 'nextcloud.local';

		return 'arbeitszeitcheck-absence-' . $absenceId . '@' . $host;
	}

	private function summaryForType(string $type): string
	{
		$labels = [
			Absence::TYPE_VACATION => $this->l10n->t('Vacation'),
			Absence::TYPE_SICK_LEAVE => $this->l10n->t('Sick Leave'),
			Absence::TYPE_PERSONAL_LEAVE => $this->l10n->t('Personal Leave'),
			Absence::TYPE_PARENTAL_LEAVE => $this->l10n->t('Parental Leave'),
			Absence::TYPE_SPECIAL_LEAVE => $this->l10n->t('Special Leave'),
			Absence::TYPE_UNPAID_LEAVE => $this->l10n->t('Unpaid Leave'),
			Absence::TYPE_HOME_OFFICE => $this->l10n->t('Home Office'),
			Absence::TYPE_BUSINESS_TRIP => $this->l10n->t('Business Trip'),
		];
		$label = $labels[$type] ?? $this->l10n->t('Absence');

		return $label . ' (' . $this->l10n->t('ArbeitszeitCheck') . ')';
	}

	private function buildAllDayIcs(string $uid, \DateTime $start, \DateTime $end, string $summary): string
	{
		$s = clone $start;
		$s->setTime(0, 0, 0);
		$e = clone $end;
		$e->setTime(0, 0, 0);
		// DTEND is exclusive: day after last day of absence
		$endExclusive = clone $e;
		$endExclusive->modify('+1 day');

		$ds = $s->format('Ymd');
		$de = $endExclusive->format('Ymd');
		$stamp = gmdate('Ymd\THis\Z', $this->timeFactory->getTime());

		return implode("\r\n", [
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//Software by Design//ArbeitszeitCheck//EN',
			'CALSCALE:GREGORIAN',
			'BEGIN:VEVENT',
			'UID:' . $this->escapeIcsText($uid),
			'DTSTAMP:' . $stamp,
			'DTSTART;VALUE=DATE:' . $ds,
			'DTEND;VALUE=DATE:' . $de,
			'SUMMARY:' . $this->escapeIcsText($summary),
			'TRANSP:OPAQUE',
			'STATUS:CONFIRMED',
			'END:VEVENT',
			'END:VCALENDAR',
		]) . "\r\n";
	}

	private function escapeIcsText(string $s): string
	{
		$s = str_replace(['\\', ',', ';', "\n", "\r"], ['\\\\', '\\,', '\\;', '\\n', ''], $s);

		return $s;
	}
}
