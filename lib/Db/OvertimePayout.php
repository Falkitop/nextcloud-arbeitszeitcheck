<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getId()
 * @method void setId(int $id)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method int getCalendarYear()
 * @method void setCalendarYear(int $calendarYear)
 * @method int getCalendarMonth()
 * @method void setCalendarMonth(int $calendarMonth)
 * @method float getHoursPaid()
 * @method void setHoursPaid(float $hoursPaid)
 * @method float getEffectiveBalanceBefore()
 * @method void setEffectiveBalanceBefore(float $effectiveBalanceBefore)
 * @method float getEffectiveBalanceAfter()
 * @method void setEffectiveBalanceAfter(float $effectiveBalanceAfter)
 * @method float getRawBalanceBefore()
 * @method void setRawBalanceBefore(float $rawBalanceBefore)
 * @method float getBankMaxHours()
 * @method void setBankMaxHours(float $bankMaxHours)
 * @method string getProcessedBy()
 * @method void setProcessedBy(string $processedBy)
 * @method \DateTime getCreatedAt()
 * @method void setCreatedAt(\DateTime $createdAt)
 */
class OvertimePayout extends Entity
{
	protected $userId;
	protected $calendarYear;
	protected $calendarMonth;
	protected $hoursPaid = 0.0;
	protected $effectiveBalanceBefore = 0.0;
	protected $effectiveBalanceAfter = 0.0;
	protected $rawBalanceBefore = 0.0;
	protected $bankMaxHours = 100.0;
	protected $processedBy;
	protected $createdAt;

	public function __construct()
	{
		$this->addType('userId', 'string');
		$this->addType('calendarYear', 'integer');
		$this->addType('calendarMonth', 'integer');
		$this->addType('hoursPaid', 'float');
		$this->addType('effectiveBalanceBefore', 'float');
		$this->addType('effectiveBalanceAfter', 'float');
		$this->addType('rawBalanceBefore', 'float');
		$this->addType('bankMaxHours', 'float');
		$this->addType('processedBy', 'string');
		$this->addType('createdAt', 'datetime');
	}
}
