<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getId()
 * @method void setId(int $id)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method int getYear()
 * @method void setYear(int $year)
 * @method int getMonth()
 * @method void setMonth(int $month)
 * @method int getVersion()
 * @method void setVersion(int $version)
 * @method string getStatus()
 * @method void setStatus(string $status)
 * @method string|null getSnapshotHash()
 * @method void setSnapshotHash(?string $hash)
 * @method string|null getPrevSnapshotHash()
 * @method void setPrevSnapshotHash(?string $hash)
 * @method string|null getCanonicalPayload()
 * @method void setCanonicalPayload(?string $payload)
 * @method \DateTime|null getFinalizedAt()
 * @method void setFinalizedAt(?\DateTime $at)
 * @method string|null getFinalizedBy()
 * @method void setFinalizedBy(?string $uid)
 * @method \DateTime|null getReopenedAt()
 * @method void setReopenedAt(?\DateTime $at)
 * @method string|null getReopenedBy()
 * @method void setReopenedBy(?string $uid)
 * @method string|null getReopenReason()
 * @method void setReopenReason(?string $reason)
 */
class MonthClosure extends Entity
{
	public const STATUS_FINALIZED = 'finalized';
	public const STATUS_OPEN = 'open';

	protected $userId;
	protected $year;
	protected $month;
	protected $version;
	protected $status;
	protected $snapshotHash;
	protected $prevSnapshotHash;
	protected $canonicalPayload;
	protected $finalizedAt;
	protected $finalizedBy;
	protected $reopenedAt;
	protected $reopenedBy;
	protected $reopenReason;

	public function __construct()
	{
		$this->addType('year', 'integer');
		$this->addType('month', 'integer');
		$this->addType('version', 'integer');
		$this->addType('finalizedAt', 'datetime');
		$this->addType('reopenedAt', 'datetime');
	}
}
