<?php

declare(strict_types=1);

/**
 * Close any leftover {@see TimeEntry::STATUS_PAUSED} rows after every app upgrade.
 *
 * This is the final safety net for the historic "clock-out left entry as paused
 * without end_time" defect (released in app versions ≤ 1.1.x and fully fixed in
 * 1.2.0/1.2.1). Migration 1020 already swept those rows once, but a repair step
 * runs on every `occ upgrade` and is idempotent — so an admin restoring an old
 * database snapshot, importing legacy data, or upgrading from a very early
 * version will always end up with a clean dataset, even if the migration is not
 * re-executed.
 *
 * The repair is intentionally *safer* than the in-request healer in
 * {@see \OCA\ArbeitszeitCheck\Service\TimeTrackingService::repairStalePausedAutomaticEntries()}:
 *
 *   - it runs OUTSIDE of any user session, so we never trip the month-closure
 *     guard or invalidate a user lock (the per-user healer respects those
 *     guards on purpose so finalised months stay immutable),
 *   - it does not attempt to recompute ArbZG §3/§4 adjustments (the in-request
 *     healer does that with the proper user context); instead it produces the
 *     minimal honest record: end_time = COALESCE(end_time, updated_at, start_time),
 *     status = completed, ended_reason = stale_paused_repair.
 *
 * Manual paused rows (`is_manual_entry = 1`) are also swept because the entity
 * allows them in principle even though no current code path creates them — that
 * defence-in-depth matches the contract in {@see \OCA\ArbeitszeitCheck\Db\TimeEntry::canEdit()}
 * and {@see \OCA\ArbeitszeitCheck\Db\TimeEntry::canDelete()} which both grant
 * the user explicit recovery actions for paused rows.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Repair;

use OCA\ArbeitszeitCheck\Db\TimeEntry;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class RepairOrphanedPausedEntries implements IRepairStep
{
	public function __construct(
		private IDBConnection $db,
	) {
	}

	public function getName(): string
	{
		return 'Close orphaned paused time entries left by older clock-out bugs';
	}

	public function run(IOutput $output): void
	{
		try {
			// Quick sanity check: skip silently if the table doesn't exist yet
			// (a brand-new install will hit the schema migrations first).
			if (!$this->db->tableExists('at_entries')) {
				return;
			}

			// 1) paused + end_time NOT NULL  -> status mismatch only, flip to completed.
			$qb1 = $this->db->getQueryBuilder();
			$fixedStatus = (int)$qb1
				->update('at_entries')
				->set('status', $qb1->createNamedParameter(TimeEntry::STATUS_COMPLETED, IQueryBuilder::PARAM_STR))
				->where($qb1->expr()->eq('status', $qb1->createNamedParameter(TimeEntry::STATUS_PAUSED, IQueryBuilder::PARAM_STR)))
				->andWhere($qb1->expr()->isNotNull('end_time'))
				->executeStatement();

			// 2) paused + end_time IS NULL + updated_at IS NOT NULL
			//    -> close at the moment the row was last frozen.
			//    createFunction() is used because QueryBuilder does not natively
			//    support SET col = other_col via parameterised expressions.
			$qb2 = $this->db->getQueryBuilder();
			$fixedFromUpdated = (int)$qb2
				->update('at_entries')
				->set('end_time', $qb2->createFunction('updated_at'))
				->set('status', $qb2->createNamedParameter(TimeEntry::STATUS_COMPLETED, IQueryBuilder::PARAM_STR))
				->set('ended_reason', $qb2->createNamedParameter(TimeEntry::ENDED_REASON_STALE_PAUSED_REPAIR, IQueryBuilder::PARAM_STR))
				->set('policy_applied', $qb2->createNamedParameter('repair', IQueryBuilder::PARAM_STR))
				->where($qb2->expr()->eq('status', $qb2->createNamedParameter(TimeEntry::STATUS_PAUSED, IQueryBuilder::PARAM_STR)))
				->andWhere($qb2->expr()->isNull('end_time'))
				->andWhere($qb2->expr()->isNotNull('updated_at'))
				->executeStatement();

			// 3) paused + end_time IS NULL + updated_at IS NULL
			//    -> zero-duration completed record so the row leaves the broken state.
			$qb3 = $this->db->getQueryBuilder();
			$fixedFromStart = (int)$qb3
				->update('at_entries')
				->set('end_time', $qb3->createFunction('start_time'))
				->set('status', $qb3->createNamedParameter(TimeEntry::STATUS_COMPLETED, IQueryBuilder::PARAM_STR))
				->set('ended_reason', $qb3->createNamedParameter(TimeEntry::ENDED_REASON_STALE_PAUSED_REPAIR, IQueryBuilder::PARAM_STR))
				->set('policy_applied', $qb3->createNamedParameter('repair', IQueryBuilder::PARAM_STR))
				->where($qb3->expr()->eq('status', $qb3->createNamedParameter(TimeEntry::STATUS_PAUSED, IQueryBuilder::PARAM_STR)))
				->andWhere($qb3->expr()->isNull('end_time'))
				->andWhere($qb3->expr()->isNull('updated_at'))
				->executeStatement();

			$total = $fixedStatus + $fixedFromUpdated + $fixedFromStart;
			if ($total > 0) {
				$output->info(sprintf(
					'Closed %d orphaned paused time entr%s (status-only=%d, from updated_at=%d, zero-duration=%d).',
					$total,
					$total === 1 ? 'y' : 'ies',
					$fixedStatus,
					$fixedFromUpdated,
					$fixedFromStart
				));
			}
		} catch (\Throwable $e) {
			// Non-fatal: log and continue. The next `occ upgrade` will re-try.
			$output->warning('Could not sweep orphaned paused entries: ' . $e->getMessage());
			\OCP\Log\logger('arbeitszeitcheck')->error(
				'RepairOrphanedPausedEntries failed: ' . $e->getMessage(),
				['app' => 'arbeitszeitcheck', 'exception' => $e]
			);
		}
	}
}
