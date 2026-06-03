<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Support;

use OCA\ArbeitszeitCheck\Support\GermanStatutoryHolidayCatalog;
use PHPUnit\Framework\TestCase;

class GermanStatutoryHolidayCatalogTest extends TestCase
{
	public function testBrandenburgHasReformationDayNorthRhineWestphaliaDoesNot(): void
	{
		$year = 2026;
		$bb = GermanStatutoryHolidayCatalog::getStatutoryHolidaysForStateAndYear('BB', $year);
		$nw = GermanStatutoryHolidayCatalog::getStatutoryHolidaysForStateAndYear('NW', $year);

		$this->assertArrayHasKey('2026-10-31', $bb);
		$this->assertArrayNotHasKey('2026-10-31', $nw);
	}

	public function testNorthRhineWestphaliaHasCorpusChristi(): void
	{
		$nw = GermanStatutoryHolidayCatalog::getStatutoryHolidaysForStateAndYear('NW', 2026);
		$this->assertContains('Corpus Christi', $nw);
	}

	public function testBerlinAndMecklenburgHaveWomensDay(): void
	{
		$be = GermanStatutoryHolidayCatalog::getStatutoryHolidaysForStateAndYear('BE', 2026);
		$mv = GermanStatutoryHolidayCatalog::getStatutoryHolidaysForStateAndYear('MV', 2026);
		$nw = GermanStatutoryHolidayCatalog::getStatutoryHolidaysForStateAndYear('NW', 2026);
		$this->assertArrayHasKey('2026-03-08', $be);
		$this->assertArrayHasKey('2026-03-08', $mv);
		$this->assertArrayNotHasKey('2026-03-08', $nw);
	}

	/**
	 * Locks the statewide statutory count per Bundesland for a current year so a
	 * regression in the catalog (missing/extra day) fails loudly.
	 */
	public function testStatewideCountsPerBundesland2026(): void
	{
		$expected = [
			'BW' => 12, 'BY' => 12, 'BE' => 10, 'BB' => 10, 'HB' => 10, 'HH' => 10,
			'HE' => 10, 'MV' => 11, 'NI' => 10, 'NW' => 11, 'RP' => 11, 'SL' => 12,
			'SN' => 11, 'ST' => 11, 'SH' => 10, 'TH' => 11,
		];
		foreach ($expected as $state => $count) {
			$cal = GermanStatutoryHolidayCatalog::getStatutoryHolidaysForStateAndYear($state, 2026);
			$this->assertCount($count, $cal, "$state 2026 statutory count mismatch");
		}
	}

	/**
	 * Sachsen-Anhalt (issue #13): Epiphany is statutory; Corpus Christi and All Saints are not.
	 */
	public function testSaxonyAnhaltStatutoryHolidays2026(): void
	{
		$st = GermanStatutoryHolidayCatalog::getStatutoryHolidaysForStateAndYear('ST', 2026);

		$this->assertArrayHasKey('2026-01-06', $st);
		$this->assertSame('Epiphany', $st['2026-01-06']);
		$this->assertArrayHasKey('2026-10-31', $st);
		$this->assertSame('Reformation Day', $st['2026-10-31']);

		$this->assertArrayNotHasKey('2026-06-04', $st);
		$this->assertNotContains('Corpus Christi', $st);
		$this->assertArrayNotHasKey('2026-11-01', $st);
		$this->assertNotContains('All Saints', $st);
		$this->assertCount(11, $st);
	}

	public function testEpiphanyOnlyInBwByStNotNw(): void
	{
		foreach (['BW', 'BY', 'ST'] as $state) {
			$cal = GermanStatutoryHolidayCatalog::getStatutoryHolidaysForStateAndYear($state, 2026);
			$this->assertArrayHasKey('2026-01-06', $cal, "$state must observe Epiphany");
		}
		foreach (['NW', 'BE', 'HE', 'TH'] as $state) {
			$cal = GermanStatutoryHolidayCatalog::getStatutoryHolidaysForStateAndYear($state, 2026);
			$this->assertArrayNotHasKey('2026-01-06', $cal, "$state must not observe Epiphany");
		}
	}

	public function testSaarlandHasAssumptionDayButBavariaDoesNotAtStateLevel(): void
	{
		$sl = GermanStatutoryHolidayCatalog::getStatutoryHolidaysForStateAndYear('SL', 2026);
		$by = GermanStatutoryHolidayCatalog::getStatutoryHolidaysForStateAndYear('BY', 2026);
		$this->assertArrayHasKey('2026-08-15', $sl);
		$this->assertArrayNotHasKey('2026-08-15', $by);
	}

	public function testThuringiaHasWorldChildrensDay(): void
	{
		$th = GermanStatutoryHolidayCatalog::getStatutoryHolidaysForStateAndYear('TH', 2026);
		$nw = GermanStatutoryHolidayCatalog::getStatutoryHolidaysForStateAndYear('NW', 2026);
		$this->assertArrayHasKey('2026-09-20', $th);
		$this->assertArrayNotHasKey('2026-09-20', $nw);
	}

	public function testSaxonyHasRepentanceDayOnWednesdayBeforeNov23(): void
	{
		$sn = GermanStatutoryHolidayCatalog::getStatutoryHolidaysForStateAndYear('SN', 2026);
		// 23 Nov 2026 is a Monday → the Wednesday before is 18 Nov 2026.
		$this->assertArrayHasKey('2026-11-18', $sn);
		$this->assertSame('Repentance and Prayer Day', $sn['2026-11-18']);
		$this->assertSame(3, (int)(new \DateTime('2026-11-18'))->format('N'), 'Must be a Wednesday');

		$nw = GermanStatutoryHolidayCatalog::getStatutoryHolidaysForStateAndYear('NW', 2026);
		$this->assertArrayNotHasKey('2026-11-18', $nw);
	}

	/**
	 * Repentance Day must always land on the Wednesday in [16 Nov, 22 Nov]
	 * across a span of years (date-arithmetic regression guard).
	 */
	public function testRepentanceDayAlwaysWednesdayInWindow(): void
	{
		for ($year = 2024; $year <= 2035; $year++) {
			$sn = GermanStatutoryHolidayCatalog::getStatutoryHolidaysForStateAndYear('SN', $year);
			$match = null;
			foreach ($sn as $date => $name) {
				if ($name === 'Repentance and Prayer Day') {
					$match = $date;
					break;
				}
			}
			$this->assertNotNull($match, "Saxony must have Repentance Day in $year");
			$d = new \DateTime($match);
			$this->assertSame(3, (int)$d->format('N'), "Repentance Day $year must be a Wednesday");
			$day = (int)$d->format('j');
			$this->assertGreaterThanOrEqual(16, $day);
			$this->assertLessThanOrEqual(22, $day);
		}
	}

	/**
	 * Bavaria vs North Rhine-Westphalia for an Easter year (P1 requirement):
	 * both share the nationwide + Easter-linked + Corpus Christi + All Saints
	 * set, but BY additionally has Epiphany while NW does not.
	 */
	public function testBavariaVsNorthRhineWestphalia2026(): void
	{
		$by = GermanStatutoryHolidayCatalog::getStatutoryHolidaysForStateAndYear('BY', 2026);
		$nw = GermanStatutoryHolidayCatalog::getStatutoryHolidaysForStateAndYear('NW', 2026);

		// Shared days (Easter 2026 = 5 Apr → Good Friday 3 Apr, Easter Mon 6 Apr,
		// Ascension 14 May, Whit Monday 25 May, Corpus Christi 4 Jun).
		foreach (['2026-01-01', '2026-04-03', '2026-04-06', '2026-05-01', '2026-05-14', '2026-05-25', '2026-06-04', '2026-10-03', '2026-11-01', '2026-12-25', '2026-12-26'] as $shared) {
			$this->assertArrayHasKey($shared, $by, "BY missing $shared");
			$this->assertArrayHasKey($shared, $nw, "NW missing $shared");
		}

		// BY-only: Epiphany. NW-only: none of these extra.
		$this->assertArrayHasKey('2026-01-06', $by);
		$this->assertArrayNotHasKey('2026-01-06', $nw);

		$this->assertCount(12, $by, 'BY 2026 should expose 12 statewide statutory days');
		$this->assertCount(11, $nw, 'NW 2026 should expose 11 statutory days');
	}
}
