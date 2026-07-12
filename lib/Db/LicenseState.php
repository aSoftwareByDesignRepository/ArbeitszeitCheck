<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getId()
 * @method void setId(int $id)
 * @method string getCustomerId()
 * @method void setCustomerId(string $customerId)
 * @method \DateTime getValidUntil()
 * @method void setValidUntil(\DateTime $validUntil)
 * @method int getMobileSeats()
 * @method void setMobileSeats(int $mobileSeats)
 * @method int getTerminalDevices()
 * @method void setTerminalDevices(int $terminalDevices)
 * @method int getBundle()
 * @method void setBundle(int $bundle)
 * @method \DateTime getKeyAppliedAt()
 * @method void setKeyAppliedAt(\DateTime $keyAppliedAt)
 * @method string getPayloadB64()
 * @method void setPayloadB64(string $payloadB64)
 * @method string getSignatureB64()
 * @method void setSignatureB64(string $signatureB64)
 * @method string getBoundInstanceId()
 * @method void setBoundInstanceId(string $boundInstanceId)
 * @method string getLicenseFingerprint()
 * @method void setLicenseFingerprint(string $licenseFingerprint)
 */
class LicenseState extends Entity
{
	protected $customerId = '';
	protected $validUntil;
	protected $mobileSeats = 0;
	protected $terminalDevices = 0;
	protected $bundle = 0;
	protected $keyAppliedAt;
	protected $payloadB64 = '';
	protected $signatureB64 = '';
	protected $boundInstanceId = '';
	protected $licenseFingerprint = '';

	public function __construct()
	{
		$this->addType('id', 'integer');
		$this->addType('mobileSeats', 'integer');
		$this->addType('terminalDevices', 'integer');
		$this->addType('bundle', 'integer');
		$this->addType('validUntil', 'datetime');
		$this->addType('keyAppliedAt', 'datetime');
	}
}
