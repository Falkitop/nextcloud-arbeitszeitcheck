<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Service;

use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Db\AuditLogMapper;
use OCA\ArbeitszeitCheck\Db\UserSettingsMapper;
use OCA\ArbeitszeitCheck\Exception\BusinessRuleException;
use OCA\ArbeitszeitCheck\Exception\TimeCaptureForbiddenException;
use OCP\IL10N;

/**
 * Per-employee control over clock stamping vs manual time entry creation.
 *
 * Both methods are enabled by default (missing settings). At least one method
 * must remain enabled; admins cannot disable both for the same employee.
 */
class TimeCaptureMethodService
{
	public function __construct(
		private readonly UserSettingsMapper $userSettingsMapper,
		private readonly AuditLogMapper $auditLogMapper,
		private readonly IL10N $l10n,
	) {
	}

	public function isClockStampingEnabled(string $userId): bool
	{
		return $this->userSettingsMapper->getBooleanSetting(
			$userId,
			Constants::SETTING_CLOCK_STAMPING_ENABLED,
			true
		);
	}

	public function isManualTimeEntryEnabled(string $userId): bool
	{
		return $this->userSettingsMapper->getBooleanSetting(
			$userId,
			Constants::SETTING_MANUAL_TIME_ENTRY_ENABLED,
			true
		);
	}

	/**
	 * @return array{clockStampingEnabled: bool, manualTimeEntryEnabled: bool}
	 */
	public function getSettings(string $userId): array
	{
		return [
			'clockStampingEnabled' => $this->isClockStampingEnabled($userId),
			'manualTimeEntryEnabled' => $this->isManualTimeEntryEnabled($userId),
		];
	}

	/**
	 * @param array{clockStampingEnabled?: bool, manualTimeEntryEnabled?: bool} $settings
	 */
	public function setSettings(string $userId, array $settings, string $actorUserId): array
	{
		$clockEnabled = array_key_exists('clockStampingEnabled', $settings)
			? (bool)$settings['clockStampingEnabled']
			: $this->isClockStampingEnabled($userId);
		$manualEnabled = array_key_exists('manualTimeEntryEnabled', $settings)
			? (bool)$settings['manualTimeEntryEnabled']
			: $this->isManualTimeEntryEnabled($userId);

		if (!$clockEnabled && !$manualEnabled) {
			throw new BusinessRuleException(
				$this->l10n->t('At least one time recording method must stay enabled for each employee.')
			);
		}

		$before = $this->getSettings($userId);
		$this->persistBooleanSetting($userId, Constants::SETTING_CLOCK_STAMPING_ENABLED, $clockEnabled);
		$this->persistBooleanSetting($userId, Constants::SETTING_MANUAL_TIME_ENTRY_ENABLED, $manualEnabled);
		$after = $this->getSettings($userId);

		if ($before !== $after) {
			$this->auditLogMapper->logAction(
				$userId,
				'user_time_capture_methods_updated',
				'user',
				null,
				$before,
				$after,
				$actorUserId
			);
		}

		return $after;
	}

	public function assertClockStampingAllowed(string $userId): void
	{
		if ($this->isClockStampingEnabled($userId)) {
			return;
		}

		throw new TimeCaptureForbiddenException(
			$this->l10n->t('Clock in/out (stamping) is not enabled for your account. Please contact your administrator.'),
			TimeCaptureForbiddenException::CODE_CLOCK_STAMPING_DISABLED,
		);
	}

	public function assertManualTimeEntryAllowed(string $userId): void
	{
		if ($this->isManualTimeEntryEnabled($userId)) {
			return;
		}

		throw new TimeCaptureForbiddenException(
			$this->l10n->t('Manual time entries are not enabled for your account. Please contact your administrator.'),
			TimeCaptureForbiddenException::CODE_MANUAL_TIME_ENTRY_DISABLED,
		);
	}

	private function persistBooleanSetting(string $userId, string $key, bool $enabled): void
	{
		if ($enabled) {
			// Default-on: remove explicit row so legacy installs stay enabled.
			$this->userSettingsMapper->deleteSetting($userId, $key);
			return;
		}

		$this->userSettingsMapper->setSetting($userId, $key, '0');
	}
}
