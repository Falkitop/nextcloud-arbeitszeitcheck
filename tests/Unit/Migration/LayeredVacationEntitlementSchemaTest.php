<?php

declare(strict_types=1);

/**
 * Schema regression test for the layered vacation entitlement migration
 * ({@see \OCA\ArbeitszeitCheck\Migration\Version1024Date20260512150000}).
 *
 * Verifies:
 *  - L0 (`at_org_vacation_defaults`), L1 (`at_model_vacation_defaults`) and
 *    L2 (`at_team_vacation_policies`) tables are created with the correct
 *    columns and indices.
 *  - L3 (`at_user_vacation_policies`) gains the `inherit_lower_layers`
 *    boolean column (nullable in schema, default `false`) so the migration
 *    is golden-file equivalent for every existing tenant and passes Nextcloud
 *    portability checks (BOOLEAN must not be NOT NULL on new columns).
 *  - The L2 → `at_teams` foreign key uses the prefixed table reference
 *    (guards against the install-blocking PostgreSQL bug that bit issue #4).
 *  - Re-running the migration is a no-op (idempotent).
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\Migration;

use Doctrine\DBAL\Schema\Schema;
use OCA\ArbeitszeitCheck\Migration\Version1024Date20260512150000;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;

// `PrefixedSchemaWrapperFake` is declared as a side-effect of the
// MonthClosureForeignKeyTest file. Its namespace matches ours; we just need
// the file to be evaluated so the class is loaded.
require_once __DIR__ . '/MonthClosureForeignKeyTest.php';

class LayeredVacationEntitlementSchemaTest extends TestCase
{
	private const PREFIX = 'oc_';

	public function testCreatesAllLayerTablesWithExpectedColumns(): void
	{
		$schema = $this->primedSchemaWithDeps();
		$wrapper = new PrefixedSchemaWrapperFake($schema, self::PREFIX);
		$output = $this->createMock(IOutput::class);
		$migration = new Version1024Date20260512150000();

		$result = $migration->changeSchema($output, fn () => $wrapper, []);
		self::assertNotNull($result, 'Migration must report a schema change on a fresh install.');

		// L0
		$l0 = $schema->getTable(self::PREFIX . 'at_org_vacation_defaults');
		foreach (['id', 'vacation_mode', 'manual_days', 'tariff_rule_set_id', 'description', 'effective_from', 'effective_to', 'version', 'created_by', 'created_at', 'updated_at'] as $col) {
			self::assertTrue($l0->hasColumn($col), "L0 column '$col' missing");
		}
		$l0PrimaryKey = $l0->getPrimaryKey();
		self::assertNotNull($l0PrimaryKey);
		self::assertSame(['id'], $l0PrimaryKey->getColumns());
		self::assertTrue($this->hasIndexCovering($l0, ['effective_from', 'effective_to']), 'L0 effective_from/effective_to index missing');

		// L1
		$l1 = $schema->getTable(self::PREFIX . 'at_model_vacation_defaults');
		foreach (['id', 'working_time_model_id', 'vacation_mode', 'manual_days', 'tariff_rule_set_id', 'description', 'effective_from', 'effective_to', 'version', 'created_by', 'created_at', 'updated_at'] as $col) {
			self::assertTrue($l1->hasColumn($col), "L1 column '$col' missing");
		}
		self::assertTrue($this->hasIndexCovering($l1, ['working_time_model_id']), 'L1 model index missing');

		// L2
		$l2 = $schema->getTable(self::PREFIX . 'at_team_vacation_policies');
		foreach (['id', 'team_id', 'vacation_mode', 'manual_days', 'tariff_rule_set_id', 'description', 'priority', 'effective_from', 'effective_to', 'version', 'created_by', 'created_at', 'updated_at'] as $col) {
			self::assertTrue($l2->hasColumn($col), "L2 column '$col' missing");
		}
		self::assertTrue($this->hasIndexCovering($l2, ['team_id']), 'L2 team index missing');

		// L2 FK → at_teams must reference the *prefixed* table name.
		$fks = $l2->getForeignKeys();
		self::assertCount(1, $fks, 'Exactly one foreign key (team_id → at_teams) expected on L2 table');
		$fk = array_values($fks)[0];
		self::assertSame(self::PREFIX . 'at_teams', $fk->getForeignTableName(), 'L2.team_id FK must reference the prefixed at_teams table');
		self::assertSame(['team_id'], array_map('strval', $fk->getLocalColumns()));
		self::assertSame(['id'], array_map('strval', $fk->getForeignColumns()));
		self::assertSame('CASCADE', strtoupper((string)$fk->getOption('onDelete')));

		// L3 — inherit flag
		$l3 = $schema->getTable(self::PREFIX . 'at_user_vacation_policies');
		self::assertTrue($l3->hasColumn('inherit_lower_layers'), 'L3 inherit_lower_layers column missing');
		$col = $l3->getColumn('inherit_lower_layers');
		self::assertFalse((bool)$col->getDefault(), 'L3 inherit_lower_layers must default to false to preserve legacy behaviour');
		self::assertFalse($col->getNotnull(), 'L3 inherit_lower_layers must be nullable in schema (Nextcloud forbids BOOLEAN NOT NULL on new columns)');
	}

	public function testMigrationIsIdempotent(): void
	{
		$schema = $this->primedSchemaWithDeps();
		$wrapper = new PrefixedSchemaWrapperFake($schema, self::PREFIX);
		$output = $this->createMock(IOutput::class);
		$migration = new Version1024Date20260512150000();

		$migration->changeSchema($output, fn () => $wrapper, []);
		$second = $migration->changeSchema($output, fn () => $wrapper, []);
		self::assertNull($second, 'Re-running the migration must be a no-op once all layer tables exist.');
	}

	private function primedSchemaWithDeps(): Schema
	{
		$schema = new Schema();
		// Pre-create dependency tables (mimics a Nextcloud install where the
		// L3 table already exists and the L2 FK target `at_teams` is present).
		$teams = $schema->createTable(self::PREFIX . 'at_teams');
		$teams->addColumn('id', 'bigint', ['autoincrement' => true, 'unsigned' => true, 'notnull' => true]);
		$teams->setPrimaryKey(['id']);

		$l3 = $schema->createTable(self::PREFIX . 'at_user_vacation_policies');
		$l3->addColumn('id', 'bigint', ['autoincrement' => true, 'unsigned' => true, 'notnull' => true]);
		$l3->addColumn('user_id', 'string', ['notnull' => true, 'length' => 64]);
		$l3->setPrimaryKey(['id']);
		return $schema;
	}

	/**
	 * @param list<string> $columns
	 */
	private function hasIndexCovering(\Doctrine\DBAL\Schema\Table $table, array $columns): bool
	{
		foreach ($table->getIndexes() as $index) {
			$indexCols = array_map('strval', $index->getUnquotedColumns());
			if (array_slice($indexCols, 0, count($columns)) === $columns) {
				return true;
			}
		}
		return false;
	}
}
