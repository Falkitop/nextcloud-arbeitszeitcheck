<?php

declare(strict_types=1);

/**
 * Records an admin opt-out for a statutory holiday date in a Bundesland.
 *
 * While auto-restore is disabled, seeding must not recreate suppressed dates.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getId()
 * @method void setId(int $id)
 * @method string getState()
 * @method void setState(string $state)
 * @method \DateTime getDate()
 * @method void setDate(\DateTime $date)
 * @method \DateTime getCreatedAt()
 * @method void setCreatedAt(\DateTime $createdAt)
 * @method string|null getSuppressedBy()
 * @method void setSuppressedBy(?string $suppressedBy)
 */
class HolidaySuppression extends Entity
{
	/** @var string */
	protected $state;

	/** @var \DateTime */
	protected $date;

	/** @var \DateTime */
	protected $createdAt;

	/** @var string|null */
	protected $suppressedBy;

	public function __construct()
	{
		$this->addType('state', 'string');
		$this->addType('date', 'date');
		$this->addType('createdAt', 'datetime');
		$this->addType('suppressedBy', 'string');
	}
}
