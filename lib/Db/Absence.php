<?php

declare(strict_types=1);

/**
 * Absence entity for the arbeitszeitcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Absence entity
 *
 * @method int getId()
 * @method void setId(int $id)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getType()
 * @method void setType(string $type)
 * @method \DateTime getStartDate()
 * @method void setStartDate(\DateTime $startDate)
 * @method \DateTime getEndDate()
 * @method void setEndDate(\DateTime $endDate)
 * @method float|null getDays()
 * @method void setDays(float|null $days)
 * @method string|null getReason()
 * @method void setReason(string|null $reason)
 * @method string getStatus()
 * @method void setStatus(string $status)
 * @method string|null getApproverComment()
 * @method void setApproverComment(string|null $approverComment)
 * @method int|null getApprovedBy()
 * @method void setApprovedBy(int|null $approvedBy)
 * @method \DateTime|null getApprovedAt()
 * @method void setApprovedAt(\DateTime|null $approvedAt)
 * @method \DateTime getCreatedAt()
 * @method void setCreatedAt(\DateTime $createdAt)
 * @method \DateTime getUpdatedAt()
 * @method void setUpdatedAt(\DateTime $updatedAt)
 */
class Absence extends Entity
{
	public const TYPE_VACATION = 'vacation';
	public const TYPE_SICK_LEAVE = 'sick_leave';
	public const TYPE_PERSONAL_LEAVE = 'personal_leave';
	public const TYPE_PARENTAL_LEAVE = 'parental_leave';
	public const TYPE_SPECIAL_LEAVE = 'special_leave';
	public const TYPE_UNPAID_LEAVE = 'unpaid_leave';
	public const TYPE_HOME_OFFICE = 'home_office';
	public const TYPE_BUSINESS_TRIP = 'business_trip';

	public const STATUS_PENDING = 'pending';
	public const STATUS_APPROVED = 'approved';
	public const STATUS_REJECTED = 'rejected';
	public const STATUS_CANCELLED = 'cancelled';

	/** @var string */
	protected $userId;

	/** @var string */
	protected $type;

	/** @var \DateTime */
	protected $startDate;

	/** @var \DateTime */
	protected $endDate;

	/** @var float|null */
	protected $days;

	/** @var string|null */
	protected $reason;

	/** @var string */
	protected $status;

	/** @var string|null */
	protected $approverComment;

	/** @var int|null */
	protected $approvedBy;

	/** @var \DateTime|null */
	protected $approvedAt;

	/** @var \DateTime */
	protected $createdAt;

	/** @var \DateTime */
	protected $updatedAt;

	/**
	 * Absence constructor
	 */
	public function __construct()
	{
		$this->addType('userId', 'string');
		$this->addType('type', 'string');
		$this->addType('startDate', 'date');
		$this->addType('endDate', 'date');
		$this->addType('days', 'float');
		$this->addType('reason', 'string');
		$this->addType('status', 'string');
		$this->addType('approverComment', 'string');
		$this->addType('approvedBy', 'integer');
		$this->addType('approvedAt', 'datetime');
		$this->addType('createdAt', 'datetime');
		$this->addType('updatedAt', 'datetime');
	}

	/**
	 * Calculate the number of working days for this absence
	 * Excludes weekends and German public holidays
	 *
	 * @return float
	 */
	public function calculateWorkingDays(): float
	{
		$start = clone $this->startDate;
		$end = clone $this->endDate;
		$workingDays = 0;

		// German public holidays (simplified - would need full implementation)
		$germanHolidays = $this->getGermanPublicHolidays((int)$start->format('Y'));

		while ($start <= $end) {
			// Skip weekends
			if ($start->format('N') < 6) { // Monday to Friday
				$dateString = $start->format('Y-m-d');
				// Skip public holidays
				if (!isset($germanHolidays[$dateString])) {
					$workingDays++;
				}
			}
			$start->modify('+1 day');
		}

		return $workingDays;
	}

	/**
	 * Get German public holidays for a given year (simplified)
	 * In production, this should use a proper holiday calculation library
	 *
	 * @param int $year
	 * @return array
	 */
	private function getGermanPublicHolidays(int $year): array
	{
		// This is a simplified implementation
		// In production, use a proper library like yasumi/yasumi
		$holidays = [];

		// New Year's Day
		$holidays[$year . '-01-01'] = 'New Year\'s Day';

		// Good Friday (simplified - actual calculation needed)
		$holidays[$year . '-04-07'] = 'Good Friday';

		// Easter Monday (simplified)
		$holidays[$year . '-04-10'] = 'Easter Monday';

		// Labour Day
		$holidays[$year . '-05-01'] = 'Labour Day';

		// Ascension Day (simplified)
		$holidays[$year . '-05-18'] = 'Ascension Day';

		// Whit Monday (simplified)
		$holidays[$year . '-05-29'] = 'Whit Monday';

		// Corpus Christi (simplified)
		$holidays[$year . '-06-08'] = 'Corpus Christi';

		// German Unity Day
		$holidays[$year . '-10-03'] = 'German Unity Day';

		// Reformation Day
		$holidays[$year . '-10-31'] = 'Reformation Day';

		// All Saints' Day
		$holidays[$year . '-11-01'] = 'All Saints\' Day';

		// Christmas Day
		$holidays[$year . '-12-25'] = 'Christmas Day';

		// Second Christmas Day
		$holidays[$year . '-12-26'] = 'Second Christmas Day';

		return $holidays;
	}

	/**
	 * Check if this absence overlaps with another absence
	 *
	 * @param Absence $other
	 * @return bool
	 */
	public function overlapsWith(Absence $other): bool
	{
		return $this->startDate <= $other->endDate && $this->endDate >= $other->startDate;
	}

	/**
	 * Check if absence is in the past
	 *
	 * @return bool
	 */
	public function isInPast(): bool
	{
		$today = new \DateTime();
		$today->setTime(0, 0, 0);
		return $this->endDate < $today;
	}

	/**
	 * Check if absence is currently active
	 *
	 * @return bool
	 */
	public function isActive(): bool
	{
		$today = new \DateTime();
		$today->setTime(0, 0, 0);
		return $this->startDate <= $today && $this->endDate >= $today && $this->status === self::STATUS_APPROVED;
	}

	/**
	 * Validate the absence data
	 *
	 * @return array Array of validation errors
	 */
	public function validate(): array
	{
		$errors = [];

		// Validate user ID
		if (empty($this->userId)) {
			$errors['userId'] = 'User ID is required';
		}

		// Validate type
		$validTypes = [
			self::TYPE_VACATION,
			self::TYPE_SICK_LEAVE,
			self::TYPE_PERSONAL_LEAVE,
			self::TYPE_PARENTAL_LEAVE,
			self::TYPE_SPECIAL_LEAVE,
			self::TYPE_UNPAID_LEAVE,
			self::TYPE_HOME_OFFICE,
			self::TYPE_BUSINESS_TRIP
		];
		if (!in_array($this->type, $validTypes)) {
			$errors['type'] = 'Invalid absence type';
		}

		// Validate dates
		if (!$this->startDate) {
			$errors['startDate'] = 'Start date is required';
		}

		if (!$this->endDate) {
			$errors['endDate'] = 'End date is required';
		}

		if ($this->startDate && $this->endDate && $this->startDate > $this->endDate) {
			$errors['endDate'] = 'End date must be after start date';
		}

		// Validate status
		$validStatuses = [
			self::STATUS_PENDING,
			self::STATUS_APPROVED,
			self::STATUS_REJECTED,
			self::STATUS_CANCELLED
		];
		if (!in_array($this->status, $validStatuses)) {
			$errors['status'] = 'Invalid status';
		}

		// Validate reason length
		if ($this->reason && strlen($this->reason) > 1000) {
			$errors['reason'] = 'Reason cannot exceed 1000 characters';
		}

		return $errors;
	}

	/**
	 * Check if the absence data is valid
	 *
	 * @return bool
	 */
	public function isValid(): bool
	{
		return empty($this->validate());
	}

	/**
	 * Get a summary array for API responses
	 *
	 * @return array
	 */
	public function getSummary(): array
	{
		$startDate = $this->getStartDate();
		$endDate = $this->getEndDate();
		$createdAt = $this->getCreatedAt();
		$updatedAt = $this->getUpdatedAt();
		
		return [
			'id' => $this->getId(),
			'userId' => $this->getUserId(),
			'type' => $this->getType(),
			'startDate' => $startDate ? $startDate->format('Y-m-d') : null,
			'endDate' => $endDate ? $endDate->format('Y-m-d') : null,
			'days' => $this->getDays(),
			'workingDays' => $this->calculateWorkingDays(),
			'reason' => $this->getReason(),
			'status' => $this->getStatus(),
			'approverComment' => $this->getApproverComment(),
			'approvedBy' => $this->getApprovedBy(),
			'approvedAt' => $this->getApprovedAt()?->format('c'),
			'createdAt' => $createdAt ? $createdAt->format('c') : null,
			'updatedAt' => $updatedAt ? $updatedAt->format('c') : null
		];
	}
}