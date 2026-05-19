<?php

declare(strict_types=1);

/**
 * Backwards-compatible shim that resolves the user's display timezone via
 * the canonical {@see \OCA\ArbeitszeitCheck\Service\TimeZoneService} (or
 * the {@see \OCP\IDateTimeZone} fallback) and ALSO performs the full JS
 * `ArbeitszeitCheck.tz` / `ArbeitszeitCheck.serverNow` bootstrap by
 * delegating to `common/time-bootstrap.php`.
 *
 * Provides:
 *   - `$arbeitszeitCheckUserDisplayTz` (\DateTimeZone)
 *   - `$arbeitszeitCheckStorageTimeZone` (\DateTimeZone)
 *   - `$arbeitszeitCheckServerNowIso` (string ISO-8601 with offset)
 *
 * @var \DateTimeZone $arbeitszeitCheckUserDisplayTz
 * @var \DateTimeZone $arbeitszeitCheckStorageTimeZone
 * @var string $arbeitszeitCheckServerNowIso
 */

require __DIR__ . '/time-bootstrap.php';
