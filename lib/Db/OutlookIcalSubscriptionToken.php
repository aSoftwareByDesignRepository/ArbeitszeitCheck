<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Token backing store for per-team Outlook iCalendar subscriptions.
 *
 * Security properties:
 * - Only hashed tokens are stored for feed lookup (never plaintext in token_hash).
 * - Plaintext tokens are stored encrypted (ICrypto) for admin URL display.
 * - Tokens are tenant-scoped via `tenantId` (Nextcloud `instanceid`).
 * - Authorization is re-checked on every feed request; active tokens cannot outlive permissions.
 */
class OutlookIcalSubscriptionToken extends Entity
{
	/** @var int|null */
	public $id;

	/** @var string */
	protected $tenantId = '';

	/** @var string */
	protected $managerUserId = '';

	/** @var int|null */
	protected $teamId;

	/** @var string */
	protected $tokenHash = '';

	/** @var string|null */
	protected $tokenEncrypted = null;

	/** @var string|null */
	protected $feedLanguageCode;

	/** @var int */
	protected $isActive = 1;

	/** @var \DateTime|null */
	protected $revokedAt = null;

	/** @var \DateTime */
	protected $createdAt;

	/**
	 * @method int getId()
	 * @method void setId(int $id)
	 * @method string getTenantId()
	 * @method void setTenantId(string $tenantId)
	 * @method string getManagerUserId()
	 * @method void setManagerUserId(string $managerUserId)
	 * @method int|null getTeamId()
	 * @method void setTeamId(int $teamId)
	 * @method string getTokenHash()
	 * @method void setTokenHash(string $tokenHash)
	 * @method string|null getTokenEncrypted()
	 * @method void setTokenEncrypted(?string $tokenEncrypted)
	 * @method string|null getFeedLanguageCode()
	 * @method void setFeedLanguageCode(string $feedLanguageCode)
	 * @method int getIsActive()
	 * @method void setIsActive(int $isActive)
	 * @method \DateTime|null getRevokedAt()
	 * @method void setRevokedAt(?\DateTime $revokedAt)
	 * @method \DateTime getCreatedAt()
	 * @method void setCreatedAt(\DateTime $createdAt)
	 */
	public function __construct()
	{
		$this->addType('id', 'integer');
		$this->addType('tenantId', 'string');
		$this->addType('managerUserId', 'string');
		$this->addType('teamId', 'integer');
		$this->addType('tokenHash', 'string');
		$this->addType('tokenEncrypted', 'string');
		$this->addType('feedLanguageCode', 'string');
		$this->addType('isActive', 'smallint');
		$this->addType('revokedAt', 'datetime');
		$this->addType('createdAt', 'datetime');
	}
}

