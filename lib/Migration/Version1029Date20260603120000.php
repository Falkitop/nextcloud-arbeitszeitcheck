<?php

declare(strict_types=1);

/**
 * Statutory holiday suppressions (per Bundesland + date).
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

class Version1029Date20260603120000 extends SimpleMigrationStep
{
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
	{
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('at_holiday_suppress')) {
			$table = $schema->createTable('at_holiday_suppress');

			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
			]);

			$table->addColumn('state', Types::STRING, [
				'notnull' => true,
				'length' => 8,
			]);

			$table->addColumn('date', Types::DATE, [
				'notnull' => true,
			]);

			$table->addColumn('created_at', Types::DATETIME, [
				'notnull' => true,
			]);

			$table->addColumn('suppressed_by', Types::STRING, [
				'notnull' => false,
				'length' => 64,
			]);

			$table->setPrimaryKey(['id'], 'at_hol_suppress_pk');
			$table->addUniqueIndex(['state', 'date'], 'at_hol_suppress_st_dt_u');
		}

		return $schema;
	}
}
