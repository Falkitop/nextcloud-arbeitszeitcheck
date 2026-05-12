<?php

declare(strict_types=1);

/**
 * Layered vacation entitlement resolution (L0 organisation / L1 working-time
 * model / L2 app team) — schema migration.
 *
 * This migration introduces the three new layer tables and extends the
 * existing `at_user_vacation_policies` (L3) table with an `inherit_lower_layers`
 * flag that lets an individual assignment defer to the resolution chain
 * instead of pinning a number.
 *
 *  - `at_org_vacation_defaults`   : L0, organisation-wide defaults (one logical
 *                                   active row at any point in time; enforced
 *                                   transactionally — partial indexes are
 *                                   portable on PostgreSQL only, so we rely on
 *                                   service-layer guards plus an
 *                                   `effective_from` index).
 *  - `at_model_vacation_defaults` : L1, attached to a `working_time_model`.
 *  - `at_team_vacation_policies`  : L2, attached to an app `Team` (FK with
 *                                   ON DELETE CASCADE — when an HR team is
 *                                   removed its policies cascade out so the
 *                                   resolution chain never resolves to a
 *                                   stale rule).
 *  - `at_user_vacation_policies`  : L3, gains `inherit_lower_layers` (nullable
 *                                   boolean) so an explicit policy row can
 *                                   request the layer chain instead of
 *                                   pinning a value. Existing rows are
 *                                   defaulted to `0` (= explicit) so the
 *                                   migration is golden-file equivalent for
 *                                   all existing users.
 *
 * Notes on FK declarations:
 *  - All FKs are declared by passing the Doctrine `Table` object returned by
 *    `$schema->getTable()`, never the raw string, so the Nextcloud
 *    `dbtableprefix` is applied. Issue #4 (PostgreSQL install crash) burned
 *    us once; the test
 *    `tests/Unit/Migration/MonthClosureForeignKeyTest` covers the
 *    equivalent contract for `at_month_closure_revision` and
 *    `tests/Unit/Migration/LayeredVacationEntitlementSchemaTest`
 *    covers this migration.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1024Date20260512150000 extends SimpleMigrationStep
{
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
	{
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$changed = false;

		if (!$schema->hasTable('at_org_vacation_defaults')) {
			$table = $schema->createTable('at_org_vacation_defaults');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20, 'unsigned' => true]);
			$table->addColumn('vacation_mode', Types::STRING, ['notnull' => true, 'length' => 32]);
			$table->addColumn('manual_days', Types::FLOAT, ['notnull' => false, 'precision' => 6, 'scale' => 2]);
			$table->addColumn('tariff_rule_set_id', Types::BIGINT, ['notnull' => false, 'length' => 20]);
			$table->addColumn('description', Types::TEXT, ['notnull' => false]);
			$table->addColumn('effective_from', Types::DATE, ['notnull' => true]);
			$table->addColumn('effective_to', Types::DATE, ['notnull' => false]);
			$table->addColumn('version', Types::INTEGER, ['notnull' => true, 'unsigned' => true, 'default' => 1]);
			$table->addColumn('created_by', Types::STRING, ['notnull' => true, 'length' => 64, 'default' => 'system']);
			$table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
			$table->addColumn('updated_at', Types::DATETIME, ['notnull' => true]);
			$table->setPrimaryKey(['id'], 'at_ovd_pk');
			$table->addIndex(['effective_from', 'effective_to'], 'at_ovd_effective_idx');
			$changed = true;
		}

		if (!$schema->hasTable('at_model_vacation_defaults')) {
			$table = $schema->createTable('at_model_vacation_defaults');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20, 'unsigned' => true]);
			$table->addColumn('working_time_model_id', Types::BIGINT, ['notnull' => true, 'length' => 20]);
			$table->addColumn('vacation_mode', Types::STRING, ['notnull' => true, 'length' => 32]);
			$table->addColumn('manual_days', Types::FLOAT, ['notnull' => false, 'precision' => 6, 'scale' => 2]);
			$table->addColumn('tariff_rule_set_id', Types::BIGINT, ['notnull' => false, 'length' => 20]);
			$table->addColumn('description', Types::TEXT, ['notnull' => false]);
			$table->addColumn('effective_from', Types::DATE, ['notnull' => true]);
			$table->addColumn('effective_to', Types::DATE, ['notnull' => false]);
			$table->addColumn('version', Types::INTEGER, ['notnull' => true, 'unsigned' => true, 'default' => 1]);
			$table->addColumn('created_by', Types::STRING, ['notnull' => true, 'length' => 64, 'default' => 'system']);
			$table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
			$table->addColumn('updated_at', Types::DATETIME, ['notnull' => true]);
			$table->setPrimaryKey(['id'], 'at_mvd_pk');
			$table->addIndex(['working_time_model_id'], 'at_mvd_model_idx');
			$table->addIndex(['working_time_model_id', 'effective_from', 'effective_to'], 'at_mvd_model_eff_idx');
			$changed = true;
		}

		if (!$schema->hasTable('at_team_vacation_policies')) {
			$table = $schema->createTable('at_team_vacation_policies');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20, 'unsigned' => true]);
			$table->addColumn('team_id', Types::BIGINT, ['notnull' => true, 'length' => 20]);
			$table->addColumn('vacation_mode', Types::STRING, ['notnull' => true, 'length' => 32]);
			$table->addColumn('manual_days', Types::FLOAT, ['notnull' => false, 'precision' => 6, 'scale' => 2]);
			$table->addColumn('tariff_rule_set_id', Types::BIGINT, ['notnull' => false, 'length' => 20]);
			$table->addColumn('description', Types::TEXT, ['notnull' => false]);
			$table->addColumn('priority', Types::INTEGER, ['notnull' => true, 'default' => 0]);
			$table->addColumn('effective_from', Types::DATE, ['notnull' => true]);
			$table->addColumn('effective_to', Types::DATE, ['notnull' => false]);
			$table->addColumn('version', Types::INTEGER, ['notnull' => true, 'unsigned' => true, 'default' => 1]);
			$table->addColumn('created_by', Types::STRING, ['notnull' => true, 'length' => 64, 'default' => 'system']);
			$table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
			$table->addColumn('updated_at', Types::DATETIME, ['notnull' => true]);
			$table->setPrimaryKey(['id'], 'at_tvp_pk');
			$table->addIndex(['team_id'], 'at_tvp_team_idx');
			$table->addIndex(['team_id', 'effective_from', 'effective_to'], 'at_tvp_team_eff_idx');

			if ($schema->hasTable('at_teams')) {
				$table->addForeignKeyConstraint(
					$schema->getTable('at_teams'),
					['team_id'],
					['id'],
					['onDelete' => 'CASCADE'],
					'at_tvp_team_fk'
				);
			}
			$changed = true;
		}

		// L3: extend existing per-user assignments with inherit flag.
		// Migration is golden-file equivalent: default = 0 (= explicit policy,
		// preserve today's behaviour) for every existing row.
		if ($schema->hasTable('at_user_vacation_policies')) {
			$table = $schema->getTable('at_user_vacation_policies');
			if (!$table->hasColumn('inherit_lower_layers')) {
				$table->addColumn('inherit_lower_layers', Types::BOOLEAN, [
					'notnull' => true,
					'default' => false,
				]);
				$changed = true;
			}
		}

		return $changed ? $schema : null;
	}
}
