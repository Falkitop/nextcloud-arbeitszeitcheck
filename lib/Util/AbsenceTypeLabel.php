<?php

declare(strict_types=1);

/**
 * Localized absence type labels and working-day formatting for notifications and mail.
 *
 * @copyright Copyright (c) 2025, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Util;

use OCP\IL10N;

final class AbsenceTypeLabel
{
	/**
	 * Human-readable label for an absence type code (same strings as absences UI).
	 */
	public static function get(IL10N $l, string $type): string
	{
		$map = [
			'vacation' => $l->t('Vacation'),
			'sick_leave' => $l->t('Sick leave'),
			'personal_leave' => $l->t('Personal leave'),
			'parental_leave' => $l->t('Parental leave'),
			'special_leave' => $l->t('Special leave'),
			'unpaid_leave' => $l->t('Unpaid leave'),
			'home_office' => $l->t('Home office'),
			'business_trip' => $l->t('Business trip'),
		];

		return $map[$type] ?? $type;
	}

	/**
	 * Format working days for user-facing text (supports half-days, e.g. 0.5).
	 */
	public static function formatWorkingDays(IL10N $l, float $days): string
	{
		if ($days <= 0) {
			return $l->t('no working days');
		}

		$rounded = round($days, 2);
		if (abs($rounded - round($rounded)) < 0.001) {
			$count = (int)round($rounded);
			return $l->n('%n working day', '%n working days', $count);
		}

		return $l->t('%s working days', [number_format($rounded, 1, '.', '')]);
	}
}
