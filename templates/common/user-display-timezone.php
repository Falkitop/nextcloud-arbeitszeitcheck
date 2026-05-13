<?php

declare(strict_types=1);

/**
 * Resolves the logged-in user's display timezone (Nextcloud personal setting).
 *
 * @var \DateTimeZone $arbeitszeitCheckUserDisplayTz
 */
try {
	$arbeitszeitCheckUserDisplayTz = \OCP\Server::get(\OCP\IDateTimeZone::class)->getTimeZone();
} catch (\Throwable $e) {
	$arbeitszeitCheckUserDisplayTz = new \DateTimeZone(date_default_timezone_get() ?: 'UTC');
}
