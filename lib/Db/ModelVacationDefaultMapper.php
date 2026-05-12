<?php

declare(strict_types=1);

/**
 * Mapper for L1 model-attached vacation defaults
 * ({@see ModelVacationDefault}).
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

class ModelVacationDefaultMapper extends QBMapper
{
	public function __construct(IDBConnection $db)
	{
		parent::__construct($db, 'at_model_vacation_defaults', ModelVacationDefault::class);
	}

	/**
	 * Default that is active for the given working-time model on the given
	 * date. Among multiple matches, the latest `effective_from` (then largest
	 * `id`) wins — matches the order used by the engine to make tie-breaking
	 * deterministic and reproducible by auditors.
	 */
	public function findActiveByModelAndDate(int $workingTimeModelId, \DateTimeInterface $asOfDate): ?ModelVacationDefault
	{
		try {
			$date = $asOfDate->format('Y-m-d');
			$qb = $this->db->getQueryBuilder();
			$qb->select('*')
				->from($this->getTableName())
				->where($qb->expr()->eq('working_time_model_id', $qb->createNamedParameter($workingTimeModelId, IQueryBuilder::PARAM_INT)))
				->andWhere($qb->expr()->lte('effective_from', $qb->createNamedParameter($date, IQueryBuilder::PARAM_STR)))
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

	/**
	 * @return ModelVacationDefault[]
	 */
	public function findByModelId(int $workingTimeModelId): array
	{
		try {
			$qb = $this->db->getQueryBuilder();
			$qb->select('*')
				->from($this->getTableName())
				->where($qb->expr()->eq('working_time_model_id', $qb->createNamedParameter($workingTimeModelId, IQueryBuilder::PARAM_INT)))
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

	public function find(int $id): ModelVacationDefault
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * @return ModelVacationDefault[]
	 */
	public function findAll(): array
	{
		try {
			$qb = $this->db->getQueryBuilder();
			$qb->select('*')
				->from($this->getTableName())
				->orderBy('working_time_model_id', 'ASC')
				->addOrderBy('effective_from', 'DESC');
			return $this->findEntities($qb);
		} catch (DBException $e) {
			if ($e->getReason() === DBException::REASON_DATABASE_OBJECT_NOT_FOUND) {
				return [];
			}
			throw $e;
		}
	}

	public function deleteByModelId(int $workingTimeModelId): int
	{
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('working_time_model_id', $qb->createNamedParameter($workingTimeModelId, IQueryBuilder::PARAM_INT)));
		return $qb->executeStatement();
	}
}
