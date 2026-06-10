<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Service;

use OCA\ArbeitszeitCheck\AppInfo\Application;
use OCP\IDateTimeFormatter;
use OCP\IDateTimeZone;
use OCP\IUserSession;
use OCP\L10N\IFactory;

/**
 * Locale and timezone hints for page shell and PHP templates.
 */
class LocaleFormatService
{
	public function __construct(
		private readonly IFactory $l10nFactory,
		private readonly IDateTimeFormatter $dateTimeFormatter,
		private readonly IUserSession $userSession,
		private readonly IDateTimeZone $dateTimeZone,
		private readonly TimeZoneService $timeZoneService,
	) {
	}

	public function canonicalHtmlLangFromLocaleString(?string $rawLocale): string
	{
		$locale = strtolower(trim((string)$rawLocale));
		if ($locale === '') {
			return 'en-US';
		}
		$locale = str_replace('_', '-', $locale);
		if (preg_match('/^[a-z]{2}-[a-z]{2}$/', $locale) !== 1) {
			return match ($locale) {
				'de' => 'de-DE',
				'en' => 'en-US',
				'fr' => 'fr-FR',
				'es' => 'es-ES',
				'da' => 'da-DK',
				'nl' => 'nl-NL',
				'it' => 'it-IT',
				'pl' => 'pl-PL',
				'pt' => 'pt-PT',
				default => 'en-US',
			};
		}
		$parts = explode('-', $locale);

		return strtolower($parts[0]) . '-' . strtoupper($parts[1]);
	}

	/**
	 * @return array{locale: string, htmlLang: string, timezone: string, displayTimezone: string}
	 */
	public function clientHints(): array
	{
		$user = $this->userSession->getUser();
		$locale = '';
		if ($user !== null) {
			$locale = (string)$this->l10nFactory->getUserLanguage($user);
		}
		if ($locale === '') {
			$locale = (string)$this->l10nFactory->findLanguage(Application::APP_ID);
		}
		$htmlLang = $this->canonicalHtmlLangFromLocaleString($locale);

		try {
			$displayTz = $this->dateTimeZone->getTimeZone();
			$displayName = $displayTz instanceof \DateTimeZone
				? $displayTz->getName()
				: (is_string($displayTz) && $displayTz !== '' ? $displayTz : 'UTC');
		} catch (\Throwable) {
			$displayName = $this->timeZoneService->storageTimeZone()->getName();
		}

		return [
			'locale' => $htmlLang,
			'htmlLang' => $htmlLang,
			'timezone' => $displayName,
			'displayTimezone' => $displayName,
		];
	}

	public function formatDate(string $isoDate, string $length = 'medium'): string
	{
		try {
			$ts = (new \DateTimeImmutable($isoDate))->getTimestamp();
		} catch (\Throwable) {
			return $isoDate;
		}

		return $this->dateTimeFormatter->formatDate($ts, $length);
	}

	public function formatYearMonth(string $yearMonth): string
	{
		if (!preg_match('/^\d{4}-\d{2}$/', $yearMonth)) {
			return $yearMonth;
		}
		try {
			$ts = (new \DateTimeImmutable($yearMonth . '-01'))->getTimestamp();
		} catch (\Throwable) {
			return $yearMonth;
		}

		return $this->dateTimeFormatter->formatDate($ts, 'medium');
	}
}
