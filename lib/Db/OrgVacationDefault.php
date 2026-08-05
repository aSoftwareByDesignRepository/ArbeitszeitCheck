<?php

declare(strict_types=1);

/**
 * L0 organisation-wide vacation entitlement default.
 *
 * One *logical* active row per validity slot (`effective_from` … `effective_to`).
 * The service layer ({@see \OCA\ArbeitszeitCheck\Service\OrgVacationDefaultService})
 * enforces "at most one open-ended row" via a transactional close-prior-row
 * pattern; the schema cannot enforce this portably across MariaDB / PostgreSQL
 * / SQLite (partial unique indexes are PG-only).
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
 * @method string|null getVacationMode()
 * @method void setVacationMode(string $vacationMode)
 * @method float|null getManualDays()
 * @method void setManualDays(?float $manualDays)
 * @method int|null getTariffRuleSetId()
 * @method void setTariffRuleSetId(?int $tariffRuleSetId)
 * @method string|null getDescription()
 * @method void setDescription(?string $description)
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
class OrgVacationDefault extends Entity
{
	// IMPORTANT: do NOT initialise these properties with default values.
	// Nextcloud's `Entity::setter()` short-circuits when the incoming value
	// already equals the current property value (`$args[0] === $this->$name`).
	// If we pre-initialise `$vacationMode = 'manual_fixed'` here, calling
	// `setVacationMode('manual_fixed')` from the service layer would silently
	// fail to mark the field dirty and `vacation_mode` would be omitted from
	// the INSERT — yielding `NOT NULL` constraint violations on MariaDB and
	// silent column drift on SQLite.
	protected ?string $vacationMode = null;
	protected ?float $manualDays = null;
	protected ?int $tariffRuleSetId = null;
	protected ?string $description = null;
	protected ?\DateTime $effectiveFrom = null;
	protected ?\DateTime $effectiveTo = null;
	protected ?int $version = null;
	protected ?string $createdBy = null;
	protected ?\DateTime $createdAt = null;
	protected ?\DateTime $updatedAt = null;

	public function __construct()
	{
		$this->addType('vacationMode', 'string');
		$this->addType('manualDays', 'float');
		$this->addType('tariffRuleSetId', 'integer');
		$this->addType('description', 'string');
		$this->addType('effectiveFrom', 'date');
		$this->addType('effectiveTo', 'date');
		$this->addType('version', 'integer');
		$this->addType('createdBy', 'string');
		$this->addType('createdAt', 'datetime');
		$this->addType('updatedAt', 'datetime');
	}

	/**
	 * Field-level validation. Returns ['field' => 'error message'] map; empty
	 * array means "no errors". Errors are short English keys; translation
	 * happens at the controller boundary so we keep this entity reusable in
	 * background jobs that have no `IL10N` context.
	 *
	 * @return array<string, string>
	 */
	public function validate(): array
	{
		$errors = [];
		$validModes = [
			Constants::VACATION_MODE_MANUAL_FIXED,
			Constants::VACATION_MODE_MODEL_BASED_SIMPLE,
			Constants::VACATION_MODE_TARIFF_RULE_BASED,
		];
		if (!in_array($this->vacationMode, $validModes, true)) {
			$errors['vacationMode'] = 'Invalid vacation mode for organisation default';
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
		return $errors;
	}

	public function getSummary(): array
	{
		return [
			'id' => $this->getId(),
			'vacationMode' => $this->getVacationMode(),
			'manualDays' => $this->getManualDays(),
			'tariffRuleSetId' => $this->getTariffRuleSetId(),
			'description' => $this->getDescription(),
			'effectiveFrom' => $this->getEffectiveFrom()?->format('Y-m-d'),
			'effectiveTo' => $this->getEffectiveTo()?->format('Y-m-d'),
			'version' => $this->getVersion(),
			'createdBy' => $this->getCreatedBy(),
			'createdAt' => $this->getCreatedAt()?->format('c'),
			'updatedAt' => $this->getUpdatedAt()?->format('c'),
		];
	}
}
