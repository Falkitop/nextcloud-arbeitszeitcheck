<?php

declare(strict_types=1);

/**
 * Central policy for whether an employee may delete a time entry.
 *
 * Combines entity rules, approval workflow config, and month-closure guards so
 * the API, deletion-impact endpoint, and list template stay in sync.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Service;

use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Db\TimeEntry;
use OCA\ArbeitszeitCheck\Exception\MonthFinalizedException;
use OCP\IConfig;
use OCP\IL10N;

class TimeEntryDeletionPolicy
{
	public function __construct(
		private IConfig $config,
		private MonthClosureGuard $monthClosureGuard,
		private IL10N $l10n,
	) {
	}

	/**
	 * @return array{
	 *     canDelete: bool,
	 *     blockCode: ?string,
	 *     blockMessage: ?string,
	 *     warnings: list<string>,
	 *     isManualEntry: bool,
	 *     status: string
	 * }
	 */
	public function evaluate(TimeEntry $entry, int $editWindowDays = Constants::EDIT_WINDOW_DAYS): array
	{
		if ($entry->getStatus() === TimeEntry::STATUS_PENDING_APPROVAL
			&& $this->requiresChangeApproval()) {
			return $this->buildResult($entry, [
				'canDelete' => false,
				'blockCode' => 'cancel_correction_first',
				'blockMessage' => $this->l10n->t('Withdraw the pending correction first using “Withdraw” instead of deleting this entry.'),
			]);
		}

		try {
			$this->monthClosureGuard->assertTimeEntryMutable($entry);
		} catch (MonthFinalizedException $e) {
			return $this->buildResult($entry, [
				'canDelete' => false,
				'blockCode' => 'month_finalized',
				'blockMessage' => $this->l10n->t('This calendar month is finalized. Contact an administrator if a correction must be made.'),
			]);
		}

		if (!$entry->canDelete($editWindowDays)) {
			return $this->buildResult($entry, [
				'canDelete' => false,
				'blockCode' => $this->resolveEntityBlockCode($entry, $editWindowDays),
				'blockMessage' => $this->resolveEntityBlockMessage($entry, $editWindowDays),
			]);
		}

		$warnings = [];
		if ($entry->getStatus() === TimeEntry::STATUS_PENDING_APPROVAL) {
			$warnings[] = $this->l10n->t('This entry has a pending approval request. Deleting it will cancel the request.');
		}
		if (!$entry->getIsManualEntry()
			&& $entry->canEdit($editWindowDays)
			&& in_array($entry->getStatus(), [TimeEntry::STATUS_COMPLETED, TimeEntry::STATUS_PAUSED], true)) {
			$warnings[] = $this->l10n->t('To change start or end times, use Edit. Delete removes the entire entry permanently.');
		}
		if (!$entry->getIsManualEntry()
			&& in_array($entry->getStatus(), [TimeEntry::STATUS_COMPLETED, TimeEntry::STATUS_PAUSED], true)) {
			$warnings[] = $this->l10n->t('This clocked time entry will be permanently removed. The deletion is recorded in the audit log.');
		}

		return $this->buildResult($entry, [
			'canDelete' => true,
			'blockCode' => null,
			'blockMessage' => null,
			'warnings' => $warnings,
		]);
	}

	/**
	 * @param array<string, mixed> $fields
	 * @return array{
	 *     canDelete: bool,
	 *     blockCode: ?string,
	 *     blockMessage: ?string,
	 *     warnings: list<string>,
	 *     isManualEntry: bool,
	 *     status: string
	 * }
	 */
	private function buildResult(TimeEntry $entry, array $fields): array
	{
		return array_merge([
			'isManualEntry' => $entry->getIsManualEntry(),
			'status' => $entry->getStatus(),
			'warnings' => [],
			'canDelete' => false,
			'blockCode' => null,
			'blockMessage' => null,
		], $fields);
	}

	private function requiresChangeApproval(): bool
	{
		return $this->config->getAppValue('arbeitszeitcheck', Constants::CONFIG_TIME_ENTRY_CHANGES_REQUIRE_APPROVAL, '0') === '1';
	}

	private function resolveEntityBlockCode(TimeEntry $entry, int $editWindowDays): string
	{
		if ($entry->isLockedForEmployeeEdit()) {
			return 'manager_approved';
		}
		if (in_array($entry->getStatus(), [TimeEntry::STATUS_ACTIVE, TimeEntry::STATUS_BREAK], true)) {
			return 'session_active';
		}
		if (!$entry->getIsManualEntry() && $entry->getStatus() === TimeEntry::STATUS_COMPLETED) {
			return 'edit_window_expired';
		}

		return 'not_deletable';
	}

	private function resolveEntityBlockMessage(TimeEntry $entry, int $editWindowDays): string
	{
		if ($entry->isLockedForEmployeeEdit()) {
			return $this->l10n->t('This entry was approved by your manager and cannot be deleted.');
		}
		if (in_array($entry->getStatus(), [TimeEntry::STATUS_ACTIVE, TimeEntry::STATUS_BREAK], true)) {
			return $this->l10n->t('End this live session with clock out before deleting it.');
		}
		if (!$entry->getIsManualEntry() && $entry->getStatus() === TimeEntry::STATUS_COMPLETED) {
			return $this->l10n->t(
				'Clocked entries older than %d days cannot be deleted. Use “Request correction” instead.',
				[$editWindowDays]
			);
		}

		return $this->l10n->t('This time entry cannot be deleted.');
	}
}
