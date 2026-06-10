<?php

declare(strict_types=1);

/** @var array $_ */
/** @var \OCP\IL10N $l */
$l = $_['l'] ?? \OCP\Util::getL10N('arbeitszeitcheck');

$license = is_array($_['license'] ?? null) ? $_['license'] : null;
$mobileUsed = (int)($_['mobileSeatsUsed'] ?? 0);
$mobileLimit = (int)($_['mobileSeatsLimit'] ?? 0);
$terminalUsed = (int)($_['terminalDevicesUsed'] ?? 0);
$terminalLimit = (int)($_['terminalDevicesLimit'] ?? 0);
$mobileSeats = is_array($_['mobileSeats'] ?? null) ? $_['mobileSeats'] : [];
$showMobile = !empty($_['showMobileSeats']);
$showTerminal = !empty($_['showTerminal']);
$purchaseUrl = (string)($_['purchaseUrl'] ?? 'https://software-by-design.de/arbeitszeitcheck/preise');
$apiLicenseUrl = (string)($_['apiLicenseUrl'] ?? '');
$apiClearLicenseUrl = (string)($_['apiClearLicenseUrl'] ?? '');
$apiSeatsUrl = (string)($_['apiSeatsUrl'] ?? '');
$apiRemoveSeatUrl = (string)($_['apiRemoveSeatUrl'] ?? '');
$apiSearchUsersUrl = (string)($_['apiSearchUsersUrl'] ?? '');
$requesttoken = (string)($_['requesttoken'] ?? '');
$i18n = is_array($_['i18n'] ?? null) ? $_['i18n'] : [];
$i18nJson = json_encode($i18n, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
if (!is_string($i18nJson)) {
	$i18nJson = '{}';
}

$hasLicense = $license !== null;
$isActive = $hasLicense && !empty($license['active']);
$validUntil = $hasLicense ? (string)($license['validUntil'] ?? '') : '';
$customerId = $hasLicense ? (string)($license['customerId'] ?? '') : '';
$expiresSoon = false;
if ($validUntil !== '') {
	$untilDt = DateTimeImmutable::createFromFormat('Y-m-d', $validUntil);
	$today = new DateTimeImmutable('today');
	if ($untilDt !== false) {
		$daysLeft = (int)$today->diff($untilDt)->format('%r%a');
		$expiresSoon = $daysLeft >= 0 && $daysLeft <= 30;
	}
}
?>

<?php include __DIR__ . '/common/page-start.php'; ?>

<div class="azc-page-stack azc-license-page" id="azc-license-page"
	data-api-license="<?php p($apiLicenseUrl); ?>"
	data-api-clear-license="<?php p($apiClearLicenseUrl); ?>"
	data-api-seats="<?php p($apiSeatsUrl); ?>"
	data-api-remove-seat="<?php p($apiRemoveSeatUrl); ?>"
	data-api-search-users="<?php p($apiSearchUsersUrl); ?>"
	data-i18n="<?php p($i18nJson); ?>"
	data-requesttoken="<?php p($requesttoken); ?>">

	<div id="azc-license-live" class="azc-sr-only" role="status" aria-live="polite" aria-atomic="true"></div>
	<div id="azc-license-alert" class="azc-sr-only" role="alert" aria-live="assertive" aria-atomic="true"></div>

	<section class="azc-license-section azc-card" aria-labelledby="azc-license-key-heading">
		<header class="azc-license-section__header">
			<h2 id="azc-license-key-heading" class="azc-license-section__title"><?php p($l->t('Organisation license')); ?></h2>
			<p class="azc-license-section__lead"><?php p($l->t('Paste the license key you received after purchase. The web app stays free — this key unlocks Mobile and Terminal apps for your organisation.')); ?></p>
		</header>

		<div class="azc-license-form">
			<label for="azc-license-key-input" class="azc-field__label"><?php p($l->t('License key')); ?></label>
			<textarea id="azc-license-key-input"
				class="azc-license-key-input"
				name="licenseKey"
				rows="4"
				spellcheck="false"
				autocomplete="off"
				aria-describedby="azc-license-key-hint"
				placeholder="AZC2.…"></textarea>
			<p id="azc-license-key-hint" class="azc-field__hint"><?php p($l->t('Format: AZC2 followed by a signed payload. One key per organisation.')); ?></p>
			<div class="azc-license-form__actions">
				<button type="button" id="azc-license-save" class="azc-btn azc-btn--primary">
					<?php p($l->t('Save license')); ?>
				</button>
				<?php if ($hasLicense): ?>
				<button type="button" id="azc-license-clear" class="azc-btn azc-btn--danger">
					<?php p($l->t('Remove license')); ?>
				</button>
				<?php endif; ?>
			</div>
		</div>

		<div id="azc-license-status" class="azc-license-status" <?php echo $hasLicense ? '' : 'hidden'; ?>>
			<h3 class="azc-license-status__title"><?php p($l->t('Current license')); ?></h3>
			<dl class="azc-license-status__grid">
				<div class="azc-license-status__item">
					<dt><?php p($l->t('Customer ID')); ?></dt>
					<dd id="azc-license-customer"><?php p($customerId); ?></dd>
				</div>
				<div class="azc-license-status__item">
					<dt><?php p($l->t('Valid until')); ?></dt>
					<dd id="azc-license-valid-until"><?php p($validUntil); ?></dd>
				</div>
				<div class="azc-license-status__item">
					<dt><?php p($l->t('Mobile seats')); ?></dt>
					<dd><span id="azc-license-mobile-used"><?php p((string)$mobileUsed); ?></span> / <span id="azc-license-mobile-limit"><?php p((string)$mobileLimit); ?></span></dd>
				</div>
				<div class="azc-license-status__item">
					<dt><?php p($l->t('Terminal devices')); ?></dt>
					<dd><span id="azc-license-terminal-used"><?php p((string)$terminalUsed); ?></span> / <span id="azc-license-terminal-limit"><?php p((string)$terminalLimit); ?></span></dd>
				</div>
				<div class="azc-license-status__item">
					<dt><?php p($l->t('Status')); ?></dt>
					<dd>
						<span id="azc-license-active-badge" class="azc-badge <?php echo $isActive ? 'azc-badge--success' : 'azc-badge--warning'; ?>"
							data-active-label="<?php p($l->t('Active')); ?>"
							data-inactive-label="<?php p($l->t('Expired or invalid')); ?>">
							<?php p($isActive ? $l->t('Active') : $l->t('Expired or invalid')); ?>
						</span>
					</dd>
				</div>
			</dl>
			<?php if ($expiresSoon && $isActive): ?>
				<?php
				$calloutVariant = 'warning';
				$calloutRole = 'note';
				$calloutTitle = $l->t('License expires soon');
				$calloutText = $l->t('Your license expires within 30 days. Contact your vendor to renew.');
				$calloutExtraClass = 'azc-license-expiry-callout';
				include __DIR__ . '/common/alert-callout.php';
				?>
			<?php endif; ?>
			<p class="azc-license-status__purchase">
				<a href="<?php p($purchaseUrl); ?>" target="_blank" rel="noopener noreferrer"><?php p($l->t('Purchase or renew license')); ?></a>
			</p>
		</div>
	</section>

	<?php if ($showMobile): ?>
	<section class="azc-license-section azc-card" id="azc-mobile-seats-section" aria-labelledby="azc-mobile-seats-heading">
		<header class="azc-license-section__header">
			<h2 id="azc-mobile-seats-heading" class="azc-license-section__title"><?php p($l->t('Mobile seats')); ?></h2>
			<p class="azc-license-section__lead"><?php p($l->t('Choose which employees may use the ArbeitszeitCheck Mobile app. Only assigned users can clock in from their phone.')); ?></p>
		</header>

		<div class="azc-license-seat-picker">
			<label for="azc-seat-user-search" class="azc-field__label"><?php p($l->t('Add employee')); ?></label>
			<input type="search"
				id="azc-seat-user-search"
				class="azc-input"
				autocomplete="off"
				placeholder="<?php p($l->t('Search by name or user ID…')); ?>"
				aria-describedby="azc-seat-picker-hint azc-seat-count"
				aria-controls="azc-seat-search-results"
				aria-expanded="false"
				role="combobox">
			<p id="azc-seat-picker-hint" class="azc-field__hint"><?php p($l->t('Type at least two characters, then select a person from the list.')); ?></p>
			<p id="azc-seat-count" class="azc-field__hint" aria-live="polite">
				<?php p($l->t('%1$d of %2$d seats assigned', [$mobileUsed, $mobileLimit])); ?>
			</p>
			<ul id="azc-seat-search-results" class="azc-seat-search-results" role="listbox" hidden></ul>
		</div>

		<div class="azc-license-seats-table-wrap">
			<table class="azc-license-seats-table" aria-labelledby="azc-mobile-seats-heading">
				<thead>
					<tr>
						<th scope="col"><?php p($l->t('Employee')); ?></th>
						<th scope="col"><?php p($l->t('User ID')); ?></th>
						<th scope="col"><?php p($l->t('Assigned')); ?></th>
						<th scope="col"><span class="azc-sr-only"><?php p($l->t('Actions')); ?></span></th>
					</tr>
				</thead>
				<tbody id="azc-seat-list-body">
					<?php foreach ($mobileSeats as $seat): ?>
						<tr data-user-id="<?php p((string)($seat['userId'] ?? '')); ?>">
							<td><?php p((string)($seat['displayName'] ?? '')); ?></td>
							<td><code><?php p((string)($seat['userId'] ?? '')); ?></code></td>
							<td><?php p((string)($seat['assignedAt'] ?? '')); ?></td>
							<td>
								<button type="button" class="azc-btn azc-btn--secondary azc-seat-remove" data-user-id="<?php p((string)($seat['userId'] ?? '')); ?>">
									<?php p($l->t('Remove')); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p id="azc-seat-empty" class="azc-field__hint" <?php echo count($mobileSeats) > 0 ? 'hidden' : ''; ?>>
				<?php p($l->t('No mobile seats assigned yet.')); ?>
			</p>
		</div>
	</section>
	<?php endif; ?>

	<?php if ($showTerminal): ?>
	<section class="azc-license-section azc-card" aria-labelledby="azc-terminal-heading">
		<header class="azc-license-section__header">
			<h2 id="azc-terminal-heading" class="azc-license-section__title"><?php p($l->t('Terminal devices')); ?></h2>
			<p class="azc-license-section__lead"><?php p($l->t('Pair kiosk tablets from the Kiosk admin area (Track C). Each paired device uses one license slot.')); ?></p>
		</header>
		<p class="azc-field__hint"><?php p($l->t('%1$d of %2$d terminal slots in use.', [$terminalUsed, $terminalLimit])); ?></p>
	</section>
	<?php endif; ?>
</div>

<?php include __DIR__ . '/common/page-end.php'; ?>
