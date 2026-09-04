<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service;

use DateTime;
use DateTimeZone;
use OCA\ArbeitszeitCheck\Db\Absence;
use OCA\ArbeitszeitCheck\Util\Rfc5545Ics;
use PHPUnit\Framework\TestCase;

final class OutlookIcalSubscriptionFeedServiceTest extends TestCase
{
	use OutlookIcalFeedServiceTestTrait;

	private function buildAbsence(
		int $id,
		string $userId,
		string $type,
		string $startYmd,
		string $endYmd,
		?float $days,
		string $status,
		string $reason,
		string $updatedAtYmdHisUtc,
	): Absence {
		$absence = new Absence();
		$absence->setId($id);
		$absence->setUserId($userId);
		$absence->setType($type);
		$absence->setStartDate(new DateTime($startYmd, new DateTimeZone('UTC')));
		$absence->setEndDate(new DateTime($endYmd, new DateTimeZone('UTC')));
		$absence->setDays($days);
		$absence->setStatus($status);
		$absence->setReason($reason);
		$absence->setUpdatedAt(new DateTime($updatedAtYmdHisUtc, new DateTimeZone('UTC')));
		return $absence;
	}

	public function testBuildFeedEscapesTextMapsSickLeaveAndHalfDayAndProtectsDescriptionPrivacy(): void
	{
		$service = $this->makeFeedService();

		$tenantDomain = 'example.com;evil';

		$displayName = "Max, Mustermann;Test\\New\nLine";
		$expectedDisplayEscaped = Rfc5545Ics::escapeText($displayName);

		$absence = $this->buildAbsence(
			1,
			'u1',
			Absence::TYPE_SICK_LEAVE,
			'2026-01-01',
			'2026-01-01',
			0.5,
			Absence::STATUS_APPROVED,
			'REASON_SECRET_SHOULD_NEVER_APPEAR',
			'2026-01-03 04:05:06'
		);

		$summaryRaw = $displayName . ' (Absence, Half day)';
		$expectedSummaryEscaped = Rfc5545Ics::escapeText($summaryRaw);

		$feed = $service->buildFeed(
			$tenantDomain,
			[$absence],
			['u1' => $displayName]
		);

		self::assertStringContainsString('BEGIN:VCALENDAR', $feed);
		self::assertStringContainsString('BEGIN:VEVENT', $feed);
		self::assertStringContainsString('X-WR-CALNAME:Approved team absences', $feed);
		self::assertStringContainsString('NAME:Approved team absences', $feed);
		self::assertStringContainsString('DTSTART;VALUE=DATE:20260101', $feed);
		self::assertStringContainsString('DTEND;VALUE=DATE:20260102', $feed);
		self::assertStringContainsString('UID:arbeitszeitcheck-absence-1@example.com\;evil', $feed);
		self::assertStringContainsString('DTSTAMP:20260103T040506Z', $feed);
		self::assertStringContainsString('SUMMARY:' . $expectedSummaryEscaped, $feed);
		self::assertStringContainsString('LOCATION:' . $expectedDisplayEscaped, $feed);
		self::assertStringContainsString('DESCRIPTION:' . $expectedSummaryEscaped, $feed);
		self::assertStringNotContainsString('SUMMARY:Absence', $feed);
		self::assertStringNotContainsString('Sick Leave', $feed);
		self::assertStringNotContainsString('REASON_SECRET_SHOULD_NEVER_APPEAR', $feed);
	}

	public function testBuildFeedUsesLocalizedCalendarNameWhenLanguageProvided(): void
	{
		$service = $this->makeFeedService([
			'Approved team absences' => 'Genehmigte Team-Abwesenheiten',
		], 'de');

		$feed = $service->buildFeed('example.com', [], [], 'de');

		self::assertStringContainsString('X-WR-CALNAME:Genehmigte Team-Abwesenheiten', $feed);
		self::assertStringContainsString('NAME:Genehmigte Team-Abwesenheiten', $feed);
	}

	public function testBuildFeedUsesLocalizedLabelsWhenLanguageProvided(): void
	{
		$service = $this->makeFeedService([
			'Absence' => 'Abwesenheit',
			'Half day' => 'Halbtag',
		], 'de');

		$absence = $this->buildAbsence(
			3,
			'u2',
			Absence::TYPE_SICK_LEAVE,
			'2026-02-01',
			'2026-02-01',
			0.5,
			Absence::STATUS_APPROVED,
			'secret',
			'2026-02-01 12:00:00'
		);

		$feed = $service->buildFeed(
			'example.com',
			[$absence],
			['u2' => 'Anna Schmidt'],
			'de',
		);

		self::assertStringContainsString('SUMMARY:' . Rfc5545Ics::escapeText('Anna Schmidt (Abwesenheit, Halbtag)'), $feed);
		self::assertStringContainsString('LOCATION:Anna Schmidt', $feed);
		self::assertStringNotContainsString('SUMMARY:Absence', $feed);
	}

	public function testBuildFeedIncludesEmployeeDisplayNameBeforeTypeLabel(): void
	{
		$service = $this->makeFeedService([], 'en');

		$absence = $this->buildAbsence(
			4,
			'emp1',
			Absence::TYPE_VACATION,
			'2026-03-10',
			'2026-03-14',
			5.0,
			Absence::STATUS_APPROVED,
			'ignored',
			'2026-03-01 08:00:00'
		);

		$feed = $service->buildFeed(
			'example.com',
			[$absence],
			['emp1' => 'Taylor Example'],
		);

		self::assertStringContainsString('SUMMARY:Taylor Example (Vacation)', $feed);
		self::assertStringNotContainsString(': Vacation', $feed);
	}

	public function testBuildFeedOrdersEventsDeterministicallyByStartDateAndUid(): void
	{
		$service = $this->makeFeedService();

		$tenantDomain = 'example.com';

		$absenceA = $this->buildAbsence(
			2,
			'u1',
			Absence::TYPE_VACATION,
			'2026-01-02',
			'2026-01-02',
			0.5,
			Absence::STATUS_APPROVED,
			'ignored',
			'2026-01-03 04:05:06'
		);

		$absenceB = $this->buildAbsence(
			1,
			'u1',
			Absence::TYPE_VACATION,
			'2026-01-01',
			'2026-01-01',
			1.0,
			Absence::STATUS_APPROVED,
			'ignored',
			'2026-01-03 04:05:06'
		);

		$feed = $service->buildFeed(
			$tenantDomain,
			[$absenceA, $absenceB],
			['u1' => 'u1']
		);

		$uid1 = 'UID:arbeitszeitcheck-absence-1@example.com';
		$uid2 = 'UID:arbeitszeitcheck-absence-2@example.com';

		$pos1 = strpos($feed, $uid1);
		$pos2 = strpos($feed, $uid2);

		self::assertIsInt($pos1);
		self::assertIsInt($pos2);
		self::assertLessThan($pos2, $pos1, 'Event with earlier start date must appear first');
	}
}
