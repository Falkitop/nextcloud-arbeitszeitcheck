<?php

declare(strict_types=1);

/**
 * Interprets naive DATETIME strings from at_* tables in the app's configured timezone.
 *
 * Nextcloud's {@see \OCP\AppFramework\Db\Entity} maps SQL DATETIME columns with
 * {@see \DateTime::__construct(string)} which uses PHP's default timezone (often UTC in
 * containers). ArbeitszeitCheck stores wall-clock values in {@see Constants::CONFIG_APP_TIMEZONE}
 * (see migration Version1015Date20260415120000 and {@see TimeTrackingService} day windows).
 * Without this step, the same instant is mis-labelled as UTC and later shifted again when
 * converting to the user's display timezone — e.g. 09:00 Berlin stored as naive "09:00" was read
 * as 09:00 UTC and shown as 11:00 in Europe/Berlin.
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Service;

use OCA\ArbeitszeitCheck\Constants;

final class AppLocalNaiveDateTimeNormalizer
{
	/**
	 * Re-bind a {@see \DateTime} loaded from a naive SQL datetime to the storage timezone.
	 *
	 * Uses the calendar wall clock components (Y-m-d H:i:s) from $value and attaches $storageTz.
	 * If parsing fails, returns the original instance unchanged.
	 */
	public static function interpretSqlNaiveAsAppTimezone(\DateTime $value, \DateTimeZone $storageTz): \DateTime
	{
		$wall = $value->format('Y-m-d H:i:s');
		$parsed = \DateTime::createFromFormat('!Y-m-d H:i:s', $wall, $storageTz);
		if ($parsed === false) {
			return $value;
		}
		return $parsed;
	}

	/**
	 * Normalise naive SQL datetime columns in a raw associative row (e.g. fetchAll) to ISO-8601 with offset.
	 *
	 * Callers that bypass {@see TimeEntryMapper::mapRowToEntity()} still receive unambiguous instants.
	 *
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	public static function normalizeAtEntryDatetimeStringsInRow(array $row, \DateTimeZone $storageTz): array
	{
		foreach (['start_time', 'end_time', 'break_start_time', 'break_end_time', 'created_at', 'updated_at', 'approved_at'] as $col) {
			if (!\array_key_exists($col, $row)) {
				continue;
			}
			$v = $row[$col];
			if (!\is_string($v) || $v === '') {
				continue;
			}
			$parsed = \DateTime::createFromFormat('!Y-m-d H:i:s', $v, $storageTz);
			if ($parsed !== false) {
				$row[$col] = $parsed->format('c');
			}
		}
		return $row;
	}

	/**
	 * Parse datetime strings from HTTP/JSON clients. If PHP detects an explicit zone in the string
	 * ({@see date_parse()} is_localtime), that zone is used; otherwise the value is wall time in
	 * $naiveInThisZoneIfUnset (same semantics as stored at_* datetimes).
	 *
	 * @throws \InvalidArgumentException when the value cannot be parsed
	 */
	public static function parseFlexibleDateTime(string $value, \DateTimeZone $naiveInThisZoneIfUnset): \DateTime
	{
		$value = trim($value);
		if ($value === '') {
			throw new \InvalidArgumentException('Empty datetime value');
		}
		$info = date_parse($value);
		if (($info['error_count'] ?? 0) > 0) {
			throw new \InvalidArgumentException('Invalid datetime syntax');
		}
		$explicit = (($info['is_localtime'] ?? false) === true);
		if ($explicit) {
			return new \DateTime($value);
		}
		return new \DateTime($value, $naiveInThisZoneIfUnset);
	}

	public static function appStorageTimeZoneFromConfig(\OCP\IConfig $config): \DateTimeZone
	{
		$name = $config->getAppValue('arbeitszeitcheck', Constants::CONFIG_APP_TIMEZONE, 'Europe/Berlin');
		try {
			return new \DateTimeZone($name);
		} catch (\Throwable $e) {
			\OCP\Log\logger('arbeitszeitcheck')->warning(
				'Invalid app timezone in config; falling back to Europe/Berlin: ' . $name,
				['exception' => $e]
			);
			return new \DateTimeZone('Europe/Berlin');
		}
	}
}
