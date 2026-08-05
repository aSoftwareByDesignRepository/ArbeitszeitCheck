<?php

declare(strict_types=1);

/**
 * L2 team / cohort vacation policy.
 *
 * Attached to an app `Team` (`at_teams`). A user "matches" this policy at
 * resolution time iff they are a direct member of `team_id` **or** a member
 * of any descendant team (deepest match wins). Tie-breaking is deterministic
 * across the matrix:
 *
 *  1. Deeper subtree wins (most-specific cohort).
 *  2. On equal depth: higher `priority` wins.
 *  3. On equal depth + equal priority: smaller `team_id` wins (stable
 *     iteration order, reproducible by auditors).
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Db;

use OCA\ArbeitszeitCheck\Constants;
use OCP\AppFramework\Db\Entity;

/**
 * @method int getId()
 * @method void setId(int $id)
 * @method int|null getTeamId()
 * @method void setTeamId(int $teamId)
 * @method string|null getVacationMode()
 * @method void setVacationMode(string $vacationMode)
 * @method float|null getManualDays()
 * @method void setManualDays(?float $manualDays)
 * @method int|null getTariffRuleSetId()
 * @method void setTariffRuleSetId(?int $tariffRuleSetId)
 * @method string|null getDescription()
 * @method void setDescription(?string $description)
 * @method int|null getPriority()
 * @method void setPriority(int $priority)
 * @method \DateTime|null getEffectiveFrom()
 * @method void setEffectiveFrom(?\DateTime $effectiveFrom)
 * @method \DateTime|null getEffectiveTo()
 * @method void setEffectiveTo(?\DateTime $effectiveTo)
 * @method int|null getVersion()
 * @method void setVersion(int $version)
 * @method string|null getCreatedBy()
 * @method void setCreatedBy(string $createdBy)
 * @method \DateTime|null getCreatedAt()
 * @method void setCreatedAt(?\DateTime $createdAt)
 * @method \DateTime|null getUpdatedAt()
 * @method void setUpdatedAt(?\DateTime $updatedAt)
 */
class TeamVacationPolicy extends Entity
{
	// NOTE: properties are intentionally nullable without initial values —
	// see {@see OrgVacationDefault} for the full rationale. Nextcloud's
	// Entity::setter short-circuits on equal values, so a pre-initialised
	// default would silently skip the dirty-mark and the column would be
	// missing from the INSERT.
	protected ?int $teamId = null;
	protected ?string $vacationMode = null;
	protected ?float $manualDays = null;
	protected ?int $tariffRuleSetId = null;
	protected ?string $description = null;
	protected ?int $priority = null;
	protected ?\DateTime $effectiveFrom = null;
	protected ?\DateTime $effectiveTo = null;
	protected ?int $version = null;
	protected ?string $createdBy = null;
	protected ?\DateTime $createdAt = null;
	protected ?\DateTime $updatedAt = null;

	public function __construct()
	{
		$this->addType('teamId', 'integer');
		$this->addType('vacationMode', 'string');
		$this->addType('manualDays', 'float');
		$this->addType('tariffRuleSetId', 'integer');
		$this->addType('description', 'string');
		$this->addType('priority', 'integer');
		$this->addType('effectiveFrom', 'date');
		$this->addType('effectiveTo', 'date');
		$this->addType('version', 'integer');
		$this->addType('createdBy', 'string');
		$this->addType('createdAt', 'datetime');
		$this->addType('updatedAt', 'datetime');
	}

	/**
	 * @return array<string, string>
	 */
	public function validate(): array
	{
		$errors = [];
		if ($this->teamId === null || $this->teamId <= 0) {
			$errors['teamId'] = 'Team is required';
		}
		$validModes = [
			Constants::VACATION_MODE_MANUAL_FIXED,
			Constants::VACATION_MODE_MODEL_BASED_SIMPLE,
			Constants::VACATION_MODE_TARIFF_RULE_BASED,
		];
		if (!in_array($this->vacationMode, $validModes, true)) {
			$errors['vacationMode'] = 'Invalid vacation mode for team policy';
		}
		if ($this->vacationMode === Constants::VACATION_MODE_MANUAL_FIXED && $this->manualDays === null) {
			$errors['manualDays'] = 'Manual days are required for manual mode';
		}
		if ($this->vacationMode === Constants::VACATION_MODE_MANUAL_FIXED && $this->tariffRuleSetId !== null) {
			$errors['tariffRuleSetId'] = 'Tariff rule set must be empty for manual fixed mode';
		}
		if ($this->vacationMode === Constants::VACATION_MODE_MODEL_BASED_SIMPLE) {
			if ($this->manualDays !== null) {
				$errors['manualDays'] = 'Manual days must be empty for this vacation mode';
			}
			if ($this->tariffRuleSetId !== null) {
				$errors['tariffRuleSetId'] = 'Tariff rule set must be empty for this vacation mode';
			}
		}
		if ($this->vacationMode === Constants::VACATION_MODE_TARIFF_RULE_BASED && $this->manualDays !== null) {
			$errors['manualDays'] = 'Manual days must be empty for this vacation mode';
		}
		if ($this->vacationMode === Constants::VACATION_MODE_TARIFF_RULE_BASED && $this->tariffRuleSetId === null) {
			$errors['tariffRuleSetId'] = 'Tariff rule set is required for tariff mode';
		}
		if ($this->manualDays !== null && ($this->manualDays < 0.0 || $this->manualDays > 4000.0)) {
			$errors['manualDays'] = 'Manual amount must be between 0 and 4000';
		}
		if ($this->effectiveFrom === null) {
			$errors['effectiveFrom'] = 'Effective from date is required';
		}
		if ($this->effectiveTo !== null && $this->effectiveFrom !== null && $this->effectiveTo < $this->effectiveFrom) {
			$errors['effectiveTo'] = 'Effective to date must be on or after effective from date';
		}
		if ($this->priority !== null && ($this->priority < -1000 || $this->priority > 1000)) {
			$errors['priority'] = 'Priority must be between -1000 and 1000';
		}
		return $errors;
	}

	public function getSummary(): array
	{
		return [
			'id' => $this->getId(),
			'teamId' => $this->getTeamId(),
			'vacationMode' => $this->getVacationMode(),
			'manualDays' => $this->getManualDays(),
			'tariffRuleSetId' => $this->getTariffRuleSetId(),
			'description' => $this->getDescription(),
			'priority' => $this->getPriority(),
			'effectiveFrom' => $this->getEffectiveFrom()?->format('Y-m-d'),
			'effectiveTo' => $this->getEffectiveTo()?->format('Y-m-d'),
			'version' => $this->getVersion(),
			'createdBy' => $this->getCreatedBy(),
			'createdAt' => $this->getCreatedAt()?->format('c'),
			'updatedAt' => $this->getUpdatedAt()?->format('c'),
		];
	}
}
