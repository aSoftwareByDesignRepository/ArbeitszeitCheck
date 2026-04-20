<?php

declare(strict_types=1);

/**
 * Immutable seal history for month closures.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getId()
 * @method void setId(int $id)
 * @method int getClosureId()
 * @method void setClosureId(int $id)
 * @method int getVersion()
 * @method void setVersion(int $version)
 * @method string getSnapshotHash()
 * @method void setSnapshotHash(string $hash)
 * @method string|null getPrevSnapshotHash()
 * @method void setPrevSnapshotHash(?string $hash)
 * @method string getCanonicalPayload()
 * @method void setCanonicalPayload(string $payload)
 * @method \DateTime getSealedAt()
 * @method void setSealedAt(\DateTime $at)
 * @method string getSealedBy()
 * @method void setSealedBy(string $uid)
 */
class MonthClosureRevision extends Entity
{
	protected $closureId;
	protected $version;
	protected $snapshotHash;
	protected $prevSnapshotHash;
	protected $canonicalPayload;
	protected $sealedAt;
	protected $sealedBy;

	public function __construct()
	{
		$this->addType('closureId', 'integer');
		$this->addType('version', 'integer');
		$this->addType('sealedAt', 'datetime');
	}
}
