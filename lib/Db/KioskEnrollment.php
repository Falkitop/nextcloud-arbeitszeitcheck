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
 * @method \DateTime getExpiresAt()
 * @method void setExpiresAt(\DateTime $expiresAt)
 * @method \DateTime|null getCompletedAt()
 * @method void setCompletedAt(?\DateTime $completedAt)
 * @method string getCreatedBy()
 * @method void setCreatedBy(string $createdBy)
 * @method \DateTime getCreatedAt()
 * @method void setCreatedAt(\DateTime $createdAt)
 */
class KioskEnrollment extends Entity
{
	protected $terminalId = '';
	protected $userId = '';
	protected $expiresAt;
	protected $completedAt = null;
	protected $createdBy = '';
	protected $createdAt;

	public function __construct()
	{
		$this->addType('id', 'integer');
		$this->addType('expiresAt', 'datetime');
		$this->addType('completedAt', 'datetime');
		$this->addType('createdAt', 'datetime');
	}
}
