<?php

declare(strict_types=1);

/**
 * Bootstraps the JavaScript timezone module ({@see js/common/time.js}) and
 * exposes PHP timezone variables for server-side template rendering.
 *
 * Side effects (via {@see \OCA\ArbeitszeitCheck\Support\TimeClientBootstrap}):
 *
 *  - Registers Nextcloud InitialState + `common/time-init.js` (idempotent).
 *  - Defines `$arbeitszeitCheckStorageTimeZone`, `$arbeitszeitCheckUserDisplayTz`
 *    and `$arbeitszeitCheckServerNowIso` for the including template.
 *
 * @var \DateTimeZone $arbeitszeitCheckStorageTimeZone
 * @var \DateTimeZone $arbeitszeitCheckUserDisplayTz
 * @var string $arbeitszeitCheckServerNowIso
 *
 * @copyright Copyright (c) 2026 Alexander Mäule <info@software-by-design.de>
 * @license AGPL-3.0-or-later
 */

use OCA\ArbeitszeitCheck\Support\TimeClientBootstrap;

/** @var \DateTimeZone $arbeitszeitCheckStorageTimeZone */
/** @var \DateTimeZone $arbeitszeitCheckUserDisplayTz */
/** @var string $arbeitszeitCheckServerNowIso */

$bootstrap = \OCP\Server::get(TimeClientBootstrap::class);

if (!isset($arbeitszeitCheckStorageTimeZone) || !$arbeitszeitCheckStorageTimeZone instanceof \DateTimeZone) {
	$arbeitszeitCheckStorageTimeZone = $bootstrap->storageTimeZone();
}

if (!isset($arbeitszeitCheckUserDisplayTz) || !$arbeitszeitCheckUserDisplayTz instanceof \DateTimeZone) {
	$arbeitszeitCheckUserDisplayTz = $bootstrap->userDisplayTimeZone();
}

if (!isset($arbeitszeitCheckServerNowIso) || !is_string($arbeitszeitCheckServerNowIso) || $arbeitszeitCheckServerNowIso === '') {
	$arbeitszeitCheckServerNowIso = $bootstrap->serverNowIso();
}

$bootstrap->registerConfig();

$storageTzName = $arbeitszeitCheckStorageTimeZone->getName();
$displayTzName = $arbeitszeitCheckUserDisplayTz->getName();
$serverNowJs = json_encode(
	$arbeitszeitCheckServerNowIso,
	JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR,
);
$storageTzJs = json_encode($storageTzName, JSON_THROW_ON_ERROR);
$displayTzJs = json_encode($displayTzName, JSON_THROW_ON_ERROR);
?>
<script nonce="<?php p($_['cspNonce'] ?? ''); ?>">
(function (w) {
	'use strict';
	w.ArbeitszeitCheck = w.ArbeitszeitCheck || {};
	w.ArbeitszeitCheck.tz = Object.assign(
		{ storage: <?php print_unescaped($storageTzJs); ?>, display: <?php print_unescaped($displayTzJs); ?> },
		w.ArbeitszeitCheck.tz || {},
	);
	if (!w.ArbeitszeitCheck.serverNow) {
		w.ArbeitszeitCheck.serverNow = <?php print_unescaped($serverNowJs); ?>;
	}
})(window);
</script>
<?php
