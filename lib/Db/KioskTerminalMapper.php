<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class KioskTerminalMapper extends QBMapper
{
	public function __construct(IDBConnection $db)
	{
		parent::__construct($db, 'at_kiosk_terminals', KioskTerminal::class);
	}

	public function findByTerminalId(string $terminalId): ?KioskTerminal
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('terminal_id', $qb->createNamedParameter($terminalId)));
		$entities = $this->findEntities($qb);
		return $entities[0] ?? null;
	}

	/** @return KioskTerminal[] */
	public function findAllActive(): array
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('status', $qb->createNamedParameter('active')))
			->orderBy('created_at', 'ASC');
		return $this->findEntities($qb);
	}

	/** @return KioskTerminal[] */
	public function findPendingPairing(): array
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('status', $qb->createNamedParameter('pending')))
			->orderBy('created_at', 'DESC');
		return $this->findEntities($qb);
	}
}
