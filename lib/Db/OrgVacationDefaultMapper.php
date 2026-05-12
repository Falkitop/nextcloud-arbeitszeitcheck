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
	 * Count of L0 rows that are simultaneously active on `$asOfDate`. The
	 * organisation default is supposed to be a single active row per validity
	 * slot; if this returns more than 1, the engine emits a
	 * `degraded_org_default_collision` flag in the resolution trace and a
	 * `critical` log line so an admin can repair the data (REQ-ENT-10).
	 *
	 * Returns 0 when the table does not exist yet (fresh install before
	 * migration) so callers can fall back without a stack trace.
	 */
	public function countActiveByDate(\DateTimeInterface $asOfDate): int
	{
		try {
			$date = $asOfDate->format('Y-m-d');
			$qb = $this->db->getQueryBuilder();
			$qb->select($qb->func()->count('*', 'cnt'))
				->from($this->getTableName())
				->where($qb->expr()->lte('effective_from', $qb->createNamedParameter($date, IQueryBuilder::PARAM_STR)))
				->andWhere($qb->expr()->orX(
					$qb->expr()->isNull('effective_to'),
					$qb->expr()->gte('effective_to', $qb->createNamedParameter($date, IQueryBuilder::PARAM_STR))
				));
			$cursor = $qb->executeQuery();
			$row = $cursor->fetch();
			$cursor->closeCursor();
			return $row && isset($row['cnt']) ? (int)$row['cnt'] : 0;
		} catch (DBException $e) {
			if ($e->getReason() === DBException::REASON_DATABASE_OBJECT_NOT_FOUND) {
				return 0;
			}
			throw $e;
		}
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
	 * Detect existing rows whose validity range overlaps a *new* row defined
	 * by `$newFrom`/`$newTo` (inclusive). Used by the service layer to
	 * reject overlapping closed ranges (`REQ-DAT-03`): two L0 rows that
	 * cover the same calendar date would silently produce a
	 * `degraded_org_default_collision` flag at resolution time. We prefer
	 * to refuse the write *before* the data lands instead of relying on
	 * the degraded-state flag as a backstop.
	 *
	 * Returns an associative summary `[id, effective_from, effective_to]`
	 * for every overlapping row so the validation error can name the
	 * conflicting record.
	 *
	 * @return list<array{id: int, effective_from: string, effective_to: ?string}>
	 */
	public function findOverlappingRanges(\DateTime $newFrom, ?\DateTime $newTo, ?int $excludeId = null): array
	{
		$fromIso = $newFrom->format('Y-m-d');
		$toIso = $newTo !== null ? $newTo->format('Y-m-d') : null;
		try {
			$qb = $this->db->getQueryBuilder();
			$qb->select('id', 'effective_from', 'effective_to')
				->from($this->getTableName());

			// A pre-existing row (eFrom, eTo) overlaps the new range
			// (newFrom, newTo) iff:
			//   eFrom <= newTo (or newTo IS NULL, treated as +inf) AND
			//   (eTo IS NULL OR eTo >= newFrom)
			$conds = [];
			if ($toIso === null) {
				// New range is open-ended; only need eTo IS NULL OR eTo >= newFrom.
				$conds[] = $qb->expr()->orX(
					$qb->expr()->isNull('effective_to'),
					$qb->expr()->gte('effective_to', $qb->createNamedParameter($fromIso, IQueryBuilder::PARAM_STR))
				);
			} else {
				$conds[] = $qb->expr()->lte('effective_from', $qb->createNamedParameter($toIso, IQueryBuilder::PARAM_STR));
				$conds[] = $qb->expr()->orX(
					$qb->expr()->isNull('effective_to'),
					$qb->expr()->gte('effective_to', $qb->createNamedParameter($fromIso, IQueryBuilder::PARAM_STR))
				);
			}
			$qb->where(...$conds);
			if ($excludeId !== null) {
				$qb->andWhere($qb->expr()->neq('id', $qb->createNamedParameter($excludeId, IQueryBuilder::PARAM_INT)));
			}
			$qb->orderBy('effective_from', 'ASC');
			$cursor = $qb->executeQuery();
			$out = [];
			while ($row = $cursor->fetch()) {
				$out[] = [
					'id' => (int)$row['id'],
					'effective_from' => (string)$row['effective_from'],
					'effective_to' => $row['effective_to'] !== null ? (string)$row['effective_to'] : null,
				];
			}
			$cursor->closeCursor();
			return $out;
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
