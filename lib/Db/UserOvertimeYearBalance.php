<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Per-user overtime opening balance for a calendar year.
 *
 * @method int getId()
 * @method void setId(int $id)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method int getYear()
 * @method void setYear(int $year)
 * @method float getOpeningBalanceHours()
 * @method void setOpeningBalanceHours(float $openingBalanceHours)
 * @method \DateTime getCreatedAt()
 * @method void setCreatedAt(\DateTime $createdAt)
 * @method \DateTime getUpdatedAt()
 * @method void setUpdatedAt(\DateTime $updatedAt)
 */
class UserOvertimeYearBalance extends Entity
{
	protected $userId;
	protected $year;
	protected $openingBalanceHours = 0.0;
	protected $createdAt;
	protected $updatedAt;

	public function __construct()
	{
		$this->addType('userId', 'string');
		$this->addType('year', 'integer');
		$this->addType('openingBalanceHours', 'float');
		$this->addType('createdAt', 'datetime');
		$this->addType('updatedAt', 'datetime');
	}
}
