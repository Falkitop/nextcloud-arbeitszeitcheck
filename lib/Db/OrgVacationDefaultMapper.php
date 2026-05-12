<?php

declare(strict_types=1);

/**
 * Mapper for the L0 organisation-wide vacation default
 * ({@see OrgVacationDefault}).
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception as DBException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class OrgVacationDefaultMapper extends QBMapper
{
	public function __construct(IDBConnection $db)
	{
		parent::__construct($db, 'at_org_vacation_defaults', OrgVacationDefault::class);
	}

	/**
	 * The single active organisation default for `$asOfDate` (or `null`).
	 * "Active" means `effective_from <= asOf <= effective_to` (`effective_to`
	 * NULL = open ended). The query is deterministic — on the off chance two
	 * rows are simultaneously active (config drift / failed migration), the
	 * row with the **latest** `effective_from` wins and the layered engine
	 * emits a `degraded_org_default_collision` flag in the trace.
	 *
	 * Returns `null` when the layer table does not exist yet (fresh install
	 * before migration), so the engine can fall back without `DoesNotExist`
	 * surfacing in admin logs.
	 */
	public function findActiveByDate(\DateTimeInterface $asOfDate): ?OrgVacationDefault
	{
		try {
			$date = $asOfDate->format('Y-m-d');
			$qb = $this->db->getQueryBuilder();
			$qb->select('*')
				->from($this->getTableName())
				->where($qb->expr()->lte('effective_from', $qb->createNamedParameter($date, IQueryBuilder::PARAM_STR)))
				->andWhere($qb->expr()->orX(
					$qb->expr()->isNull('effective_to'),
					$qb->expr()->gte('effective_to', $qb->createNamedParameter($date, IQueryBuilder::PARAM_STR))
				))
				->orderBy('effective_from', 'DESC')
				->addOrderBy('id', 'DESC')
				->setMaxResults(1);
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		} catch (DBException $e) {
			if ($e->getReason() === DBException::REASON_DATABASE_OBJECT_NOT_FOUND) {
				return null;
			}
			throw $e;
		}
	}

	public function find(int $id): OrgVacationDefault
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * @return OrgVacationDefault[]
	 */
	public function findAll(): array
	{
		try {
			$qb = $this->db->getQueryBuilder();
			$qb->select('*')
				->from($this->getTableName())
				->orderBy('effective_from', 'DESC')
				->addOrderBy('id', 'DESC');
			return $this->findEntities($qb);
		} catch (DBException $e) {
			if ($e->getReason() === DBException::REASON_DATABASE_OBJECT_NOT_FOUND) {
				return [];
			}
			throw $e;
		}
	}

	/**
	 * Close any currently open-ended row that overlaps a new row starting on
	 * `$newFrom`, by setting `effective_to = newFrom - 1 day`. Returns the
	 * IDs whose validity was trimmed so the audit log can record them.
	 *
	 * @return list<int>
	 */
	public function closeOverlappingOpenRows(\DateTime $newFrom): array
	{
		$newFromIso = $newFrom->format('Y-m-d');
		$endIso = (clone $newFrom)->modify('-1 day')->format('Y-m-d');

		$select = $this->db->getQueryBuilder();
		$select->select('id', 'effective_from')
			->from($this->getTableName())
			->where($select->expr()->isNull('effective_to'))
			->andWhere($select->expr()->lte('effective_from', $select->createNamedParameter($newFromIso, IQueryBuilder::PARAM_STR)));
		$rows = $select->executeQuery();
		$ids = [];
		while ($row = $rows->fetch()) {
			$ids[] = (int)$row['id'];
		}
		$rows->closeCursor();
		if ($ids === []) {
			return [];
		}

		$update = $this->db->getQueryBuilder();
		$update->update($this->getTableName())
			->set('effective_to', $update->createNamedParameter($endIso, IQueryBuilder::PARAM_STR))
			->set('updated_at', $update->createNamedParameter((new \DateTime())->format('Y-m-d H:i:s'), IQueryBuilder::PARAM_STR))
			->where($update->expr()->in('id', $update->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)));
		$update->executeStatement();
		return $ids;
	}
}
