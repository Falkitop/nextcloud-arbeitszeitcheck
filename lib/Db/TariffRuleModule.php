<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getId()
 * @method void setId(int $id)
 * @method int getRuleSetId()
 * @method void setRuleSetId(int $ruleSetId)
 * @method string getModuleType()
 * @method void setModuleType(string $moduleType)
 * @method string getConfigJson()
 * @method void setConfigJson(string $configJson)
 * @method int getSortOrder()
 * @method void setSortOrder(int $sortOrder)
 * @method \DateTime getCreatedAt()
 * @method void setCreatedAt(\DateTime $createdAt)
 * @method \DateTime getUpdatedAt()
 * @method void setUpdatedAt(\DateTime $updatedAt)
 */
class TariffRuleModule extends Entity {
	protected int $ruleSetId = 0;
	protected string $moduleType = '';
	protected string $configJson = '{}';
	protected int $sortOrder = 0;
	protected \DateTime $createdAt;
	protected \DateTime $updatedAt;

	public function __construct() {
		$this->addType('ruleSetId', 'integer');
		$this->addType('moduleType', 'string');
		$this->addType('configJson', 'string');
		$this->addType('sortOrder', 'integer');
		$this->addType('createdAt', 'datetime');
		$this->addType('updatedAt', 'datetime');
		// Typed \DateTime properties must be initialized before the parent
		// Entity::setter() can compare/assign them; otherwise the very first
		// setCreatedAt()/setUpdatedAt() call throws "must not be accessed
		// before initialization" and aborts persistence.
		$now = new \DateTime();
		$this->createdAt = $now;
		$this->updatedAt = clone $now;
	}

	public function getConfig(): array {
		$decoded = json_decode($this->configJson, true);
		return is_array($decoded) ? $decoded : [];
	}

	public function setConfig(array $config): void {
		// Assign directly (this is not a registered column) and explicitly mark
		// the backing column dirty so QBMapper::insert()/update() include it.
		// Going through the magic setter alone would skip the write whenever the
		// encoded value equals the current one (e.g. the default "{}").
		$this->configJson = (string)json_encode($config);
		$this->markFieldUpdated('configJson');
	}
}

