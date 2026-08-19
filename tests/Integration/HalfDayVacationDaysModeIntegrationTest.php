<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Integration;

use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Db\Absence;
use OCA\ArbeitszeitCheck\Service\AbsenceService;
use OCA\ArbeitszeitCheck\Service\VacationAllocationService;
use OCA\ArbeitszeitCheck\Service\VacationUnitService;
use OCP\IConfig;
use OCP\IUserManager;
use OCP\IUserSession;
use Test\TestCase;

/**
 * AC-G1 / AC-G5b / AC-G7 / AC-G12 — days-mode half-day against real DB + allocation.
 *
 * @group integration
 */
final class HalfDayVacationDaysModeIntegrationTest extends TestCase
{
	private string $uid = '';
	private string $managerUid = '';
	private ?string $prevUnit = null;
	private ?string $prevYearMode = null;

	protected function setUp(): void
	{
		if (!class_exists(\OC::class) || !isset(\OC::$server)) {
			$this->markTestSkipped('Nextcloud runtime required');
		}

		$config = \OC::$server->get(IConfig::class);
		$this->prevUnit = $config->getAppValue('arbeitszeitcheck', Constants::CONFIG_VACATION_UNIT, Constants::VACATION_UNIT_DAYS);
		$this->prevYearMode = $config->getAppValue('arbeitszeitcheck', Constants::CONFIG_VACATION_YEAR_MODE, Constants::VACATION_YEAR_MODE_CALENDAR);
		$config->setAppValue('arbeitszeitcheck', Constants::CONFIG_VACATION_UNIT, Constants::VACATION_UNIT_DAYS);
		$config->setAppValue('arbeitszeitcheck', Constants::CONFIG_VACATION_YEAR_MODE, Constants::VACATION_YEAR_MODE_CALENDAR);

		$unit = \OC::$server->get(VacationUnitService::class);
		if (!$unit->isDaysMode()) {
			$this->markTestSkipped('Could not force vacation_unit=days for integration run');
		}

		$um = \OC::$server->get(IUserManager::class);
		$this->uid = 'azc_half_' . bin2hex(random_bytes(3));
		$this->managerUid = 'azc_mgr_' . bin2hex(random_bytes(3));
		foreach ([$this->uid, $this->managerUid] as $id) {
			if ($um->userExists($id)) {
				$um->get($id)?->delete();
			}
			$um->createUser($id, 'Azc-Half-' . bin2hex(random_bytes(4)) . '!');
		}
	}

	protected function tearDown(): void
	{
		if (!isset(\OC::$server)) {
			return;
		}
		try {
			\OC::$server->get(IUserSession::class)->setUser(null);
		} catch (\Throwable) {
		}
		$um = \OC::$server->get(IUserManager::class);
		foreach ([$this->uid, $this->managerUid] as $id) {
			if ($id === '') {
				continue;
			}
			try {
				$um->get($id)?->delete();
			} catch (\Throwable) {
			}
		}
		if ($this->prevUnit !== null) {
			try {
				\OC::$server->get(IConfig::class)->setAppValue(
					'arbeitszeitcheck',
					Constants::CONFIG_VACATION_UNIT,
					$this->prevUnit
				);
			} catch (\Throwable) {
			}
		}
		if ($this->prevYearMode !== null) {
			try {
				\OC::$server->get(IConfig::class)->setAppValue(
					'arbeitszeitcheck',
					Constants::CONFIG_VACATION_YEAR_MODE,
					$this->prevYearMode
				);
			} catch (\Throwable) {
			}
		}
	}

	public function testCreateApproveHalfDayReducesRemainingByHalf(): void
	{
		$user = \OC::$server->get(IUserManager::class)->get($this->uid);
		$this->assertNotNull($user);
		\OC::$server->get(IUserSession::class)->setUser($user);

		$absenceService = \OC::$server->get(AbsenceService::class);
		$alloc = \OC::$server->get(VacationAllocationService::class);

		// Pick a weekday at least 14 days ahead to avoid month-closure / past edges.
		$day = new \DateTimeImmutable('tomorrow');
		while ((int)$day->format('N') > 5) {
			$day = $day->modify('+1 day');
		}
		$day = $day->modify('+14 days');
		while ((int)$day->format('N') > 5) {
			$day = $day->modify('+1 day');
		}
		$ymd = $day->format('Y-m-d');
		$year = (int)$day->format('Y');

		$before = $alloc->computeYearAllocation(
			$this->uid,
			$year,
			null,
			null,
			null,
			\DateTime::createFromImmutable($day),
			null,
			false
		);
		$remainingBefore = (float)$before['total_remaining_for_new_requests'];
		if ($remainingBefore < 0.5) {
			$this->markTestSkipped('Employee entitlement remaining < 0.5 — cannot exercise half-day debit');
		}

		$row = $absenceService->createAbsence([
			'type' => Absence::TYPE_VACATION,
			'start_date' => $ymd,
			'end_date' => $ymd,
			'day_fraction' => '0.5',
			'reason' => 'Half-day integration ' . bin2hex(random_bytes(2)),
		], $this->uid);

		$this->assertGreaterThan(0, (int)$row->getId());
		$this->assertEqualsWithDelta(0.5, (float)$row->getDays(), 0.011, 'AC-G1 persist days=0.5');

		if ($row->getStatus() !== Absence::STATUS_APPROVED) {
			$approved = $absenceService->approveAbsence((int)$row->getId(), $this->managerUid, 'integration approve');
			$this->assertSame(Absence::STATUS_APPROVED, $approved->getStatus());
			$this->assertEqualsWithDelta(0.5, (float)$approved->getDays(), 0.011);
		}

		$after = $alloc->computeYearAllocation(
			$this->uid,
			$year,
			null,
			null,
			null,
			\DateTime::createFromImmutable($day),
			null,
			false
		);
		$delta = $remainingBefore - (float)$after['total_remaining_for_new_requests'];
		$this->assertEqualsWithDelta(0.5, $delta, 0.02, 'AC-G5b remaining decreases by 0.5 not 1.0');

		// AC-G7: cannot stack a second half on the same day.
		$overlapThrown = false;
		try {
			$absenceService->createAbsence([
				'type' => Absence::TYPE_VACATION,
				'start_date' => $ymd,
				'end_date' => $ymd,
				'day_fraction' => '0.5',
				'reason' => 'overlap attempt',
			], $this->uid);
		} catch (\Throwable $e) {
			$overlapThrown = true;
			$this->assertStringContainsStringIgnoringCase('overlap', $e->getMessage());
		}
		$this->assertTrue($overlapThrown, 'Second half on same day must be rejected');
	}

	public function testManagerRecordedHalfDayApproved(): void
	{
		$absenceService = \OC::$server->get(AbsenceService::class);
		$day = new \DateTimeImmutable('tomorrow');
		while ((int)$day->format('N') > 5) {
			$day = $day->modify('+1 day');
		}
		$day = $day->modify('+21 days');
		while ((int)$day->format('N') > 5) {
			$day = $day->modify('+1 day');
		}
		$ymd = $day->format('Y-m-d');

		$row = $absenceService->createApprovedAbsenceForEmployeeByManager($this->managerUid, $this->uid, [
			'type' => Absence::TYPE_VACATION,
			'start_date' => $ymd,
			'end_date' => $ymd,
			'day_fraction' => '0.5',
			'reason' => 'Manager half ' . bin2hex(random_bytes(2)),
		]);
		$this->assertSame(Absence::STATUS_APPROVED, $row->getStatus());
		$this->assertEqualsWithDelta(0.5, (float)$row->getDays(), 0.011);
	}

	public function testHalfDayRangeForbiddenAgainstLiveService(): void
	{
		$absenceService = \OC::$server->get(AbsenceService::class);
		$start = (new \DateTimeImmutable('monday next week'))->modify('+28 days');
		$end = $start->modify('+2 days');
		$this->expectException(\Throwable::class);
		$absenceService->createAbsence([
			'type' => Absence::TYPE_VACATION,
			'start_date' => $start->format('Y-m-d'),
			'end_date' => $end->format('Y-m-d'),
			'day_fraction' => '0.5',
		], $this->uid);
	}
}
