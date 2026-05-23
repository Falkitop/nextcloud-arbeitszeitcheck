<?php

declare(strict_types=1);

/**
 * Builds consistent absence notification parameter payloads.
 *
 * @copyright Copyright (c) 2025, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Util;

use OCA\ArbeitszeitCheck\Db\Absence;

final class AbsenceNotificationPayload
{
	/**
	 * @return array{id: int|null, absence_id: int|null, type: string, start_date: string|null, end_date: string|null, days: float}
	 */
	public static function fromAbsence(Absence $absence, float $workingDays): array
	{
		$startDate = $absence->getStartDate();
		$endDate = $absence->getEndDate();
		$id = $absence->getId();

		return [
			'id' => $id,
			'absence_id' => $id,
			'type' => $absence->getType(),
			'start_date' => $startDate ? $startDate->format('Y-m-d') : null,
			'end_date' => $endDate ? $endDate->format('Y-m-d') : null,
			'days' => $workingDays,
		];
	}
}
