<?php

declare(strict_types=1);

/**
 * Backfill the missing `at_mcr_closure_fk` foreign key.
 *
 * Background
 * ----------
 * In `Version1014Date20260409120000` the FK from
 * `oc_at_month_closure_revision.closure_id` → `oc_at_month_closure(id)` was
 * declared by passing the raw, *unprefixed* string `'at_month_closure'` to
 * `addForeignKeyConstraint()`. On PostgreSQL this aborts the install loudly
 * (the user-visible regression report:
 *   "An exception occurred while executing a query: SQLSTATE[42P01]:
 *    Undefined table: 7 FEHLER: Relation »at_month_closure« existiert nicht")
 * because Doctrine then emits SQL that references the literal `at_month_closure`
 * relation instead of the prefixed `oc_at_month_closure`.
 *
 * On MariaDB / MySQL the same broken DDL did not surface a hard error — the
 * FK was silently dropped during table creation, leaving the
 * `closure_id` column with only the auto-generated index and *no* referential
 * integrity. That is a data-integrity hazard for the month-closure audit
 * trail and must be repaired retro-actively for existing installs.
 *
 * What this migration does
 * ------------------------
 *  1. preSchemaChange : best-effort delete of orphan revision rows whose
 *     `closure_id` does not match any existing closure. Without this step
 *     the FK creation would fail on legitimate (pre-fix) datasets.
 *
 *  2. changeSchema    : adds the FK `at_mcr_closure_fk` (ON DELETE CASCADE)
 *     when both tables exist and the FK is not yet present.
 *
 * The migration is fully idempotent and safe to re-run.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1023Date20260512143000 extends SimpleMigrationStep
{
	private const FK_NAME = 'at_mcr_closure_fk';

	public function __construct(
		private IDBConnection $db,
	) {
	}

	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void
	{
		// On a fresh install both tables are created in Version1014 in the
		// same diff that this migration runs in. The orphan cleanup is only
		// needed for existing installs that already have data, so skip when
		// either table is missing.
		if (!$this->db->tableExists('at_month_closure') || !$this->db->tableExists('at_month_closure_revision')) {
			return;
		}

		try {
			$select = $this->db->getQueryBuilder();
			$select->select('r.id')
				->from('at_month_closure_revision', 'r')
				->leftJoin(
					'r',
					'at_month_closure',
					'c',
					$select->expr()->eq('r.closure_id', 'c.id')
				)
				->where($select->expr()->isNull('c.id'));

			$result = $select->executeQuery();
			$orphanIds = [];
			while (($row = $result->fetch()) !== false) {
				$orphanIds[] = (int)$row['id'];
			}
			$result->closeCursor();

			if ($orphanIds === []) {
				return;
			}

			$output->info(sprintf(
				'ArbeitszeitCheck: removing %d orphan month-closure revision row(s) before backfilling FK.',
				count($orphanIds)
			));

			// Delete in chunks of 500 so that IN-clauses stay well under the
			// portable parameter limits (Oracle: 1000, MySQL: bound by
			// max_allowed_packet, SQLite: SQLITE_MAX_VARIABLE_NUMBER).
			foreach (array_chunk($orphanIds, 500) as $chunk) {
				$delete = $this->db->getQueryBuilder();
				$delete->delete('at_month_closure_revision')
					->where($delete->expr()->in(
						'id',
						$delete->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)
					));
				$delete->executeStatement();
			}
		} catch (\Throwable $e) {
			// Best-effort: log and let the schema step decide whether the
			// FK can still be added. A subsequent run will retry.
			\OCP\Log\logger('arbeitszeitcheck')->warning(
				'Version1023: orphan revision cleanup failed: ' . $e->getMessage(),
				['exception' => $e, 'app' => 'arbeitszeitcheck']
			);
		}
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
	{
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('at_month_closure') || !$schema->hasTable('at_month_closure_revision')) {
			return null;
		}

		$revision = $schema->getTable('at_month_closure_revision');
		$closure = $schema->getTable('at_month_closure');

		if ($revision->hasForeignKey(self::FK_NAME)) {
			return null;
		}

		// Critical: pass the Table OBJECT, not a string. The wrapper's table
		// object carries the prefixed name `oc_at_month_closure`; passing the
		// raw unprefixed string would re-introduce the original PostgreSQL
		// install crash.
		$revision->addForeignKeyConstraint(
			$closure,
			['closure_id'],
			['id'],
			['onDelete' => 'CASCADE'],
			self::FK_NAME
		);

		$output->info('ArbeitszeitCheck: added missing FK at_mcr_closure_fk on at_month_closure_revision(closure_id).');

		return $schema;
	}
}
