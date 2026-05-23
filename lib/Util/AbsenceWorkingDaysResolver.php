<?php

declare(strict_types=1);

/**
 * Resolves working-day counts for absence notifications (including legacy rows).
 *
 * @copyright Copyright (c) 2025, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Util;

use OCA\ArbeitszeitCheck\Db\AbsenceMapper;
use OCA\ArbeitszeitCheck\Service\HolidayService;
use OCP\AppFramework\Db\DoesNotExistException;

class AbsenceWorkingDaysResolver
{
	public function __construct(
		private AbsenceMapper $absenceMapper,
		private HolidayService $holidayService,
	) {
	}

	/**
	 * Resolve working days from notification parameters, falling back to the absence record.
	 *
	 * @param array<string, mixed> $parameters Merged subject + message parameters
	 */
	public function resolveFromNotificationParameters(array $parameters): float
	{
		if (isset($parameters['days']) && is_numeric($parameters['days'])) {
			$days = (float)$parameters['days'];
			if ($days > 0) {
				return $days;
			}
		}

		$absenceId = $parameters['absence_id'] ?? $parameters['id'] ?? null;
		if ($absenceId === null || !is_numeric($absenceId)) {
			return isset($parameters['days']) && is_numeric($parameters['days'])
				? (float)$parameters['days']
				: 0.0;
		}

		try {
			$absence = $this->absenceMapper->find((int)$absenceId);
		} catch (DoesNotExistException) {
			return 0.0;
		}

		if ($absence->getDays() !== null && (float)$absence->getDays() > 0) {
			return (float)$absence->getDays();
		}

		$start = $absence->getStartDate();
		$end = $absence->getEndDate();
		if ($start !== null && $end !== null) {
			return $this->holidayService->computeWorkingDaysForUser(
				$absence->getUserId(),
				$start,
				$end
			);
		}

		return 0.0;
	}
}
