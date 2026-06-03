<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service;

use OCA\ArbeitszeitCheck\Service\HolidayService;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for HolidayService.
 */
class HolidayServiceTest extends TestCase
{
	public function testIsGermanPublicHolidayFixedDates(): void
	{
		// New Year
		$this->assertTrue(HolidayService::isGermanPublicHoliday(new \DateTime('2024-01-01')));

		// Labour Day
		$this->assertTrue(HolidayService::isGermanPublicHoliday(new \DateTime('2024-05-01')));

		// Christmas
		$this->assertTrue(HolidayService::isGermanPublicHoliday(new \DateTime('2024-12-25')));
		$this->assertTrue(HolidayService::isGermanPublicHoliday(new \DateTime('2024-12-26')));

		// Normal working day
		$this->assertFalse(HolidayService::isGermanPublicHoliday(new \DateTime('2024-01-15'))); // Monday
	}

	public function testComputeWorkingDaysExcludesWeekendsAndHolidaysAroundChristmas(): void
	{
		// Vacation from 23.12.2024 (Mon) to 03.01.2025 (Fri)
		$start = new \DateTime('2024-12-23');
		$end = new \DateTime('2025-01-03');

		$weights = [
			'2024-12-25' => 1.0,
			'2024-12-26' => 1.0,
			'2025-01-01' => 1.0,
		];
		$workingDays = HolidayService::computeWorkingDaysFromWeights($start, $end, $weights);

		// Manually expected working days:
		// 23.12. (Mon)  workday
		// 24.12. (Tue)  workday (not a German public holiday by default)
		// 25.12. (Wed)  Christmas (holiday)
		// 26.12. (Thu)  Second Christmas Day (holiday)
		// 27.12. (Fri)  workday
		// Weekend 28./29.12. ignored
		// 30.12. (Mon)  workday
		// 31.12. (Tue)  workday (not a German public holiday by default)
		// 01.01. (Wed)  New Year (holiday)
		// 02.01. (Thu)  workday
		// 03.01. (Fri)  workday
		//
		// => 7 working days
		$this->assertSame(7.0, $workingDays);
	}

	public function testComputeWorkingDaysPerYearSplitsAcrossYearBoundary(): void
	{
		$start = new \DateTime('2024-12-23');
		$end = new \DateTime('2025-01-03');

		$weights = [
			'2024-12-25' => 1.0,
			'2024-12-26' => 1.0,
			'2025-01-01' => 1.0,
		];
		$perYear = HolidayService::computeWorkingDaysPerYearFromWeights($start, $end, $weights);

		// From 23.12.2024 to 31.12.2024:
		// Working days: 23, 24, 27, 30, 31  => 5 days (25/26 holidays, weekend ignored)
		// From 01.01.2025 to 03.01.2025:
		// Working days: 02, 03 (01.01. holiday) => 2 days
		$this->assertArrayHasKey(2024, $perYear);
		$this->assertArrayHasKey(2025, $perYear);
		$this->assertSame(5.0, $perYear[2024]);
		$this->assertSame(2.0, $perYear[2025]);
	}

	/**
	 * Company / custom half-day holidays (extra weights) must reduce that weekday by 0.5.
	 */
	public function testComputeWorkingDaysAppliesHalfDayExtraWeights(): void
	{
		// Mon 2024-01-08 .. Wed 2024-01-10 — three weekdays, no statutory holiday on these dates.
		$start = new \DateTime('2024-01-08');
		$end = new \DateTime('2024-01-10');
		$extra = [
			'2024-01-09' => 0.5,
		];
		$this->assertSame(2.5, HolidayService::computeWorkingDays($start, $end, $extra));

		$perYear = HolidayService::computeWorkingDaysPerYear($start, $end, $extra);
		$this->assertSame(2.5, $perYear[2024]);
	}
}

