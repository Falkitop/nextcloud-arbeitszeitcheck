<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getId()
 * @method void setId(int $id)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method \DateTime getAssignedAt()
 * @method void setAssignedAt(\DateTime $assignedAt)
 * @method string getAssignedBy()
 * @method void setAssignedBy(string $assignedBy)
 */
class MobileSeat extends Entity
{
	protected $userId = '';
	protected $assignedAt;
	protected $assignedBy = '';

	public function __construct()
	{
		$this->addType('id', 'integer');
		$this->addType('assignedAt', 'datetime');
	}
}
