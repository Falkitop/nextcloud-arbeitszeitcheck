<?php

declare(strict_types=1);

/**
 * Persistence contract for tariff rule modules.
 *
 * Regression guard: a TariffRuleModule must be fully insertable straight after
 * construction. Two historical defects broke this and left orphaned draft rule
 * sets that then returned HTTP 409 on every retry:
 *   1. uninitialised typed $createdAt/$updatedAt threw "must not be accessed
 *      before initialization" on the first setCreatedAt() call;
 *   2. setConfig() bypassed the magic setter, so the NOT NULL config_json
 *      column was omitted from the INSERT.
 *
 * @copyright Copyright (c) 2026 Alexander Mäule
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\Db;

use OCA\ArbeitszeitCheck\Db\TariffRuleModule;
use PHPUnit\Framework\TestCase;

class TariffRuleModuleTest extends TestCase
{
	public function testTimestampSettersDoNotThrowOnFreshEntity(): void
	{
		$module = new TariffRuleModule();
		$module->setCreatedAt(new \DateTime('2026-06-03 10:00:00'));
		$module->setUpdatedAt(new \DateTime('2026-06-03 10:00:00'));

		self::assertInstanceOf(\DateTime::class, $module->getCreatedAt());
		self::assertInstanceOf(\DateTime::class, $module->getUpdatedAt());
	}

	public function testSetConfigMarksColumnDirtyAndRoundTrips(): void
	{
		$module = new TariffRuleModule();
		$module->setConfig(['reference_days' => 30, 'reference_week_days' => 5]);

		self::assertArrayHasKey('configJson', $module->getUpdatedFields());
		self::assertSame(
			['reference_days' => 30, 'reference_week_days' => 5],
			$module->getConfig()
		);
	}

	/**
	 * The default backing value is "{}"; an empty config must still be written
	 * explicitly so a NOT NULL column never falls back to a missing INSERT value.
	 */
	public function testEmptyConfigStillMarksColumnDirty(): void
	{
		$module = new TariffRuleModule();
		$module->setConfig([]);

		self::assertArrayHasKey('configJson', $module->getUpdatedFields());
		self::assertSame([], $module->getConfig());
	}

	public function testFullModuleIsReadyForInsert(): void
	{
		$module = new TariffRuleModule();
		$module->setRuleSetId(7);
		$module->setModuleType('base_formula');
		$module->setConfig(['reference_days' => 30]);
		$module->setSortOrder(0);
		$module->setCreatedAt(new \DateTime());
		$module->setUpdatedAt(new \DateTime());

		$updated = $module->getUpdatedFields();
		foreach (['ruleSetId', 'moduleType', 'configJson', 'createdAt', 'updatedAt'] as $field) {
			self::assertArrayHasKey($field, $updated, $field . ' must be written on insert');
		}
	}

	public function testGetConfigReturnsEmptyArrayForCorruptJson(): void
	{
		$module = TariffRuleModule::fromRow([
			'rule_set_id' => 1,
			'module_type' => 'base_formula',
			'config_json' => 'not-json',
		]);

		self::assertSame([], $module->getConfig());
	}
}
