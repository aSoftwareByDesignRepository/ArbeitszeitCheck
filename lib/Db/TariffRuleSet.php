<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Db;

use OCA\ArbeitszeitCheck\Constants;
use OCP\AppFramework\Db\Entity;

/**
 * @method int getId()
 * @method void setId(int $id)
 * @method string getTariffCode()
 * @method void setTariffCode(string $tariffCode)
 * @method string getVersion()
 * @method void setVersion(string $version)
 * @method string|null getJurisdiction()
 * @method void setJurisdiction(?string $jurisdiction)
 * @method \DateTime getValidFrom()
 * @method void setValidFrom(\DateTime $validFrom)
 * @method \DateTime|null getValidTo()
 * @method void setValidTo(?\DateTime $validTo)
 * @method string getActivationMode()
 * @method void setActivationMode(string $activationMode)
 * @method string getStatus()
 * @method void setStatus(string $status)
 * @method string|null getReferenceModel()
 * @method void setReferenceModel(?string $referenceModel)
 * @method \DateTime getCreatedAt()
 * @method void setCreatedAt(\DateTime $createdAt)
 * @method \DateTime getUpdatedAt()
 * @method void setUpdatedAt(\DateTime $updatedAt)
 */
class TariffRuleSet extends Entity {
	protected string $tariffCode = '';
	protected string $version = '';
	protected ?string $jurisdiction = null;
	protected \DateTime $validFrom;
	protected ?\DateTime $validTo = null;
	protected string $activationMode = 'immediate';
	protected string $status = Constants::TARIFF_RULE_SET_STATUS_DRAFT;
	protected ?string $referenceModel = null;
	protected \DateTime $createdAt;
	protected \DateTime $updatedAt;

	public function __construct() {
		$this->addType('tariffCode', 'string');
		$this->addType('version', 'string');
		$this->addType('jurisdiction', 'string');
		$this->addType('validFrom', 'date');
		$this->addType('validTo', 'date');
		$this->addType('activationMode', 'string');
		$this->addType('status', 'string');
		$this->addType('referenceModel', 'string');
		$this->addType('createdAt', 'datetime');
		$this->addType('updatedAt', 'datetime');
		$today = new \DateTime('today');
		$this->validFrom = clone $today;
		$this->createdAt = new \DateTime();
		$this->updatedAt = new \DateTime();
	}

	public function validate(): array {
		$errors = [];
		if (trim($this->tariffCode) === '') {
			$errors['tariffCode'] = 'Tariff code is required';
		}
		if (trim($this->version) === '') {
			$errors['version'] = 'Version is required';
		}
		if (!in_array($this->status, [
			Constants::TARIFF_RULE_SET_STATUS_DRAFT,
			Constants::TARIFF_RULE_SET_STATUS_ACTIVE,
			Constants::TARIFF_RULE_SET_STATUS_RETIRED,
		], true)) {
			$errors['status'] = 'Invalid tariff rule set status';
		}
		if (!in_array($this->activationMode, ['immediate', 'next_month', 'next_year'], true)) {
			$errors['activationMode'] = 'Invalid activation mode';
		}
		if ($this->validTo !== null && $this->validTo < $this->validFrom) {
			$errors['validTo'] = 'Valid to date must be after valid from date';
		}
		return $errors;
	}
}

