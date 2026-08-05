<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Db;

use OCA\ArbeitszeitCheck\Constants;
use OCP\AppFramework\Db\Entity;

/**
 * @method int getId()
 * @method void setId(int $id)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getVacationMode()
 * @method void setVacationMode(string $vacationMode)
 * @method float|null getManualDays()
 * @method void setManualDays(?float $manualDays)
 * @method int|null getTariffRuleSetId()
 * @method void setTariffRuleSetId(?int $tariffRuleSetId)
 * @method string|null getOverrideReason()
 * @method void setOverrideReason(?string $overrideReason)
 * @method \DateTime|null getEffectiveFrom()
 * @method void setEffectiveFrom(?\DateTime $effectiveFrom)
 * @method \DateTime|null getEffectiveTo()
 * @method void setEffectiveTo(?\DateTime $effectiveTo)
 * @method string getCreatedBy()
 * @method void setCreatedBy(string $createdBy)
 * @method \DateTime|null getCreatedAt()
 * @method void setCreatedAt(?\DateTime $createdAt)
 * @method \DateTime|null getUpdatedAt()
 * @method void setUpdatedAt(?\DateTime $updatedAt)
 * @method bool getInheritLowerLayers()
 * @method void setInheritLowerLayers(bool $inheritLowerLayers)
 */
class UserVacationPolicyAssignment extends Entity {
	// Do not default vacationMode (or other NOT NULL columns) here — Entity::setter()
	// skips dirty marking when the new value equals the PHP default, which omits
	// columns from INSERT and breaks on strict SQL modes (see Developer-Documentation).
	protected string $userId = '';
	/** @var string|null */
	protected ?string $vacationMode = null;
	protected ?float $manualDays = null;
	protected ?int $tariffRuleSetId = null;
	protected ?string $overrideReason = null;
	protected ?\DateTime $effectiveFrom = null;
	protected ?\DateTime $effectiveTo = null;
	protected string $createdBy = 'system';
	protected ?\DateTime $createdAt = null;
	protected ?\DateTime $updatedAt = null;
	/** @var bool|null DB column is nullable for Nextcloud schema portability; NULL is treated as false. */
	protected $inheritLowerLayers = false;

	public function __construct() {
		$this->addType('userId', 'string');
		$this->addType('vacationMode', 'string');
		$this->addType('manualDays', 'float');
		$this->addType('tariffRuleSetId', 'integer');
		$this->addType('overrideReason', 'string');
		$this->addType('effectiveFrom', 'date');
		$this->addType('effectiveTo', 'date');
		$this->addType('createdBy', 'string');
		$this->addType('createdAt', 'datetime');
		$this->addType('updatedAt', 'datetime');
		$this->addType('inheritLowerLayers', 'boolean');
	}

	/**
	 * {@see Entity::setter} maps DB rows onto properties. PHP's `settype($v, 'bool')`
	 * treats any non-empty string (including literal "false") as true — unsafe for
	 * a payroll-relevant flag. Normalise textual booleans before casting.
	 *
	 * @param array{0: mixed} $args
	 */
	protected function setter(string $name, array $args): void {
		if ($name === 'inheritLowerLayers' && isset($args[0]) && $args[0] !== null && is_string($args[0])) {
			$parsed = filter_var($args[0], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
			$args[0] = $parsed ?? false;
		}
		parent::setter($name, $args);
	}

	/**
	 * True iff this L3 row defers entitlement resolution to lower layers
	 * (L2 team → L1 model → L0 organisation). Accepts both representations
	 * tolerated by the API: the explicit boolean column **and** the sentinel
	 * `vacation_mode = 'inherit'`. Either is sufficient.
	 */
	public function isInherit(): bool {
		// Strict checks: avoid PHP truthiness traps on corrupted string values in
		// the DB (e.g. a literal "false" string would be truthy).
		if ($this->inheritLowerLayers === true || $this->inheritLowerLayers === 1) {
			return true;
		}
		return $this->vacationMode !== null && $this->vacationMode === Constants::VACATION_MODE_INHERIT;
	}

	public function validate(): array {
		$errors = [];
		$validModes = [
			Constants::VACATION_MODE_MANUAL_FIXED,
			Constants::VACATION_MODE_MODEL_BASED_SIMPLE,
			Constants::VACATION_MODE_TARIFF_RULE_BASED,
			Constants::VACATION_MODE_MANUAL_EXCEPTION,
			Constants::VACATION_MODE_INHERIT,
		];
		if ($this->vacationMode === null || !in_array($this->vacationMode, $validModes, true)) {
			$errors['vacationMode'] = 'Invalid vacation mode';
		}

		$isInherit = $this->isInherit();

		if (!$isInherit) {
			if (($this->vacationMode === Constants::VACATION_MODE_MANUAL_FIXED || $this->vacationMode === Constants::VACATION_MODE_MANUAL_EXCEPTION) && $this->manualDays === null) {
				$errors['manualDays'] = 'Manual days are required for manual modes';
			}
			if ($this->vacationMode === Constants::VACATION_MODE_TARIFF_RULE_BASED && $this->tariffRuleSetId === null) {
				$errors['tariffRuleSetId'] = 'Tariff rule set is required for tariff mode';
			}
			if ($this->vacationMode === Constants::VACATION_MODE_MANUAL_EXCEPTION && trim((string)$this->overrideReason) === '') {
				$errors['overrideReason'] = 'Override reason is required for manual exception mode';
			}
		}
		if ($this->manualDays !== null && ($this->manualDays < 0.0 || $this->manualDays > 4000.0)) {
			$errors['manualDays'] = 'Manual amount must be between 0 and 4000';
		}
		if ($this->effectiveFrom === null) {
			$errors['effectiveFrom'] = 'Effective from date is required';
		}
		if ($this->effectiveTo !== null && $this->effectiveFrom !== null && $this->effectiveTo < $this->effectiveFrom) {
			$errors['effectiveTo'] = 'Effective to date must be after effective from date';
		}
		return $errors;
	}
}
