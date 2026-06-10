<?php

declare(strict_types=1);

/** @var array $_ */
/** @var \OCP\IL10N $l */
$l = $_['l'] ?? \OCP\Util::getL10N('arbeitszeitcheck');

$kioskEnabled = !empty($_['kioskEnabled']);
$terminals = is_array($_['terminals'] ?? null) ? $_['terminals'] : [];
$terminalUsed = (int)($_['terminalDevicesUsed'] ?? 0);
$terminalLimit = (int)($_['terminalDevicesLimit'] ?? 0);
$requesttoken = (string)($_['requesttoken'] ?? '');
$i18n = is_array($_['i18n'] ?? null) ? $_['i18n'] : [];
$i18nJson = json_encode($i18n, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
if (!is_string($i18nJson)) {
	$i18nJson = '{}';
}

$statusLabel = static function (string $status) use ($l, $i18n): string {
	return match ($status) {
		'active' => (string)($i18n['statusActive'] ?? $l->t('Active')),
		'pending' => (string)($i18n['statusPending'] ?? $l->t('Pending pairing')),
		'revoked' => (string)($i18n['statusRevoked'] ?? $l->t('Revoked')),
		default => $status,
	};
};
?>

<?php include __DIR__ . '/common/page-start.php'; ?>

<div class="azc-page-stack azc-kiosk-page" id="azc-kiosk-page"
	data-api-enabled="<?php p((string)($_['apiKioskEnabled'] ?? '')); ?>"
	data-api-terminals="<?php p((string)($_['apiTerminals'] ?? '')); ?>"
	data-api-credentials="<?php p((string)($_['apiCredentials'] ?? '')); ?>"
	data-api-rfid="<?php p((string)($_['apiRfid'] ?? '')); ?>"
	data-api-pin="<?php p((string)($_['apiPinGenerate'] ?? '')); ?>"
	data-api-enrollment-start="<?php p((string)($_['apiEnrollmentStart'] ?? '')); ?>"
	data-api-enrollment-status="<?php p((string)($_['apiEnrollmentStatus'] ?? '')); ?>"
	data-api-enrollment-cancel="<?php p((string)($_['apiEnrollmentCancel'] ?? '')); ?>"
	data-api-search-users="<?php p((string)($_['apiSearchUsers'] ?? '')); ?>"
	data-api-terminal-revoke="<?php p((string)($_['apiTerminalRevoke'] ?? '')); ?>"
	data-api-user-allowed="<?php p((string)($_['apiUserAllowed'] ?? '')); ?>"
	data-i18n="<?php p($i18nJson); ?>"
	data-requesttoken="<?php p($requesttoken); ?>">

	<div id="azc-kiosk-live" class="azc-sr-only" role="status" aria-live="polite" aria-atomic="true"></div>
	<div id="azc-kiosk-alert" class="azc-sr-only" role="alert" aria-live="assertive" aria-atomic="true"></div>

	<section class="azc-card azc-kiosk-section" aria-labelledby="azc-kiosk-enable-heading">
		<header class="azc-kiosk-section__header">
			<h2 id="azc-kiosk-enable-heading"><?php p($l->t('Kiosk mode')); ?></h2>
			<p class="azc-kiosk-section__lead"><?php p($l->t('Enable foyer tablet clocking. Requires a Terminal license and registered devices.')); ?></p>
		</header>
		<label class="azc-kiosk-toggle">
			<input type="checkbox" id="azc-kiosk-enabled" <?php echo $kioskEnabled ? 'checked' : ''; ?>
				aria-describedby="azc-kiosk-enable-hint">
			<span><?php p($l->t('Kiosk enabled')); ?></span>
		</label>
		<p id="azc-kiosk-enable-hint" class="azc-field__hint">
			<?php p($l->t('Terminal devices: %1$s / %2$s', [(string)$terminalUsed, (string)$terminalLimit])); ?>
		</p>
	</section>

	<section class="azc-card azc-kiosk-section" aria-labelledby="azc-kiosk-terminals-heading">
		<header class="azc-kiosk-section__header">
			<h2 id="azc-kiosk-terminals-heading"><?php p($l->t('Terminals')); ?></h2>
		</header>
		<div class="azc-kiosk-form">
			<label for="azc-kiosk-terminal-label" class="azc-field__label"><?php p($l->t('New terminal label')); ?></label>
			<input type="text" id="azc-kiosk-terminal-label" class="azc-input" maxlength="128"
				placeholder="<?php p($l->t('e.g. Main entrance')); ?>">
			<button type="button" id="azc-kiosk-create-terminal" class="azc-btn azc-btn--primary">
				<?php p($l->t('Create terminal & pairing code')); ?>
			</button>
		</div>
		<div id="azc-kiosk-pairing-backdrop" class="azc-kiosk-modal-backdrop" hidden></div>
		<div id="azc-kiosk-pairing-modal" class="azc-kiosk-modal" hidden role="dialog" aria-modal="true" aria-labelledby="azc-kiosk-pairing-title">
			<h3 id="azc-kiosk-pairing-title"><?php p($l->t('Pairing code')); ?></h3>
			<p><?php p($l->t('Enter this code on the tablet within 10 minutes. It is shown only once.')); ?></p>
			<p class="azc-kiosk-pairing__code" id="azc-kiosk-pairing-code" aria-live="polite"></p>
			<button type="button" id="azc-kiosk-pairing-close" class="azc-btn azc-btn--primary"><?php p($l->t('Close')); ?></button>
		</div>
		<div class="azc-table-wrap">
			<table class="azc-table" id="azc-kiosk-terminals-table">
				<caption class="azc-sr-only"><?php p($l->t('Registered kiosk terminals')); ?></caption>
				<thead>
					<tr>
						<th scope="col"><?php p($l->t('Label')); ?></th>
						<th scope="col"><?php p($l->t('Status')); ?></th>
						<th scope="col"><?php p($l->t('Last seen')); ?></th>
						<th scope="col"><?php p($l->t('Actions')); ?></th>
					</tr>
				</thead>
				<tbody id="azc-kiosk-terminals-body">
					<?php foreach ($terminals as $t): ?>
					<?php
					$tid = (string)($t['terminalId'] ?? '');
					$status = (string)($t['status'] ?? '');
					$canRevoke = $status === 'active' || $status === 'pending';
					?>
					<tr data-terminal-id="<?php p($tid); ?>">
						<td><?php p((string)($t['label'] ?? '')); ?></td>
						<td><?php p($statusLabel($status)); ?></td>
						<td><?php p((string)($t['lastSeenAt'] ?? '—')); ?></td>
						<td>
							<?php if ($canRevoke): ?>
							<button type="button" class="azc-btn azc-btn--small azc-kiosk-revoke-terminal"
								data-terminal-id="<?php p($tid); ?>">
								<?php p($l->t('Revoke')); ?>
							</button>
							<?php else: ?>
							<span aria-hidden="true">—</span>
							<?php endif; ?>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</section>

	<section class="azc-card azc-kiosk-section" aria-labelledby="azc-kiosk-creds-heading">
		<header class="azc-kiosk-section__header">
			<h2 id="azc-kiosk-creds-heading"><?php p($l->t('Badges & PIN')); ?></h2>
		</header>
		<div class="azc-kiosk-form azc-kiosk-form--grid">
			<div>
				<label for="azc-kiosk-user-search" class="azc-field__label"><?php p($l->t('Employee')); ?></label>
				<input type="search" id="azc-kiosk-user-search" class="azc-input" autocomplete="off"
					aria-controls="azc-kiosk-user-results" placeholder="<?php p($l->t('Search by name…')); ?>">
				<input type="hidden" id="azc-kiosk-selected-user" value="">
				<ul id="azc-kiosk-user-results" class="azc-kiosk-user-results" role="listbox" hidden></ul>
			</div>
			<div>
				<label for="azc-kiosk-enroll-terminal" class="azc-field__label"><?php p($l->t('Terminal for scan')); ?></label>
				<select id="azc-kiosk-enroll-terminal" class="azc-input">
					<option value=""><?php p($l->t('Select terminal…')); ?></option>
					<?php foreach ($terminals as $t): ?>
						<?php if (($t['status'] ?? '') === 'active'): ?>
						<option value="<?php p((string)($t['terminalId'] ?? '')); ?>"><?php p((string)($t['label'] ?? '')); ?></option>
						<?php endif; ?>
					<?php endforeach; ?>
				</select>
			</div>
		</div>
		<div class="azc-kiosk-form__actions">
			<button type="button" id="azc-kiosk-start-enrollment" class="azc-btn azc-btn--primary">
				<?php p($l->t('Scan badge at tablet')); ?>
			</button>
			<button type="button" id="azc-kiosk-generate-pin" class="azc-btn">
				<?php p($l->t('Generate PIN')); ?>
			</button>
		</div>
		<p id="azc-kiosk-enrollment-status" class="azc-kiosk-enrollment-status" aria-live="polite"></p>
		<div id="azc-kiosk-pin-backdrop" class="azc-kiosk-modal-backdrop" hidden></div>
		<div id="azc-kiosk-pin-modal" class="azc-kiosk-modal" hidden role="dialog" aria-modal="true" aria-labelledby="azc-kiosk-pin-title">
			<h3 id="azc-kiosk-pin-title"><?php p($l->t('PIN generated')); ?></h3>
			<p class="azc-kiosk-modal__hint"><?php p($l->t('PIN is shown only once. Share it securely with the employee.')); ?></p>
			<p class="azc-kiosk-pairing__code" id="azc-kiosk-pin-code" aria-live="polite"></p>
			<button type="button" id="azc-kiosk-pin-close" class="azc-btn azc-btn--primary"><?php p($l->t('Close')); ?></button>
		</div>
		<div class="azc-table-wrap">
			<table class="azc-table" id="azc-kiosk-creds-table">
				<caption class="azc-sr-only"><?php p($l->t('Kiosk credentials')); ?></caption>
				<thead>
					<tr>
						<th scope="col"><?php p($l->t('Employee')); ?></th>
						<th scope="col"><?php p($l->t('Type')); ?></th>
						<th scope="col"><?php p($l->t('Kiosk allowed')); ?></th>
						<th scope="col"><?php p($l->t('Actions')); ?></th>
					</tr>
				</thead>
				<tbody id="azc-kiosk-creds-body"></tbody>
			</table>
		</div>
	</section>
</div>

<?php include __DIR__ . '/common/page-end.php'; ?>
