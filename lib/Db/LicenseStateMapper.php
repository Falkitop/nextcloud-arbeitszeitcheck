<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class LicenseStateMapper extends QBMapper
{
	public function __construct(IDBConnection $db)
	{
		parent::__construct($db, 'azc_license_state', LicenseState::class);
	}

	public function findCurrent(): ?LicenseState
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->setMaxResults(1)
			->orderBy('id', 'DESC');
		try {
			return $this->findEntity($qb);
		} catch (\OCP\AppFramework\Db\DoesNotExistException) {
			return null;
		} catch (\OCP\AppFramework\Db\MultipleObjectsReturnedException) {
			return null;
		}
	}

	public function upsert(LicenseState $state): LicenseState
	{
		$existing = $this->findCurrent();
		if ($existing !== null) {
			$state->setId($existing->getId());
			return $this->update($state);
		}
		return $this->insert($state);
	}

	public function deleteAll(): void
	{
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())->executeStatement();
	}
}
