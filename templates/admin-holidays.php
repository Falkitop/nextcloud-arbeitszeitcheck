<?php

declare(strict_types=1);

/**
 * Admin holidays template for arbeitszeitcheck app
 *
 * @copyright Copyright (c) 2025
 * @license AGPL-3.0-or-later
 */

/** @var array $_ */
/** @var \OCP\IL10N $l */
$l = $_['l'] ?? \OCP\Util::getL10N('arbeitszeitcheck');
$urlGenerator = $_['urlGenerator'] ?? \OCP\Server::get(\OCP\IURLGenerator::class);

$defaultState = $_['defaultState'] ?? 'NW';
$currentYear = (int)date('Y');

$states = [
	'BW' => 'Baden‑Württemberg',
	'BY' => 'Bayern',
	'BE' => 'Berlin',
	'BB' => 'Brandenburg',
	'HB' => 'Bremen',
	'HH' => 'Hamburg',
	'HE' => 'Hessen',
	'MV' => 'Mecklenburg‑Vorpommern',
	'NI' => 'Niedersachsen',
	'NW' => 'Nordrhein‑Westfalen',
	'RP' => 'Rheinland‑Pfalz',
	'SL' => 'Saarland',
	'SN' => 'Sachsen',
	'ST' => 'Sachsen‑Anhalt',
	'SH' => 'Schleswig‑Holstein',
	'TH' => 'Thüringen',
];

$holidaysUiStrings = [
	'dd.mm.yyyy' => $l->t('dd.mm.yyyy'),
	'Full-day holiday' => $l->t('Full-day holiday'),
	'Half-day holiday' => $l->t('Half-day holiday'),
	'Company holiday' => $l->t('Company holiday'),
	'custom' => $l->t('custom'),
	'Statutory' => $l->t('Statutory'),
	'Save' => $l->t('Save'),
	'Remove' => $l->t('Remove'),
	'Technical error: Required fields for the holiday could not be found.' => $l->t('Technical error: Required fields for the holiday could not be found.'),
	'Please specify date and name of the holiday.' => $l->t('Please specify date and name of the holiday.'),
	'Holiday was saved.' => $l->t('Holiday was saved.'),
	'Holiday could not be saved.' => $l->t('Holiday could not be saved.'),
	'An error occurred while saving the holiday.' => $l->t('An error occurred while saving the holiday.'),
	'Holidays could not be loaded.' => $l->t('Holidays could not be loaded.'),
	'Remove holiday {name} on {date}' => $l->t('Remove holiday {name} on {date}'),
	'Remove holiday' => $l->t('Remove holiday'),
	'Do you really want to remove the holiday "{name}" on {date}?' => $l->t('Do you really want to remove the holiday "{name}" on {date}?'),
	'Statutory holidays are automatically restored when the calendar is viewed, unless "Auto-restore statutory holidays" is disabled in Settings.' => $l->t('Statutory holidays are automatically restored when the calendar is viewed, unless "Auto-restore statutory holidays" is disabled in Settings.'),
	'Cancel' => $l->t('Cancel'),
	'No holidays configured for this year.' => $l->t('No holidays configured for this year.'),
	'Holiday was removed.' => $l->t('Holiday was removed.'),
	'Holiday could not be removed.' => $l->t('Holiday could not be removed.'),
	'An error occurred while removing the holiday.' => $l->t('An error occurred while removing the holiday.'),
];
?>

<?php include __DIR__ . '/common/page-start.php'; ?>

<div class="azc-page-stack">
	<script type="application/json" nonce="<?php p($_['cspNonce'] ?? ''); ?>" id="arbeitszeitcheck-admin-holidays-ui-strings">
<?php echo json_encode($holidaysUiStrings, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
	</script>

	<div class="admin-holidays">

		<section class="azc-card" aria-labelledby="holiday-default-state-title">
			<header class="azc-card__header">
				<div class="azc-card__header-text">
					<h2 id="holiday-default-state-title" class="azc-card__title"><?php p($l->t('Default federal state for holidays')); ?></h2>
					<p class="azc-card__lead">
						<?php p($l->t('This federal state is used automatically when no specific state is set for employees or teams.')); ?>
					</p>
				</div>
			</header>
			<div class="azc-card__body">
				<div class="azc-filter-field admin-holidays__default-state-field">
					<label for="holiday-default-state" class="azc-filter-field__label"><?php p($l->t('Select default federal state')); ?></label>
					<div class="azc-filter-field__control">
						<select id="holiday-default-state" name="holidayDefaultState" class="form-select" aria-describedby="holiday-default-state-help">
							<?php foreach ($states as $code => $name): ?>
								<option value="<?php p($code); ?>"<?php if ($code === $defaultState) { echo ' selected'; } ?>><?php p($l->t($name)); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>
				<p id="holiday-default-state-help" class="admin-holidays__help">
					<?php
					$usersUrl = $urlGenerator->linkToRoute('arbeitszeitcheck.admin.users');
					print_unescaped($l->t(
						'The federal state for an employee is set by administrators or managers, for example in %1$sEmployee settings%2$s. If no own state is configured there, the default state configured here is used.',
						[
							'<a href="' . \OCP\Util::sanitizeHTML($usersUrl) . '">',
							'</a>',
						]
					));
					?>
				</p>
			</div>
		</section>

		<section class="azc-card" aria-labelledby="state-calendar-title">
			<header class="azc-card__header">
				<div class="azc-card__header-text">
					<h2 id="state-calendar-title" class="azc-card__title"><?php p($l->t('Manage calendars by federal state')); ?></h2>
					<p class="azc-card__lead">
						<?php p($l->t('Select federal state and year to view and edit statutory holidays as well as additional company or custom holidays.')); ?>
					</p>
				</div>
			</header>
			<div class="azc-card__body">
				<form class="admin-holidays__toolbar" id="holiday-calendar-filters" novalidate onsubmit="return false;">
					<div class="azc-filter-grid admin-holidays__filter-grid" role="group" aria-label="<?php p($l->t('Calendar selection')); ?>">
						<div class="azc-filter-field">
							<label for="holiday-state-select" class="azc-filter-field__label"><?php p($l->t('Federal state')); ?></label>
							<div class="azc-filter-field__control">
								<select id="holiday-state-select" name="holidayState" class="form-select">
									<?php foreach ($states as $code => $name): ?>
										<option value="<?php p($code); ?>"<?php if ($code === $defaultState) { echo ' selected'; } ?>><?php p($l->t($name)); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
						</div>
						<div class="azc-filter-field">
							<label for="holiday-year-select" class="azc-filter-field__label"><?php p($l->t('Year')); ?></label>
							<div class="azc-filter-field__control">
								<select id="holiday-year-select" name="holidayYear" class="form-select">
									<?php for ($y = $currentYear - 1; $y <= $currentYear + 3; $y++): ?>
										<option value="<?php p($y); ?>"<?php if ($y === $currentYear) { echo ' selected'; } ?>><?php p($y); ?></option>
									<?php endfor; ?>
								</select>
							</div>
						</div>
						<div class="azc-filter-actions">
							<button type="button" id="holiday-add-entry" class="azc-btn azc-btn--primary" aria-label="<?php p($l->t('Create new holiday')); ?>">
								<?php p($l->t('Add new holiday')); ?>
							</button>
						</div>
					</div>
				</form>

				<div class="admin-holidays__results" id="holiday-results" aria-live="polite" aria-busy="false">
					<div class="table-container" role="region" aria-label="<?php p($l->t('List of holidays for the selected federal state and year')); ?>">
						<table class="table table--hover" id="holiday-table" aria-label="<?php p($l->t('List of holidays for the selected federal state and year')); ?>">
							<caption class="visually-hidden"><?php p($l->t('List of holidays for the selected federal state and year, with date, name, type and actions')); ?></caption>
							<thead>
								<tr>
									<th scope="col"><?php p($l->t('Date')); ?></th>
									<th scope="col"><?php p($l->t('Holiday name')); ?></th>
									<th scope="col"><?php p($l->t('Type')); ?></th>
									<th scope="col"><?php p($l->t('Scope')); ?></th>
									<th scope="col"><?php p($l->t('Actions')); ?></th>
								</tr>
							</thead>
							<tbody id="holiday-tbody"></tbody>
						</table>
					</div>
				</div>

				<aside class="azc-callout azc-callout--info admin-holidays__legend" aria-label="<?php p($l->t('Column explanations')); ?>">
					<p class="azc-callout__text">
						<?php p($l->t('"Type" determines whether a day is treated as a full-day holiday (not counted as a working day) or as a half-day holiday (e.g., 0.5 vacation day).')); ?>
					</p>
					<p class="azc-callout__text">
						<?php p($l->t('"Scope" distinguishes between statutory holidays, organization-wide company holidays, and custom entries. Statutory holidays are always treated as full-day holidays.')); ?>
					</p>
				</aside>
			</div>
		</section>

	</div>
</div>

<?php include __DIR__ . '/common/page-end.php'; ?>
