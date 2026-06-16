<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service;

use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Service\UserEmploymentSettingsService;
use OCA\ArbeitszeitCheck\Service\VacationProrationService;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;

class VacationProrationServiceTest extends TestCase
{
	private function d(?string $ymd): ?\DateTimeImmutable
	{
		return $ymd === null ? null : (new \DateTimeImmutable($ymd))->setTime(0, 0, 0);
	}

	public function testNoEmploymentDatesReturnsFullEntitlement(): void
	{
		$r = VacationProrationService::computeProration(2026, 30.0, null, null, Constants::VACATION_PRORATION_METHOD_TWELFTHS);

		$this->assertSame(30.0, $r['days']);
		$this->assertFalse($r['prorated']);
		$this->assertSame(12, $r['months_covered']);
		$this->assertTrue($r['employed_in_year']);
		$this->assertNull($r['employment_start']);
		$this->assertNull($r['employment_end']);
	}

	public function testFullYearEmploymentIsNotProrated(): void
	{
		$r = VacationProrationService::computeProration(
			2026,
			30.0,
			$this->d('2026-01-01'),
			$this->d('2026-12-31'),
			Constants::VACATION_PRORATION_METHOD_TWELFTHS
		);

		$this->assertSame(30.0, $r['days']);
		$this->assertFalse($r['prorated']);
		$this->assertSame(12, $r['months_covered']);
	}

	public function testEmploymentStartedAroundSchemaMigrationStartsBeforeYear(): void
	{
		// Hired in a previous year, ongoing ⇒ full entitlement this year.
		$r = VacationProrationService::computeProration(
			2026,
			30.0,
			$this->d('2020-05-01'),
			null,
			Constants::VACATION_PRORATION_METHOD_TWELFTHS
		);

		$this->assertSame(30.0, $r['days']);
		$this->assertFalse($r['prorated']);
		$this->assertSame(12, $r['months_covered']);
	}

	/**
	 * The headline bug: a mid-year joiner (1 May) must receive a reduced
	 * entitlement. May–December = 8 months ⇒ 30 × 8/12 = 20 days.
	 */
	public function testMidYearJoinerMayFirstTwelfths(): void
	{
		$r = VacationProrationService::computeProration(
			2026,
			30.0,
			$this->d('2026-05-01'),
			null,
			Constants::VACATION_PRORATION_METHOD_TWELFTHS
		);

		$this->assertSame(20.0, $r['days']);
		$this->assertTrue($r['prorated']);
		$this->assertSame(8, $r['months_covered']);
		$this->assertTrue($r['employed_in_year']);
		$this->assertSame('2026-05-01', $r['covered_from']);
		$this->assertSame('2026-12-31', $r['covered_to']);
	}

	/**
	 * A started month counts in full (employee-favourable): joining on 15 May
	 * still counts May, i.e. 8 months.
	 */
	public function testStartedMonthCountsInFull(): void
	{
		$r = VacationProrationService::computeProration(
			2026,
			30.0,
			$this->d('2026-05-15'),
			null,
			Constants::VACATION_PRORATION_METHOD_TWELFTHS
		);

		$this->assertSame(20.0, $r['days']);
		$this->assertSame(8, $r['months_covered']);
	}

	/**
	 * Mid-year leaver: employed Jan–Jun = 6 months ⇒ 30 × 6/12 = 15 days.
	 */
	public function testMidYearLeaverTwelfths(): void
	{
		$r = VacationProrationService::computeProration(
			2026,
			30.0,
			null,
			$this->d('2026-06-30'),
			Constants::VACATION_PRORATION_METHOD_TWELFTHS
		);

		$this->assertSame(15.0, $r['days']);
		$this->assertTrue($r['prorated']);
		$this->assertSame(6, $r['months_covered']);
		$this->assertSame('2026-01-01', $r['covered_from']);
		$this->assertSame('2026-06-30', $r['covered_to']);
	}

	/**
	 * §5(2): a fractional part of at least half a day rounds up. Four months
	 * of 25 days = 25 × 4/12 = 8.333… ⇒ kept (fraction < 0.5) at 8.33.
	 * Seven months of 25 days = 25 × 7/12 = 14.583… ⇒ rounds up to 15.
	 */
	public function testStatutoryRoundingKeepsSmallFraction(): void
	{
		$r = VacationProrationService::computeProration(
			2026,
			25.0,
			$this->d('2026-09-01'), // Sep–Dec = 4 months
			null,
			Constants::VACATION_PRORATION_METHOD_TWELFTHS
		);

		$this->assertEqualsWithDelta(8.33, $r['days'], 0.001);
		$this->assertSame(4, $r['months_covered']);
	}

	public function testStatutoryRoundingRoundsUpLargeFraction(): void
	{
		$r = VacationProrationService::computeProration(
			2026,
			25.0,
			$this->d('2026-06-01'), // Jun–Dec = 7 months
			null,
			Constants::VACATION_PRORATION_METHOD_TWELFTHS
		);

		// 25 × 7/12 = 14.5833… ⇒ up to 15.
		$this->assertSame(15.0, $r['days']);
		$this->assertSame(7, $r['months_covered']);
	}

	public function testDailyMethodMidYearJoiner(): void
	{
		// 2026 is not a leap year (365 days). 1 May → 31 Dec inclusive = 245 days.
		$r = VacationProrationService::computeProration(
			2026,
			30.0,
			$this->d('2026-05-01'),
			null,
			Constants::VACATION_PRORATION_METHOD_DAILY
		);

		$expected = round(30.0 * (245 / 365), 2);
		$this->assertEqualsWithDelta($expected, $r['days'], 0.001);
		$this->assertSame(245, $r['covered_days']);
		$this->assertSame(365, $r['days_in_year']);
		$this->assertTrue($r['prorated']);
	}

	public function testLeapYearDaysInYear(): void
	{
		$r = VacationProrationService::computeProration(
			2024,
			30.0,
			$this->d('2024-01-01'),
			$this->d('2024-12-31'),
			Constants::VACATION_PRORATION_METHOD_DAILY
		);

		$this->assertSame(366, $r['days_in_year']);
		$this->assertSame(30.0, $r['days']);
		$this->assertFalse($r['prorated']);
	}

	public function testEmploymentEntirelyBeforeYearYieldsZero(): void
	{
		$r = VacationProrationService::computeProration(
			2026,
			30.0,
			$this->d('2020-01-01'),
			$this->d('2021-12-31'),
			Constants::VACATION_PRORATION_METHOD_TWELFTHS
		);

		$this->assertSame(0.0, $r['days']);
		$this->assertFalse($r['employed_in_year']);
		$this->assertSame(0, $r['months_covered']);
		$this->assertNull($r['covered_from']);
	}

	public function testEmploymentEntirelyAfterYearYieldsZero(): void
	{
		$r = VacationProrationService::computeProration(
			2026,
			30.0,
			$this->d('2027-01-01'),
			null,
			Constants::VACATION_PRORATION_METHOD_TWELFTHS
		);

		$this->assertSame(0.0, $r['days']);
		$this->assertFalse($r['employed_in_year']);
	}

	public function testInvertedPeriodFailsClosedToZero(): void
	{
		$r = VacationProrationService::computeProration(
			2026,
			30.0,
			$this->d('2026-08-01'),
			$this->d('2026-03-01'),
			Constants::VACATION_PRORATION_METHOD_TWELFTHS
		);

		$this->assertSame(0.0, $r['days']);
		$this->assertFalse($r['employed_in_year']);
	}

	public function testZeroFullEntitlementStaysZero(): void
	{
		$r = VacationProrationService::computeProration(
			2026,
			0.0,
			$this->d('2026-05-01'),
			null,
			Constants::VACATION_PRORATION_METHOD_TWELFTHS
		);

		$this->assertSame(0.0, $r['days']);
	}

	public function testProratedNeverExceedsFullEntitlement(): void
	{
		// Single-day employment, daily method, must never exceed the full days.
		$r = VacationProrationService::computeProration(
			2026,
			30.0,
			$this->d('2026-12-31'),
			$this->d('2026-12-31'),
			Constants::VACATION_PRORATION_METHOD_DAILY
		);

		$this->assertGreaterThanOrEqual(0.0, $r['days']);
		$this->assertLessThanOrEqual(30.0, $r['days']);
		$this->assertSame(1, $r['covered_days']);
	}

	public function testNonFiniteFullDaysSanitisedToZero(): void
	{
		$r = VacationProrationService::computeProration(
			2026,
			INF,
			null,
			null,
			Constants::VACATION_PRORATION_METHOD_TWELFTHS
		);

		$this->assertSame(0.0, $r['days']);
		$this->assertSame(0.0, $r['full_days']);
	}

	public function testNormalizeMethodFallsBackToTwelfths(): void
	{
		$this->assertSame(Constants::VACATION_PRORATION_METHOD_TWELFTHS, VacationProrationService::normalizeMethod('garbage'));
		$this->assertSame(Constants::VACATION_PRORATION_METHOD_TWELFTHS, VacationProrationService::normalizeMethod(''));
		$this->assertSame(Constants::VACATION_PRORATION_METHOD_DAILY, VacationProrationService::normalizeMethod('  DAILY  '));
		$this->assertSame(Constants::VACATION_PRORATION_METHOD_TWELFTHS, VacationProrationService::normalizeMethod('TWELFTHS'));
	}

	public function testGetConfiguredMethodReadsAppConfig(): void
	{
		$employment = $this->createMock(UserEmploymentSettingsService::class);
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')
			->with('arbeitszeitcheck', Constants::CONFIG_VACATION_PRORATION_METHOD, Constants::DEFAULT_VACATION_PRORATION_METHOD)
			->willReturn('daily');

		$service = new VacationProrationService($employment, $config);

		$this->assertSame(Constants::VACATION_PRORATION_METHOD_DAILY, $service->getConfiguredMethod());
	}

	public function testProrateForYearUsesEmploymentDatesAndConfiguredMethod(): void
	{
		$employment = $this->createMock(UserEmploymentSettingsService::class);
		$employment->method('getEmploymentStart')->willReturn($this->d('2026-05-01'));
		$employment->method('getEmploymentEnd')->willReturn(null);

		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturn(Constants::VACATION_PRORATION_METHOD_TWELFTHS);

		$service = new VacationProrationService($employment, $config);
		$r = $service->prorateForYear('alice', 2026, 30.0);

		$this->assertSame(20.0, $r['days']);
		$this->assertSame(8, $r['months_covered']);
		$this->assertSame('2026-05-01', $r['employment_start']);
	}
}
