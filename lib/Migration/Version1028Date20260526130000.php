<?php

declare(strict_types=1);

/**
 * Link ArbeitszeitCheck time rows to ProjectCheck billing rows (pc_time_entries.id).
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1028Date20260526130000 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('at_entries')) {
			$table = $schema->getTable('at_entries');
			if (!$table->hasColumn('project_check_time_entry_id')) {
				$table->addColumn('project_check_time_entry_id', Types::BIGINT, [
					'notnull' => false,
					'length' => 20,
				]);
			}
			if (!$table->hasIndex('at_entries_pc_te_idx')) {
				$table->addIndex(['project_check_time_entry_id'], 'at_entries_pc_te_idx');
			}
		}

		return $schema;
	}
}
