<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getId()
 * @method void setId(int $id)
 * @method string getTerminalId()
 * @method void setTerminalId(string $terminalId)
 * @method string getLabel()
 * @method void setLabel(string $label)
 * @method string getTokenHash()
 * @method void setTokenHash(string $tokenHash)
 * @method string|null getPairingCodeHash()
 * @method void setPairingCodeHash(?string $pairingCodeHash)
 * @method \DateTime|null getPairingExpiresAt()
 * @method void setPairingExpiresAt(?\DateTime $pairingExpiresAt)
 * @method string getStatus()
 * @method void setStatus(string $status)
 * @method string getCreatedBy()
 * @method void setCreatedBy(string $createdBy)
 * @method \DateTime|null getLastSeenAt()
 * @method void setLastSeenAt(?\DateTime $lastSeenAt)
 * @method \DateTime getCreatedAt()
 * @method void setCreatedAt(\DateTime $createdAt)
 */
class KioskTerminal extends Entity
{
	protected $terminalId = '';
	protected $label = '';
	protected $tokenHash = '';
	protected $pairingCodeHash = null;
	protected $pairingExpiresAt = null;
	protected $status = 'active';
	protected $createdBy = '';
	protected $lastSeenAt = null;
	protected $createdAt;

	public function __construct()
	{
		$this->addType('id', 'integer');
		$this->addType('pairingExpiresAt', 'datetime');
		$this->addType('lastSeenAt', 'datetime');
		$this->addType('createdAt', 'datetime');
	}
}
