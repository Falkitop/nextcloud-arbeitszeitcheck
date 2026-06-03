<?php

declare(strict_types=1);

/**
 * Reference catalog of German statutory public holidays per Bundesland.
 *
 * Used only to seed at_holidays — never as a runtime overlay for working-day math.
 * Names are English msgids for IL10N in HolidayService.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Support;

final class GermanStatutoryHolidayCatalog
{
	/** Nationwide fixed-date holidays (all 16 Länder). */
	private const NATIONAL_FIXED = [
		'01-01' => 'New Year',
		'05-01' => 'Labour Day',
		'10-03' => 'Unity Day',
		'12-25' => 'Christmas',
		'12-26' => 'Second Christmas',
	];

	/** Reformation Day (31 Oct). Northern states (HB, HH, NI, SH) added it in 2018. */
	private const REFORMATION_STATES = ['BB', 'MV', 'SN', 'ST', 'TH', 'HB', 'HH', 'NI', 'SH'];

	/** Corpus Christi (Easter + 60 days). */
	private const CORPUS_CHRISTI_STATES = ['BW', 'BY', 'HE', 'NW', 'RP', 'SL'];

	/** All Saints (1 Nov). */
	private const ALL_SAINTS_STATES = ['BW', 'BY', 'NW', 'RP', 'SL'];

	/** International Women's Day (8 Mar) — Berlin (since 2019) and Mecklenburg-Vorpommern (since 2023). */
	private const WOMENS_DAY_STATES = ['BE', 'MV'];

	/** Epiphany / Heilige Drei Könige (6 Jan). */
	private const EPIPHANY_STATES = ['BW', 'BY', 'ST'];

	/**
	 * Assumption Day / Mariä Himmelfahrt (15 Aug).
	 *
	 * Statewide statutory only in Saarland. In Bavaria it is statutory solely in
	 * municipalities with a predominantly Catholic population (~1700 of 2056),
	 * which this state-level catalog cannot model reliably, so BY is intentionally
	 * excluded to avoid over-counting non-working days for the whole Bundesland.
	 */
	private const ASSUMPTION_STATES = ['SL'];

	/** World Children's Day / Weltkindertag (20 Sep) — Thuringia only (since 2019). */
	private const WORLD_CHILDRENS_DAY_STATES = ['TH'];

	/** Repentance and Prayer Day / Buß- und Bettag — Saxony only; Wednesday before 23 Nov. */
	private const REPENTANCE_DAY_STATES = ['SN'];

	/**
	 * @return array<string,string> date (Y-m-d) => English name (l10n msgid)
	 */
	public static function getStatutoryHolidaysForStateAndYear(string $state, int $year): array
	{
		$state = strtoupper(trim($state));
		$holidays = [];

		foreach (self::NATIONAL_FIXED as $md => $name) {
			$holidays[sprintf('%04d-%s', $year, $md)] = $name;
		}

		foreach (self::easterDependentHolidays($year) as $dateStr => $name) {
			$holidays[$dateStr] = $name;
		}

		if (in_array($state, self::EPIPHANY_STATES, true)) {
			$holidays[sprintf('%04d-01-06', $year)] = 'Epiphany';
		}

		if (in_array($state, self::REFORMATION_STATES, true)) {
			$holidays[sprintf('%04d-10-31', $year)] = 'Reformation Day';
		}

		if (in_array($state, self::CORPUS_CHRISTI_STATES, true)) {
			$easter = self::easterDate($year);
			$holidays[$easter->modify('+60 days')->format('Y-m-d')] = 'Corpus Christi';
		}

		if (in_array($state, self::ASSUMPTION_STATES, true)) {
			$holidays[sprintf('%04d-08-15', $year)] = 'Assumption Day';
		}

		if (in_array($state, self::ALL_SAINTS_STATES, true)) {
			$holidays[sprintf('%04d-11-01', $year)] = 'All Saints';
		}

		if (in_array($state, self::WORLD_CHILDRENS_DAY_STATES, true)) {
			$holidays[sprintf('%04d-09-20', $year)] = 'World Children\'s Day';
		}

		if (in_array($state, self::REPENTANCE_DAY_STATES, true)) {
			// Buß- und Bettag: the Wednesday immediately before 23 November.
			$nov23 = new \DateTimeImmutable(sprintf('%04d-11-23', $year));
			$holidays[$nov23->modify('last wednesday')->format('Y-m-d')] = 'Repentance and Prayer Day';
		}

		if (in_array($state, self::WOMENS_DAY_STATES, true)) {
			$holidays[sprintf('%04d-03-08', $year)] = 'International Women\'s Day';
		}

		ksort($holidays);

		return $holidays;
	}

	/**
	 * Easter-linked holidays observed in every Bundesland.
	 *
	 * @return array<string,string>
	 */
	private static function easterDependentHolidays(int $year): array
	{
		$easter = self::easterDate($year);

		return [
			$easter->modify('-2 days')->format('Y-m-d') => 'Good Friday',
			$easter->modify('+1 day')->format('Y-m-d') => 'Easter Monday',
			$easter->modify('+39 days')->format('Y-m-d') => 'Ascension',
			$easter->modify('+50 days')->format('Y-m-d') => 'Whit Monday',
		];
	}

	private static function easterDate(int $year): \DateTimeImmutable
	{
		$easterDays = \function_exists('easter_days') ? \easter_days($year) : self::easterDaysGauss($year);
		$march21 = new \DateTimeImmutable($year . '-03-21');

		return $march21->modify('+' . $easterDays . ' days');
	}

	/**
	 * Gauss algorithm for Easter (fallback when ext/calendar easter_days() is unavailable).
	 */
	private static function easterDaysGauss(int $year): int
	{
		$a = $year % 19;
		$b = (int)($year / 100);
		$c = $year % 100;
		$d = (int)($b / 4);
		$e = $b % 4;
		$f = (int)(($b + 8) / 25);
		$g = (int)(($b - $f + 1) / 3);
		$h = (19 * $a + $b - $d - $g + 15) % 30;
		$i = (int)($c / 4);
		$k = $c % 4;
		$l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
		$m = (int)(($a + 11 * $h + 22 * $l) / 451);
		$month = (int)(($h + $l - 7 * $m + 114) / 31);
		$day = (($h + $l - 7 * $m + 114) % 31) + 1;

		$march21 = new \DateTimeImmutable($year . '-03-21');
		$easterDate = new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));

		return (int)$march21->diff($easterDate)->days;
	}
}
