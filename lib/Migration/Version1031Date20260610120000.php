<?php

declare(strict_types=1);

/**
 * Track A — commercial license tables (AZC2 org license, mobile seats, terminal slots).
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

class Version1031Date20260610120000 extends SimpleMigrationStep
{
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
	{
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('azc_license_state')) {
			$t = $schema->createTable('azc_license_state');
			$t->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
			]);
			$t->addColumn('customer_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$t->addColumn('valid_until', Types::DATE, [
				'notnull' => true,
			]);
			$t->addColumn('mobile_seats', Types::INTEGER, [
				'notnull' => true,
				'default' => 0,
			]);
			$t->addColumn('terminal_devices', Types::INTEGER, [
				'notnull' => true,
				'default' => 0,
			]);
			$t->addColumn('bundle', Types::SMALLINT, [
				'notnull' => true,
				'default' => 0,
			]);
			$t->addColumn('key_applied_at', Types::DATETIME, [
				'notnull' => true,
			]);
			$t->addColumn('payload_b64', Types::TEXT, [
				'notnull' => true,
			]);
			$t->addColumn('signature_b64', Types::TEXT, [
				'notnull' => true,
			]);
			$t->setPrimaryKey(['id'], 'azc_lic_st_pk');
		}

		if (!$schema->hasTable('azc_mobile_seat')) {
			$t = $schema->createTable('azc_mobile_seat');
			$t->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
			]);
			$t->addColumn('user_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$t->addColumn('assigned_at', Types::DATETIME, [
				'notnull' => true,
			]);
			$t->addColumn('assigned_by', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$t->setPrimaryKey(['id'], 'azc_mob_seat_pk');
			$t->addUniqueIndex(['user_id'], 'azc_mob_seat_uid_uq');
		}

		if (!$schema->hasTable('azc_terminal_device')) {
			$t = $schema->createTable('azc_terminal_device');
			$t->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
			]);
			$t->addColumn('kiosk_terminal_id', Types::STRING, [
				'notnull' => false,
				'length' => 64,
			]);
			$t->addColumn('label', Types::STRING, [
				'notnull' => true,
				'length' => 128,
			]);
			$t->addColumn('registered_at', Types::DATETIME, [
				'notnull' => true,
			]);
			$t->addColumn('revoked', Types::SMALLINT, [
				'notnull' => true,
				'default' => 0,
			]);
			$t->setPrimaryKey(['id'], 'azc_term_dev_pk');
			$t->addUniqueIndex(['kiosk_terminal_id'], 'azc_term_dev_kt_uq');
		}

		return $schema;
	}
}
