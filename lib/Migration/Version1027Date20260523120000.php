<?php

declare(strict_types=1);

/**
 * Immutable audit log of overtime hours paid out above the bank cap (Auszahlung).
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

class Version1027Date20260523120000 extends SimpleMigrationStep
{
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
	{
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$changed = false;

		// strlen(oc_) + strlen(at_ot_payout) = 3 + 12 = 15 <= 30 (Oracle-safe).
		if (!$schema->hasTable('at_ot_payout')) {
			$table = $schema->createTable('at_ot_payout');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('user_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('calendar_year', Types::INTEGER, [
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('calendar_month', Types::INTEGER, [
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('hours_paid', Types::FLOAT, [
				'notnull' => true,
			]);
			$table->addColumn('effective_balance_before', Types::FLOAT, [
				'notnull' => true,
			]);
			$table->addColumn('effective_balance_after', Types::FLOAT, [
				'notnull' => true,
			]);
			$table->addColumn('raw_balance_before', Types::FLOAT, [
				'notnull' => true,
			]);
			$table->addColumn('bank_max_hours', Types::FLOAT, [
				'notnull' => true,
			]);
			$table->addColumn('processed_by', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
			$table->setPrimaryKey(['id'], 'at_otpay_pk');
			$table->addUniqueIndex(['user_id', 'calendar_year', 'calendar_month'], 'at_otpay_user_ym_uq');
			$table->addIndex(['calendar_year', 'calendar_month'], 'at_otpay_ym_idx');
			$table->addIndex(['user_id'], 'at_otpay_user_idx');
			$changed = true;
		}

		return $changed ? $schema : null;
	}
}
