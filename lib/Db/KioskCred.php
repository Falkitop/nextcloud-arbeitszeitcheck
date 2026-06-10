<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getId()
 * @method void setId(int $id)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getType()
 * @method void setType(string $type)
 * @method string|null getSecretHash()
 * @method void setSecretHash(?string $secretHash)
 * @method string|null getLookupHash()
 * @method void setLookupHash(?string $lookupHash)
 * @method string|null getLabel()
 * @method void setLabel(?string $label)
 * @method int getFailedAttempts()
 * @method void setFailedAttempts(int $failedAttempts)
 * @method \DateTime|null getLockedUntil()
 * @method void setLockedUntil(?\DateTime $lockedUntil)
 * @method string getCreatedBy()
 * @method void setCreatedBy(string $createdBy)
 * @method \DateTime getCreatedAt()
 * @method void setCreatedAt(\DateTime $createdAt)
 */
class KioskCred extends Entity
{
	protected $userId = '';
	protected $type = '';
	protected $secretHash = null;
	protected $lookupHash = null;
	protected $label = null;
	protected $failedAttempts = 0;
	protected $lockedUntil = null;
	protected $createdBy = '';
	protected $createdAt;

	public function __construct()
	{
		$this->addType('id', 'integer');
		$this->addType('failedAttempts', 'integer');
		$this->addType('lockedUntil', 'datetime');
		$this->addType('createdAt', 'datetime');
	}
}
