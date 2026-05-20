<?php

declare(strict_types=1);

/**
 * Strict four-digit calendar year for overtime / vacation opening balances.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Support;

final class OpeningBalanceYearValidator
{
	public const MIN_YEAR = 2000;
	public const MAX_YEAR = 2100;

	/**
	 * @return array{int, null}|array{null, string} [year, null] or [null, error message key fragment]
	 */
	public static function parse(mixed $raw): array
	{
		if (!is_scalar($raw)) {
			return [null, 'invalid'];
		}
		$trimmed = trim((string)$raw);
		if (!preg_match('/^\d{4}$/', $trimmed)) {
			return [null, 'invalid'];
		}
		$year = (int)$trimmed;
		if ($year < self::MIN_YEAR || $year > self::MAX_YEAR) {
			return [null, 'range'];
		}

		return [$year, null];
	}
}
