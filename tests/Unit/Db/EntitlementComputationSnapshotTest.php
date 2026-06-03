<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Db;

use OCA\ArbeitszeitCheck\Db\EntitlementComputationSnapshot;
use PHPUnit\Framework\TestCase;

class EntitlementComputationSnapshotTest extends TestCase
{
	public function testDateSettersDoNotThrowOnFreshEntity(): void
	{
		$snapshot = new EntitlementComputationSnapshot();
		$snapshot->setAsOfDate(new \DateTime('2026-06-03'));
		$snapshot->setComputedAt(new \DateTime('2026-06-03 12:00:00'));

		self::assertSame('2026-06-03', $snapshot->getAsOfDate()->format('Y-m-d'));
		self::assertInstanceOf(\DateTime::class, $snapshot->getComputedAt());
	}

	public function testSetCalculationTraceMarksColumnDirty(): void
	{
		$snapshot = new EntitlementComputationSnapshot();
		$snapshot->setCalculationTrace(['algorithm_version' => 1, 'matched_layer' => 'L3']);

		self::assertArrayHasKey('calculationTraceJson', $snapshot->getUpdatedFields());
		self::assertSame(1, $snapshot->getCalculationTrace()['algorithm_version']);
	}

	public function testSnapshotIsReadyForInsert(): void
	{
		$snapshot = new EntitlementComputationSnapshot();
		$snapshot->setUserId('alice');
		$snapshot->setPeriodKey('2026');
		$snapshot->setAsOfDate(new \DateTime('2026-01-01'));
		$snapshot->setEffectiveEntitlementDays(30.0);
		$snapshot->setSource('tariff');
		$snapshot->setCalculationTrace(['algorithm_version' => 1]);
		$snapshot->setComputedAt(new \DateTime());
		$snapshot->setComputedBy('cli-test');

		$updated = $snapshot->getUpdatedFields();
		foreach (['userId', 'periodKey', 'asOfDate', 'effectiveEntitlementDays', 'source', 'calculationTraceJson', 'computedAt', 'computedBy'] as $field) {
			self::assertArrayHasKey($field, $updated, $field . ' must be written on insert');
		}
	}
}
