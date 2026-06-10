<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Service\Kiosk;

use OCA\ArbeitszeitCheck\AppInfo\Application;
use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Kiosk\KioskCrypto;
use OCP\IConfig;

class KioskSettingsService
{
	public function __construct(
		private readonly IConfig $config,
	) {
	}

	public function isKioskEnabled(): bool
	{
		return $this->config->getAppValue(Application::APP_ID, Constants::CONFIG_KIOSK_ENABLED, '0') === '1';
	}

	public function setKioskEnabled(bool $enabled): void
	{
		$this->config->setAppValue(Application::APP_ID, Constants::CONFIG_KIOSK_ENABLED, $enabled ? '1' : '0');
	}

	public function isUserKioskAllowed(string $userId): bool
	{
		if ($userId === '') {
			return false;
		}
		return $this->config->getUserValue($userId, Application::APP_ID, Constants::USER_PREF_KIOSK_ALLOWED, '0') === '1';
	}

	public function setUserKioskAllowed(string $userId, bool $allowed): void
	{
		$this->config->setUserValue($userId, Application::APP_ID, Constants::USER_PREF_KIOSK_ALLOWED, $allowed ? '1' : '0');
	}

	public function getRfidSalt(): string
	{
		$salt = (string)$this->config->getAppValue(Application::APP_ID, Constants::CONFIG_KIOSK_RFID_SALT, '');
		if ($salt === '') {
			$salt = bin2hex(random_bytes(32));
			$this->config->setAppValue(Application::APP_ID, Constants::CONFIG_KIOSK_RFID_SALT, $salt);
		}
		return $salt;
	}

	public function rfidLookupHash(string $uid): string
	{
		return KioskCrypto::rfidLookupHash(KioskCrypto::normalizeRfidUid($uid), $this->getRfidSalt());
	}
}
