<?php

declare(strict_types=1);

/**
 * Per-user overtime opening balance per calendar year (Eröffnungssaldo Überstunden).
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

class Version1025Date20260519120000 extends SimpleMigrationStep
{
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
	{
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$changed = false;

		// Logical name MUST keep `strlen(dbtableprefix)+strlen(name) <= 30` for
		// Oracle / legacy Nextcloud migration checks (default prefix `oc_`).
		if (!$schema->hasTable('at_user_ot_year_bal')) {
			$table = $schema->createTable('at_user_ot_year_bal');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('user_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('year', Types::INTEGER, [
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('opening_balance_hours', Types::FLOAT, [
				'notnull' => true,
				'default' => 0,
			]);
			$table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
			$table->addColumn('updated_at', Types::DATETIME, ['notnull' => true]);
			$table->setPrimaryKey(['id'], 'at_uotb_pk');
			$table->addUniqueIndex(['user_id', 'year'], 'at_uotb_user_year_uq');
			$table->addIndex(['user_id'], 'at_uotb_user_idx');
			$changed = true;
		}

		return $changed ? $schema : null;
	}
}
