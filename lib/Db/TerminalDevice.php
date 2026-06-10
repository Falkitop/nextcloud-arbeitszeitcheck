<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getId()
 * @method void setId(int $id)
 * @method string|null getKioskTerminalId()
 * @method void setKioskTerminalId(?string $kioskTerminalId)
 * @method string getLabel()
 * @method void setLabel(string $label)
 * @method \DateTime getRegisteredAt()
 * @method void setRegisteredAt(\DateTime $registeredAt)
 * @method int getRevoked()
 * @method void setRevoked(int $revoked)
 */
class TerminalDevice extends Entity
{
	protected $kioskTerminalId = null;
	protected $label = '';
	protected $registeredAt;
	protected $revoked = 0;

	public function __construct()
	{
		$this->addType('id', 'integer');
		$this->addType('revoked', 'integer');
		$this->addType('registeredAt', 'datetime');
	}
}
