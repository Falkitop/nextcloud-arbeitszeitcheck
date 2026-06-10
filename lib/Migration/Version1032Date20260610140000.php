<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Track C — kiosk terminals, credentials, sessions, enrollment.
 */
class Version1032Date20260610140000 extends SimpleMigrationStep
{
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
	{
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('at_kiosk_terminals')) {
			$t = $schema->createTable('at_kiosk_terminals');
			$t->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
			]);
			$t->addColumn('terminal_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$t->addColumn('label', Types::STRING, [
				'notnull' => true,
				'length' => 128,
			]);
			$t->addColumn('token_hash', Types::TEXT, [
				'notnull' => true,
			]);
			$t->addColumn('pairing_code_hash', Types::TEXT, [
				'notnull' => false,
			]);
			$t->addColumn('pairing_expires_at', Types::DATETIME, [
				'notnull' => false,
			]);
			$t->addColumn('status', Types::STRING, [
				'notnull' => true,
				'length' => 16,
				'default' => 'active',
			]);
			$t->addColumn('created_by', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$t->addColumn('last_seen_at', Types::DATETIME, [
				'notnull' => false,
			]);
			$t->addColumn('created_at', Types::DATETIME, [
				'notnull' => true,
			]);
			$t->setPrimaryKey(['id'], 'at_kiosk_term_pk');
			$t->addUniqueIndex(['terminal_id'], 'at_kiosk_term_tid_uq');
			$t->addIndex(['status'], 'at_kiosk_term_st_idx');
		}

		if (!$schema->hasTable('at_kiosk_creds')) {
			$t = $schema->createTable('at_kiosk_creds');
			$t->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
			]);
			$t->addColumn('user_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$t->addColumn('type', Types::STRING, [
				'notnull' => true,
				'length' => 8,
			]);
			$t->addColumn('secret_hash', Types::TEXT, [
				'notnull' => false,
			]);
			$t->addColumn('lookup_hash', Types::STRING, [
				'notnull' => false,
				'length' => 128,
			]);
			$t->addColumn('label', Types::STRING, [
				'notnull' => false,
				'length' => 128,
			]);
			$t->addColumn('failed_attempts', Types::INTEGER, [
				'notnull' => true,
				'default' => 0,
			]);
			$t->addColumn('locked_until', Types::DATETIME, [
				'notnull' => false,
			]);
			$t->addColumn('created_by', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$t->addColumn('created_at', Types::DATETIME, [
				'notnull' => true,
			]);
			$t->setPrimaryKey(['id'], 'at_kiosk_cred_pk');
			$t->addUniqueIndex(['user_id', 'type'], 'at_kiosk_cred_ut_uq');
			$t->addUniqueIndex(['lookup_hash'], 'at_kiosk_cred_lk_uq');
		}

		if (!$schema->hasTable('at_kiosk_sessions')) {
			$t = $schema->createTable('at_kiosk_sessions');
			$t->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
			]);
			$t->addColumn('terminal_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$t->addColumn('user_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$t->addColumn('token_hash', Types::TEXT, [
				'notnull' => true,
			]);
			$t->addColumn('expires_at', Types::DATETIME, [
				'notnull' => true,
			]);
			$t->addColumn('used_at', Types::DATETIME, [
				'notnull' => false,
			]);
			$t->addColumn('created_at', Types::DATETIME, [
				'notnull' => true,
			]);
			$t->setPrimaryKey(['id'], 'at_kiosk_sess_pk');
			$t->addIndex(['terminal_id', 'expires_at'], 'at_kiosk_sess_te_idx');
		}

		if (!$schema->hasTable('at_kiosk_enrollment')) {
			$t = $schema->createTable('at_kiosk_enrollment');
			$t->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
			]);
			$t->addColumn('terminal_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$t->addColumn('user_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$t->addColumn('expires_at', Types::DATETIME, [
				'notnull' => true,
			]);
			$t->addColumn('completed_at', Types::DATETIME, [
				'notnull' => false,
			]);
			$t->addColumn('created_by', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$t->addColumn('created_at', Types::DATETIME, [
				'notnull' => true,
			]);
			$t->setPrimaryKey(['id'], 'at_kiosk_enr_pk');
			$t->addIndex(['terminal_id'], 'at_kiosk_enr_tid_idx');
		}

		return $schema;
	}
}
