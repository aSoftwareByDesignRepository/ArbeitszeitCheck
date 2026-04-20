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
 */
class UserVacationPolicyAssignment extends Entity {
	protected string $userId = '';
	protected string $vacationMode = Constants::VACATION_MODE_MANUAL_FIXED;
	protected ?float $manualDays = null;
	protected ?int $tariffRuleSetId = null;
	protected ?string $overrideReason = null;
	protected ?\DateTime $effectiveFrom = null;
	protected ?\DateTime $effectiveTo = null;
	protected string $createdBy = 'system';
	protected ?\DateTime $createdAt = null;
	protected ?\DateTime $updatedAt = null;

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
	}

	public function validate(): array {
		$errors = [];
		$validModes = [
			Constants::VACATION_MODE_MANUAL_FIXED,
			Constants::VACATION_MODE_MODEL_BASED_SIMPLE,
			Constants::VACATION_MODE_TARIFF_RULE_BASED,
			Constants::VACATION_MODE_MANUAL_EXCEPTION,
		];
		if (!in_array($this->vacationMode, $validModes, true)) {
			$errors['vacationMode'] = 'Invalid vacation mode';
		}
		if (($this->vacationMode === Constants::VACATION_MODE_MANUAL_FIXED || $this->vacationMode === Constants::VACATION_MODE_MANUAL_EXCEPTION) && $this->manualDays === null) {
			$errors['manualDays'] = 'Manual days are required for manual modes';
		}
		if ($this->vacationMode === Constants::VACATION_MODE_TARIFF_RULE_BASED && $this->tariffRuleSetId === null) {
			$errors['tariffRuleSetId'] = 'Tariff rule set is required for tariff mode';
		}
		if ($this->vacationMode === Constants::VACATION_MODE_MANUAL_EXCEPTION && trim((string)$this->overrideReason) === '') {
			$errors['overrideReason'] = 'Override reason is required for manual exception mode';
		}
		if ($this->manualDays !== null && ($this->manualDays < 0.0 || $this->manualDays > 366.0)) {
			$errors['manualDays'] = 'Manual days must be between 0 and 366';
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

