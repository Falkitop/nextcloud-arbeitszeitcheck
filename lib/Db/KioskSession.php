<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getId()
 * @method void setId(int $id)
 * @method string getTerminalId()
 * @method void setTerminalId(string $terminalId)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getTokenHash()
 * @method void setTokenHash(string $tokenHash)
 * @method \DateTime getExpiresAt()
 * @method void setExpiresAt(\DateTime $expiresAt)
 * @method \DateTime|null getUsedAt()
 * @method void setUsedAt(?\DateTime $usedAt)
 * @method \DateTime getCreatedAt()
 * @method void setCreatedAt(\DateTime $createdAt)
 */
class KioskSession extends Entity
{
	protected $terminalId = '';
	protected $userId = '';
	protected $tokenHash = '';
	protected $expiresAt;
	protected $usedAt = null;
	protected $createdAt;

	public function __construct()
	{
		$this->addType('id', 'integer');
		$this->addType('expiresAt', 'datetime');
		$this->addType('usedAt', 'datetime');
		$this->addType('createdAt', 'datetime');
	}
}
