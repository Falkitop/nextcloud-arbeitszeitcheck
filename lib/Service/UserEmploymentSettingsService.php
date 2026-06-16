<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2026 Alexander Mäule
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Service;

use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Db\AuditLogMapper;
use OCA\ArbeitszeitCheck\Db\UserSettingsMapper;

/**
 * Per-user employment period (Eintritts-/Austrittsdatum).
 *
 * The employment start and end dates are the sole basis for prorating the
 * annual vacation entitlement in partial employment years (see
 * {@see VacationProrationService}). They are stored as plain user settings —
 * exactly like the overtime "tracking from" Stichtag — so no schema migration
 * is required and an empty value cleanly means "no proration" (full annual
 * entitlement), preserving the historic behaviour for every existing user.
 *
 * Dates are normalised to midnight, date-only (`Y-m-d`). The invariant
 * `start <= end` is enforced on write whenever both are present.
 */
class UserEmploymentSettingsService
{
	public function __construct(
		private readonly UserSettingsMapper $userSettingsMapper,
		private readonly AuditLogMapper $auditLogMapper,
	) {
	}

	public function getEmploymentStart(string $userId): ?\DateTimeImmutable
	{
		return $this->readDate($userId, Constants::SETTING_EMPLOYMENT_START);
	}

	public function getEmploymentEnd(string $userId): ?\DateTimeImmutable
	{
		return $this->readDate($userId, Constants::SETTING_EMPLOYMENT_END);
	}

	/**
	 * Persist the employment start date. Passing null clears it.
	 *
	 * @throws \InvalidArgumentException when the resulting period would be
	 *         inverted (start after an existing end).
	 */
	public function setEmploymentStart(string $userId, ?\DateTimeImmutable $date, string $actorUserId): void
	{
		$date = $date?->setTime(0, 0, 0);
		$existingEnd = $this->getEmploymentEnd($userId);
		if ($date !== null && $existingEnd !== null && $date > $existingEnd) {
			throw new \InvalidArgumentException('Employment start date must not be after the employment end date.');
		}
		$this->writeDate(
			$userId,
			Constants::SETTING_EMPLOYMENT_START,
			$date,
			'user_employment_start_updated',
			$actorUserId
		);
	}

	/**
	 * Persist the employment end date. Passing null clears it.
	 *
	 * @throws \InvalidArgumentException when the resulting period would be
	 *         inverted (end before an existing start).
	 */
	public function setEmploymentEnd(string $userId, ?\DateTimeImmutable $date, string $actorUserId): void
	{
		$date = $date?->setTime(0, 0, 0);
		$existingStart = $this->getEmploymentStart($userId);
		if ($date !== null && $existingStart !== null && $date < $existingStart) {
			throw new \InvalidArgumentException('Employment end date must not be before the employment start date.');
		}
		$this->writeDate(
			$userId,
			Constants::SETTING_EMPLOYMENT_END,
			$date,
			'user_employment_end_updated',
			$actorUserId
		);
	}

	/**
	 * Atomically set both endpoints of the employment period, validating the
	 * `start <= end` invariant on the *combined* target so an operator can
	 * move both dates in a single save without tripping a transient
	 * intermediate violation.
	 *
	 * @throws \InvalidArgumentException when `start > end`.
	 */
	public function setEmploymentPeriod(
		string $userId,
		?\DateTimeImmutable $start,
		?\DateTimeImmutable $end,
		string $actorUserId,
	): void {
		$start = $start?->setTime(0, 0, 0);
		$end = $end?->setTime(0, 0, 0);
		if ($start !== null && $end !== null && $start > $end) {
			throw new \InvalidArgumentException('Employment start date must not be after the employment end date.');
		}
		// Clear end first when shrinking so a no-longer-valid existing end can
		// never block writing the new start, then write the validated pair.
		$this->writeDate($userId, Constants::SETTING_EMPLOYMENT_START, $start, 'user_employment_start_updated', $actorUserId);
		$this->writeDate($userId, Constants::SETTING_EMPLOYMENT_END, $end, 'user_employment_end_updated', $actorUserId);
	}

	private function readDate(string $userId, string $settingKey): ?\DateTimeImmutable
	{
		$raw = $this->userSettingsMapper->getStringSetting($userId, $settingKey, '');
		if ($raw === '') {
			return null;
		}
		try {
			return (new \DateTimeImmutable($raw))->setTime(0, 0, 0);
		} catch (\Throwable) {
			return null;
		}
	}

	private function writeDate(
		string $userId,
		string $settingKey,
		?\DateTimeImmutable $date,
		string $auditAction,
		string $actorUserId,
	): void {
		$oldRaw = $this->userSettingsMapper->getStringSetting($userId, $settingKey, '');
		$newRaw = $date !== null ? $date->format('Y-m-d') : '';
		if ($oldRaw === $newRaw) {
			return;
		}
		if ($newRaw === '') {
			$this->userSettingsMapper->deleteSetting($userId, $settingKey);
		} else {
			$this->userSettingsMapper->setSetting($userId, $settingKey, $newRaw);
		}
		$this->auditLogMapper->logAction(
			$userId,
			$auditAction,
			'user',
			null,
			[$settingKey => $oldRaw !== '' ? $oldRaw : null],
			[$settingKey => $newRaw !== '' ? $newRaw : null],
			$actorUserId
		);
	}
}
