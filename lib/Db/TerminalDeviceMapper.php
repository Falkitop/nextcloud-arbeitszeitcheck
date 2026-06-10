<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class TerminalDeviceMapper extends QBMapper
{
	public function __construct(IDBConnection $db)
	{
		parent::__construct($db, 'azc_terminal_device', TerminalDevice::class);
	}

	public function countActive(): int
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'c'))
			->from($this->getTableName())
			->where($qb->expr()->eq('revoked', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)));
		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();
		return (int)($row['c'] ?? 0);
	}

	/** @return TerminalDevice[] */
	public function findAllActive(): array
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('revoked', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
			->orderBy('registered_at', 'ASC');
		return $this->findEntities($qb);
	}

	public function findByKioskTerminalId(string $kioskTerminalId): ?TerminalDevice
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('kiosk_terminal_id', $qb->createNamedParameter($kioskTerminalId)))
			->andWhere($qb->expr()->eq('revoked', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)));
		$entities = $this->findEntities($qb);
		return $entities[0] ?? null;
	}

	public function findUnlinkedSlot(): ?TerminalDevice
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->isNull('kiosk_terminal_id'))
			->andWhere($qb->expr()->eq('revoked', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
			->orderBy('registered_at', 'ASC')
			->setMaxResults(1);
		$entities = $this->findEntities($qb);
		return $entities[0] ?? null;
	}
}
