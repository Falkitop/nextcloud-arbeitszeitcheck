<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Service;

use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Db\AuditLogMapper;
use OCA\ArbeitszeitCheck\Db\UserOvertimeYearBalance;
use OCA\ArbeitszeitCheck\Db\UserOvertimeYearBalanceMapper;
use OCA\ArbeitszeitCheck\Db\UserSettingsMapper;

/**
 * Per-user overtime tracking-from date and opening balance (Eröffnungssaldo).
 */
class UserOvertimeSettingsService
{
	public function __construct(
		private readonly UserSettingsMapper $userSettingsMapper,
		private readonly UserOvertimeYearBalanceMapper $overtimeYearBalanceMapper,
		private readonly AuditLogMapper $auditLogMapper,
	) {
	}

	public function getTrackingFrom(string $userId): ?\DateTimeImmutable
	{
		$raw = $this->userSettingsMapper->getStringSetting($userId, Constants::SETTING_OVERTIME_TRACKING_FROM, '');
		if ($raw === '') {
			return null;
		}
		try {
			$dt = new \DateTimeImmutable($raw);
			return $dt->setTime(0, 0, 0);
		} catch (\Throwable $e) {
			return null;
		}
	}

	public function setTrackingFrom(string $userId, ?\DateTimeImmutable $date, string $actorUserId): void
	{
		$oldRaw = $this->userSettingsMapper->getStringSetting($userId, Constants::SETTING_OVERTIME_TRACKING_FROM, '');
		$newRaw = $date !== null ? $date->format('Y-m-d') : '';

		if ($oldRaw === $newRaw) {
			return;
		}

		if ($newRaw === '') {
			$this->userSettingsMapper->deleteSetting($userId, Constants::SETTING_OVERTIME_TRACKING_FROM);
		} else {
			$this->userSettingsMapper->setSetting($userId, Constants::SETTING_OVERTIME_TRACKING_FROM, $newRaw);
		}

		$this->auditLogMapper->logAction(
			$userId,
			'user_overtime_tracking_from_updated',
			'user',
			null,
			['tracking_from' => $oldRaw !== '' ? $oldRaw : null],
			['tracking_from' => $newRaw !== '' ? $newRaw : null],
			$actorUserId
		);
	}

	public function getOpeningBalanceHours(string $userId, int $year): float
	{
		return $this->overtimeYearBalanceMapper->getOpeningBalanceHours($userId, $year);
	}

	public function setOpeningBalance(string $userId, int $year, float $hours, string $actorUserId): UserOvertimeYearBalance
	{
		$oldHours = $this->overtimeYearBalanceMapper->getOpeningBalanceHours($userId, $year);
		$entity = $this->overtimeYearBalanceMapper->upsert($userId, $year, $hours);

		if (abs($oldHours - (float)$entity->getOpeningBalanceHours()) > 0.001) {
			$this->auditLogMapper->logAction(
				$userId,
				'user_overtime_opening_balance_updated',
				'user',
				null,
				['year' => $year, 'opening_balance_hours' => $oldHours],
				['year' => $year, 'opening_balance_hours' => (float)$entity->getOpeningBalanceHours()],
				$actorUserId
			);
		}

		return $entity;
	}

	/**
	 * Effective YTD start for a calendar year: max(Jan 1, tracking_from) when tracking is set.
	 */
	public function resolveEffectiveYearStart(string $userId, int $year): \DateTime
	{
		$yearStart = new \DateTime(sprintf('%04d-01-01 00:00:00', $year));
		$trackingFrom = $this->getTrackingFrom($userId);
		if ($trackingFrom === null) {
			return $yearStart;
		}
		$trackingMutable = \DateTime::createFromImmutable($trackingFrom);
		if ($trackingMutable > $yearStart) {
			return $trackingMutable;
		}
		return $yearStart;
	}

	public function countUsersWithTrackingFrom(): int
	{
		return $this->userSettingsMapper->countDistinctUsersWithNonEmptySetting(
			Constants::SETTING_OVERTIME_TRACKING_FROM
		);
	}

	public function hasTrackingFrom(string $userId): bool
	{
		return $this->getTrackingFrom($userId) !== null;
	}

	/**
	 * User IDs that have an overtime tracking start date configured (non-empty).
	 *
	 * Returned as a list; callers wanting O(1) membership tests should flip to an
	 * `array_fill_keys($ids, true)` lookup.
	 *
	 * @return list<string>
	 */
	public function listUserIdsWithTrackingFrom(): array
	{
		return $this->userSettingsMapper->findUserIdsWithNonEmptySetting(
			Constants::SETTING_OVERTIME_TRACKING_FROM
		);
	}
}
