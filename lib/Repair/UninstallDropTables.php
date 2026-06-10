<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud DB-Standards (auto-generated)
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Drops every table the arbeitszeitcheck app has ever created, migration rows, and app config.
 * Runs on app disable and before app files are removed (see core Installer / settings).
 *
 * Regenerate via:
 *     php scripts/check-nextcloud-db-standards.php sync-uninstall --app=arbeitszeitcheck
 *
 * Uses `DROP TABLE IF EXISTS` (not SchemaWrapper) so IDBConnection injection works on
 * all Nextcloud versions. MySQL temporarily disables FK checks so legacy FK chains
 * (e.g. project_files → projects) cannot block uninstall.
 */
namespace OCA\ArbeitszeitCheck\Repair;

use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

final class UninstallDropTables implements IRepairStep
{
	public const APP_ID = 'arbeitszeitcheck';

	/**
	 * Sorted list of every table this app has ever created across all migrations.
	 * Kept in sync by the DB-standards linter.
	 */
	public const TABLES = [
		'at_kiosk_creds',
		'at_kiosk_enrollment',
		'at_kiosk_sessions',
		'at_kiosk_terminals',
		'at_absence_calendar',
		'at_absences',
		'at_audit',
		'at_entitlement_snapshots',
		'at_entries',
		'at_holiday_suppress',
		'at_holidays',
		'at_model_vacation_defaults',
		'at_models',
		'at_month_closure',
		'at_month_closure_revision',
		'at_org_vacation_defaults',
		'at_ot_payout',
		'at_settings',
		'at_tariff_rule_modules',
		'at_tariff_rule_sets',
		'at_team_managers',
		'at_team_members',
		'at_team_vacation_policies',
		'at_teams',
		'at_user_models',
		'at_user_ot_year_bal',
		'at_user_vacation_policies',
		'at_vacation_rollover_log',
		'at_vacation_year_balance',
		'at_violations',
		'azc_license_state',
		'azc_mobile_seat',
		'azc_terminal_device',
	];

	public function __construct(
		private readonly IDBConnection $connection,
		private readonly IConfig $config,
	) {
	}

	public function getName(): string
	{
		return 'Drop arbeitszeitcheck tables and install metadata on uninstall';
	}

	public function run(IOutput $output): void
	{
		$provider = $this->connection->getDatabaseProvider();
		$fkChecksDisabled = false;
		if ($provider === IDBConnection::PLATFORM_MYSQL) {
			$this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');
			$fkChecksDisabled = true;
		}

		$dropped = 0;
		foreach (self::TABLES as $table) {
			if ($this->dropLogicalTableIfExists($table)) {
				$dropped++;
			}
		}

		if ($fkChecksDisabled) {
			$this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
		}

		$qb = $this->connection->getQueryBuilder();
		$qb->delete('migrations')
			->where($qb->expr()->eq('app', $qb->createNamedParameter(self::APP_ID)));
		$migrationsRemoved = $qb->executeStatement();

		$this->config->deleteAppValues(self::APP_ID);

		$output->info(sprintf(
			'arbeitszeitcheck: dropped %d of %d table(s); removed %d migration row(s) and app config.',
			$dropped,
			count(self::TABLES),
			$migrationsRemoved,
		));
	}

	private function dropLogicalTableIfExists(string $logicalTable): bool
	{
		if (!$this->connection->tableExists($logicalTable)) {
			return false;
		}

		$prefix = (string)$this->config->getSystemValue('dbtableprefix', 'oc_');
		$physical = $prefix . $logicalTable;
		$provider = $this->connection->getDatabaseProvider();

		if ($provider === IDBConnection::PLATFORM_MYSQL) {
			$this->connection->executeStatement(sprintf('DROP TABLE IF EXISTS `%s`', $physical));
		} elseif ($provider === IDBConnection::PLATFORM_POSTGRES) {
			$this->connection->executeStatement(sprintf('DROP TABLE IF EXISTS "%s" CASCADE', $physical));
		} elseif ($provider === IDBConnection::PLATFORM_ORACLE) {
			$this->connection->executeStatement(sprintf('DROP TABLE %s CASCADE CONSTRAINTS', $physical));
		} elseif ($provider === IDBConnection::PLATFORM_SQLITE) {
			$this->connection->executeStatement(sprintf('DROP TABLE IF EXISTS "%s"', $physical));
		}

		return true;
	}
}
