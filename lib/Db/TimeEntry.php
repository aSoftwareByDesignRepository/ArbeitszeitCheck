<?php

declare(strict_types=1);

/**
 * TimeEntry entity for the arbeitszeitcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Db;

use OCP\AppFramework\Db\Entity;

/**
 * TimeEntry entity
 *
 * @method int getId()
 * @method void setId(int $id)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method \DateTime getStartTime()
 * @method void setStartTime(\DateTime $startTime)
 * @method \DateTime|null getEndTime()
 * @method void setEndTime(\DateTime|null $endTime)
 * @method \DateTime|null getBreakStartTime()
 * @method void setBreakStartTime(\DateTime|null $breakStartTime)
 * @method \DateTime|null getBreakEndTime()
 * @method void setBreakEndTime(\DateTime|null $breakEndTime)
 * @method string|null getDescription()
 * @method void setDescription(string|null $description)
 * @method string|null getProjectCheckProjectId()
 * @method void setProjectCheckProjectId(string|null $projectCheckProjectId)
 * @method string getStatus()
 * @method void setStatus(string $status)
 * @method bool getIsManualEntry()
 * @method void setIsManualEntry(bool $isManualEntry)
 * @method string|null getJustification()
 * @method void setJustification(string|null $justification)
 * @method \DateTime getCreatedAt()
 * @method void setCreatedAt(\DateTime $createdAt)
 * @method \DateTime getUpdatedAt()
 * @method void setUpdatedAt(\DateTime $updatedAt)
 * @method int|null getApprovedBy()
 * @method void setApprovedBy(int|null $approvedBy)
 * @method \DateTime|null getApprovedAt()
 * @method void setApprovedAt(\DateTime|null $approvedAt)
 */
class TimeEntry extends Entity
{
	public const STATUS_ACTIVE = 'active';
	public const STATUS_COMPLETED = 'completed';
	public const STATUS_BREAK = 'break';
	public const STATUS_PENDING_APPROVAL = 'pending_approval';
	public const STATUS_REJECTED = 'rejected';

	/** @var string */
	protected $userId;

	/** @var \DateTime */
	protected $startTime;

	/** @var \DateTime|null */
	protected $endTime;

	/** @var \DateTime|null */
	protected $breakStartTime;

	/** @var \DateTime|null */
	protected $breakEndTime;

	/** @var string|null */
	protected $description;

	/** @var string|null */
	protected $projectCheckProjectId;

	/** @var string */
	protected $status;

	/** @var bool */
	protected $isManualEntry = false;

	/** @var string|null */
	protected $justification;

	/** @var \DateTime */
	protected $createdAt;

	/** @var \DateTime */
	protected $updatedAt;

	/** @var int|null */
	protected $approvedBy;

	/** @var \DateTime|null */
	protected $approvedAt;

	/**
	 * TimeEntry constructor
	 */
	public function __construct()
	{
		$this->addType('userId', 'string');
		$this->addType('startTime', 'datetime');
		$this->addType('endTime', 'datetime');
		$this->addType('breakStartTime', 'datetime');
		$this->addType('breakEndTime', 'datetime');
		$this->addType('description', 'string');
		$this->addType('projectCheckProjectId', 'string');
		$this->addType('status', 'string');
		$this->addType('isManualEntry', 'boolean');
		$this->addType('justification', 'string');
		$this->addType('createdAt', 'datetime');
		$this->addType('updatedAt', 'datetime');
		$this->addType('approvedBy', 'integer');
		$this->addType('approvedAt', 'datetime');
	}

	/**
	 * Get the duration in hours
	 *
	 * @return float|null
	 */
	public function getDurationHours(): ?float
	{
		if (!$this->endTime) {
			return null;
		}

		$start = $this->startTime;
		$end = $this->endTime;

		// Calculate break duration
		$breakDuration = 0;
		if ($this->breakStartTime && $this->breakEndTime) {
			$breakDuration = ($this->breakEndTime->getTimestamp() - $this->breakStartTime->getTimestamp()) / 3600;
		}

		$totalDuration = ($end->getTimestamp() - $start->getTimestamp()) / 3600;
		return max(0, $totalDuration - $breakDuration);
	}

	/**
	 * Get the break duration in hours
	 *
	 * @return float
	 */
	public function getBreakDurationHours(): float
	{
		if (!$this->breakStartTime || !$this->breakEndTime) {
			return 0.0;
		}

		return ($this->breakEndTime->getTimestamp() - $this->breakStartTime->getTimestamp()) / 3600;
	}

	/**
	 * Get the working duration in hours (excluding breaks)
	 *
	 * @return float|null
	 */
	public function getWorkingDurationHours(): ?float
	{
		return $this->getDurationHours();
	}

	/**
	 * Check if this entry is currently active
	 *
	 * @return bool
	 */
	public function isActive(): bool
	{
		return $this->status === self::STATUS_ACTIVE;
	}

	/**
	 * Check if this entry is on break
	 *
	 * @return bool
	 */
	public function isOnBreak(): bool
	{
		return $this->status === self::STATUS_BREAK;
	}

	/**
	 * Check if this entry is completed
	 *
	 * @return bool
	 */
	public function isCompleted(): bool
	{
		return $this->status === self::STATUS_COMPLETED;
	}

	/**
	 * Validate the time entry data
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

		// Validate start time
		if (!$this->startTime) {
			$errors['startTime'] = 'Start time is required';
		}

		// Validate status
		$validStatuses = [
			self::STATUS_ACTIVE,
			self::STATUS_COMPLETED,
			self::STATUS_BREAK,
			self::STATUS_PENDING_APPROVAL,
			self::STATUS_REJECTED
		];
		if (!in_array($this->status, $validStatuses)) {
			$errors['status'] = 'Invalid status';
		}

		// Validate end time is after start time
		if ($this->endTime && $this->startTime && $this->endTime <= $this->startTime) {
			$errors['endTime'] = 'End time must be after start time';
		}

		// Validate break times
		if ($this->breakStartTime && $this->breakEndTime) {
			if ($this->breakEndTime <= $this->breakStartTime) {
				$errors['breakEndTime'] = 'Break end time must be after break start time';
			}
			if ($this->startTime && $this->breakStartTime < $this->startTime) {
				$errors['breakStartTime'] = 'Break start time cannot be before work start time';
			}
			if ($this->endTime && $this->breakEndTime > $this->endTime) {
				$errors['breakEndTime'] = 'Break end time cannot be after work end time';
			}
		}

		// Validate description length
		if ($this->description && strlen($this->description) > 1000) {
			$errors['description'] = 'Description cannot exceed 1000 characters';
		}

		// Validate justification for manual entries
		if ($this->isManualEntry && empty($this->justification)) {
			$errors['justification'] = 'Justification is required for manual time entries';
		}

		return $errors;
	}

	/**
	 * Check if the time entry data is valid
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
		$startTime = $this->getStartTime();
		$createdAt = $this->getCreatedAt();
		$updatedAt = $this->getUpdatedAt();
		
		return [
			'id' => $this->getId(),
			'userId' => $this->getUserId(),
			'startTime' => $startTime ? $startTime->format('c') : null,
			'endTime' => $this->getEndTime()?->format('c'),
			'breakStartTime' => $this->getBreakStartTime()?->format('c'),
			'breakEndTime' => $this->getBreakEndTime()?->format('c'),
			'durationHours' => $this->getDurationHours(),
			'breakDurationHours' => $this->getBreakDurationHours(),
			'workingDurationHours' => $this->getWorkingDurationHours(),
			'description' => $this->getDescription(),
			'projectCheckProjectId' => $this->getProjectCheckProjectId(),
			'status' => $this->getStatus(),
			'isManualEntry' => $this->getIsManualEntry(),
			'justification' => $this->getJustification(),
			'createdAt' => $createdAt ? $createdAt->format('c') : null,
			'updatedAt' => $updatedAt ? $updatedAt->format('c') : null,
			'approvedBy' => $this->getApprovedBy(),
			'approvedAt' => $this->getApprovedAt()?->format('c')
		];
	}
}