<?php

declare(strict_types=1);

/**
 * Strict `YYYY-MM-DD` parsing for admin and HR APIs (no time component, no
 * locale-specific formats). Rejects PHP {@see \DateTimeImmutable::createFromFormat}
 * overflow normalisation (e.g. `2026-02-30` → March) by requiring an exact
 * round-trip on the formatted calendar day.
 *
 * @copyright Copyright (c) 2026 Alexander Mäule
 * @license AGPL-3.0-or-later
 */
namespace OCA\ArbeitszeitCheck\Support;

final class StrictYmdDates {
	/**
	 * @return \DateTime|null Null when empty after trim, malformed, or not a real calendar day.
	 */
	public static function parseRequired(string $raw): ?\DateTime {
		$trimmed = trim($raw);
		if ($trimmed === '') {
			return null;
		}
		$immutable = \DateTimeImmutable::createFromFormat('!Y-m-d', $trimmed);
		if ($immutable === false || $immutable->format('Y-m-d') !== $trimmed) {
			return null;
		}

		return \DateTime::createFromImmutable($immutable);
	}
}
