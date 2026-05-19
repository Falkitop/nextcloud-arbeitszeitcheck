<?php

declare(strict_types=1);

/**
 * Overtime service for the arbeitszeitcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Service;

use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Db\TimeEntryMapper;
use OCA\ArbeitszeitCheck\Db\WorkingTimeModelMapper;
use OCA\ArbeitszeitCheck\Db\UserWorkingTimeModelMapper;
use OCA\ArbeitszeitCheck\Db\TimeEntry;
use OCP\IL10N;

/**
 * OvertimeService for calculating overtime based on working time models
 */
class OvertimeService
{
	private TimeEntryMapper $timeEntryMapper;
	private WorkingTimeModelMapper $workingTimeModelMapper;
	private UserWorkingTimeModelMapper $userWorkingTimeModelMapper;
	private IL10N $l10n;
	private HolidayService $holidayCalendarService;
	private UserOvertimeSettingsService $overtimeSettingsService;

	public function __construct(
		TimeEntryMapper $timeEntryMapper,
		WorkingTimeModelMapper $workingTimeModelMapper,
		UserWorkingTimeModelMapper $userWorkingTimeModelMapper,
		IL10N $l10n,
		HolidayService $holidayCalendarService,
		UserOvertimeSettingsService $overtimeSettingsService,
	) {
		$this->timeEntryMapper = $timeEntryMapper;
		$this->workingTimeModelMapper = $workingTimeModelMapper;
		$this->userWorkingTimeModelMapper = $userWorkingTimeModelMapper;
		$this->l10n = $l10n;
		$this->holidayCalendarService = $holidayCalendarService;
		$this->overtimeSettingsService = $overtimeSettingsService;
	}

	/**
	 * Calculate overtime for a user for a specific period
	 *
	 * @param string $userId User ID
	 * @param \DateTime $startDate Start date
	 * @param \DateTime $endDate End date
	 * @param bool $calculateCumulative Whether to calculate cumulative balance
	 * @return array Overtime data including total hours, required hours, overtime hours, and balance
	 */
	public function calculateOvertime(string $userId, \DateTime $startDate, \DateTime $endDate, bool $calculateCumulative = true): array
	{
		$startDate = clone $startDate;
		$endDate = clone $endDate;
		$startDate->setTime(0, 0, 0);
		$endDate->setTime(23, 59, 59);

		$yearOfStart = (int)$startDate->format('Y');
		$effectiveYTDStart = $this->overtimeSettingsService->resolveEffectiveYearStart($userId, $yearOfStart);
		$opening = $this->overtimeSettingsService->getOpeningBalanceHours($userId, $yearOfStart);
		$trackingFrom = $this->overtimeSettingsService->getTrackingFrom($userId);

		[$dailyHours, $weeklyHours] = $this->resolveContractHours($userId);

		$periodStart = clone $startDate;
		if ($periodStart < $effectiveYTDStart) {
			$periodStart = clone $effectiveYTDStart;
		}

		$timeEntries = $this->timeEntryMapper->findByUserAndDateRange($userId, $periodStart, $endDate);

		$totalHoursWorked = 0.0;
		foreach ($timeEntries as $entry) {
			if ($entry->getStatus() === TimeEntry::STATUS_COMPLETED && $entry->getEndTime() !== null) {
				$totalHoursWorked += $entry->getWorkingDurationHours();
			}
		}

		$requiredHours = $this->calculateRequiredHours($userId, $periodStart, $endDate, $dailyHours, $weeklyHours);
		$overtimeHours = $totalHoursWorked - $requiredHours;

		$carryInDelta = 0.0;
		if ($calculateCumulative) {
			try {
				$carryInDelta = $this->getCumulativeWorkDelta($userId, $effectiveYTDStart, $startDate);
			} catch (\Throwable $e) {
				\OCP\Log\logger('arbeitszeitcheck')->error('Error calculating cumulative overtime balance: ' . $e->getMessage());
			}
		}

		$balanceBefore = $opening + $carryInDelta;
		$balanceAfter = $balanceBefore + $overtimeHours;

		$impliedDailyHours = $weeklyHours > 0 ? round($weeklyHours / 5, 2) : 0.0;

		return [
			'period_start' => $startDate->format('Y-m-d'),
			'period_end' => $endDate->format('Y-m-d'),
			'total_hours_worked' => round($totalHoursWorked, 2),
			'required_hours' => round($requiredHours, 2),
			'overtime_hours' => round($overtimeHours, 2),
			'cumulative_balance_before' => round($balanceBefore, 2),
			'cumulative_balance_after' => round($balanceAfter, 2),
			'cumulative_balance' => round($balanceAfter, 2),
			'daily_hours' => $dailyHours,
			'weekly_hours' => $weeklyHours,
			'implied_daily_hours' => $impliedDailyHours,
			'required_hours_basis' => 'weekly_contract',
			'working_days' => $this->countWorkingDays($userId, $periodStart, $endDate),
			'effective_tracking_from' => $trackingFrom !== null ? $trackingFrom->format('Y-m-d') : null,
			'opening_balance_hours' => round($opening, 2),
			'algorithm_version' => Constants::OVERTIME_ALGORITHM_VERSION,
		];
	}

	/**
	 * Calculate overtime for current month
	 */
	public function calculateMonthlyOvertime(string $userId): array
	{
		$now = new \DateTime();
		$startDate = new \DateTime($now->format('Y-m-01'));
		$startDate->setTime(0, 0, 0);
		$endDate = clone $now;
		$endDate->setTime(23, 59, 59);

		return $this->calculateOvertime($userId, $startDate, $endDate);
	}

	/**
	 * Calculate overtime for current year
	 */
	public function calculateYearlyOvertime(string $userId): array
	{
		$now = new \DateTime();
		$startDate = new \DateTime($now->format('Y-01-01'));
		$startDate->setTime(0, 0, 0);
		$endDate = clone $now;
		$endDate->setTime(23, 59, 59);

		return $this->calculateOvertime($userId, $startDate, $endDate);
	}

	/**
	 * Work delta from $from (inclusive) to $to (exclusive at day boundary).
	 */
	public function getCumulativeWorkDelta(string $userId, \DateTime $from, \DateTime $to): float
	{
		$fromCopy = clone $from;
		$fromCopy->setTime(0, 0, 0);
		$toCopy = clone $to;
		$toCopy->setTime(0, 0, 0);

		if ($fromCopy >= $toCopy) {
			return 0.0;
		}

		$overtimeData = $this->calculateOvertime($userId, $fromCopy, $toCopy, false);

		return $overtimeData['overtime_hours'];
	}

	/**
	 * Get cumulative overtime balance up to a specific date (legacy name: returns work delta only).
	 */
	public function getCumulativeOvertimeBalance(string $userId, \DateTime $beforeDate): float
	{
		$yearOfBefore = (int)$beforeDate->format('Y');
		$effectiveStart = $this->overtimeSettingsService->resolveEffectiveYearStart($userId, $yearOfBefore);

		return $this->getCumulativeWorkDelta($userId, $effectiveStart, $beforeDate);
	}

	private function calculateRequiredHours(string $userId, \DateTime $startDate, \DateTime $endDate, float $dailyHours, float $weeklyHours): float
	{
		$workingDays = $this->countWorkingDays($userId, $startDate, $endDate);
		$weeks = $workingDays / 5.0;

		return $weeks * $weeklyHours;
	}

	private function countWorkingDays(string $userId, \DateTime $startDate, \DateTime $endDate): float
	{
		return $this->holidayCalendarService->computeWorkingDaysForUser($userId, $startDate, $endDate);
	}

	/**
	 * @return array{0: float, 1: float} [dailyHours, weeklyHours]
	 */
	private function resolveContractHours(string $userId): array
	{
		$dailyHours = 8.0;
		$weeklyHours = 40.0;

		$userModel = $this->userWorkingTimeModelMapper->findCurrentByUser($userId);
		if ($userModel) {
			try {
				$model = $this->workingTimeModelMapper->find($userModel->getWorkingTimeModelId());
				$dailyHours = $model->getDailyHours();
				$weeklyHours = $model->getWeeklyHours();
			} catch (\Throwable $e) {
				// Model not found, use defaults
			}
		}

		return [$dailyHours, $weeklyHours];
	}

	public function getOvertimeBalance(string $userId): float
	{
		$now = new \DateTime();
		$yearStart = new \DateTime($now->format('Y-01-01'));
		$yearStart->setTime(0, 0, 0);
		$now->setTime(23, 59, 59);

		$overtimeData = $this->calculateOvertime($userId, $yearStart, $now);

		return $overtimeData['cumulative_balance_after'];
	}

	public function getDailyOvertime(string $userId, ?\DateTime $date = null): array
	{
		if ($date === null) {
			$date = new \DateTime();
		}

		$startDate = clone $date;
		$startDate->setTime(0, 0, 0);
		$endDate = clone $date;
		$endDate->setTime(23, 59, 59);

		return $this->calculateOvertime($userId, $startDate, $endDate);
	}

	public function getWeeklyOvertime(string $userId, ?\DateTime $weekStart = null): array
	{
		if ($weekStart === null) {
			$weekStart = new \DateTime();
			$dayOfWeek = (int)$weekStart->format('w');
			$weekStart->modify('-' . $dayOfWeek . ' days');
		}

		$weekStart->setTime(0, 0, 0);
		$weekEnd = clone $weekStart;
		$weekEnd->modify('+6 days');
		$weekEnd->setTime(23, 59, 59);

		return $this->calculateOvertime($userId, $weekStart, $weekEnd);
	}
}
