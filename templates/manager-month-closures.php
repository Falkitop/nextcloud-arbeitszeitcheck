<?php

declare(strict_types=1);

/** @var array $_ */
/** @var \OCP\IL10N $l */
$l = $_['l'] ?? \OCP\Util::getL10N('arbeitszeitcheck');
$error = $_['error'] ?? null;
$availableMonthsUrl = $_['revisionPdfAvailableMonthsUrl'] ?? '';
$usersForMonthUrl = $_['revisionPdfUsersForMonthUrl'] ?? '';
$pdfUrlBase = $_['pdfUrlBase'] ?? '';

$l10nKeys = [
	'Loading…',
	'Choose month…',
	'Could not load months.',
	'No finalized months are available for your access yet.',
	'Select a month to see who you can download for.',
	'Could not load people for this month.',
	'No one has a finalized revision for this month in your scope.',
	'Download PDF',
	'Download revision PDF for {name}',
	'Could not initialize the month list. Please reload the page.',
	'January',
	'February',
	'March',
	'April',
	'May',
	'June',
	'July',
	'August',
	'September',
	'October',
	'November',
	'December',
];
$l10nMap = [];
foreach ($l10nKeys as $key) {
	$l10nMap[$key] = $l->t($key);
}
?>

<?php include __DIR__ . '/common/page-start.php'; ?>

<div class="azc-page-stack">
	<div class="manager-month-closures"
		<?php if (!$error): ?>
		data-revision-pdf-available-months-url="<?php p($availableMonthsUrl); ?>"
		data-revision-pdf-users-for-month-url="<?php p($usersForMonthUrl); ?>"
		data-pdf-url-base="<?php p($pdfUrlBase); ?>"
		<?php endif; ?>>

		<?php if (!$error): ?>
		<script type="application/json" id="manager-mc-l10n-json" nonce="<?php p($_['cspNonce'] ?? ''); ?>">
		<?php echo json_encode($l10nMap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>
		</script>
		<?php endif; ?>

		<?php if ($error): ?>
			<div class="azc-callout azc-callout--danger" role="alert">
				<p class="azc-callout__text"><?php p($error); ?></p>
			</div>
		<?php else: ?>

		<section class="azc-card manager-mc-wizard" aria-labelledby="manager-mc-wizard-title">
			<header class="azc-card__header">
				<div class="azc-card__header-text">
					<h2 id="manager-mc-wizard-title" class="azc-card__title"><?php p($l->t('How it works')); ?></h2>
					<p class="azc-card__lead">
						<?php p($l->t('First choose a sealed month, then download the official revision PDF for each person in your scope.')); ?>
					</p>
				</div>
			</header>

			<div class="azc-card__body">
				<ol class="manager-mc-steps" role="list">
					<li class="manager-mc-step">
						<div class="manager-mc-step__head">
							<span class="manager-mc-step__badge" aria-hidden="true">1</span>
							<div class="manager-mc-step__titles">
								<h3 id="manager-mc-step-month-title" class="manager-mc-step__title"><?php p($l->t('Choose month')); ?></h3>
								<p id="manager-mc-month-hint" class="manager-mc-step__hint">
									<?php p($l->t('Only months with finalized (sealed) data you can act on are listed.')); ?>
								</p>
							</div>
						</div>
						<div class="manager-mc-step__body">
							<div class="manager-mc-field">
								<label class="manager-mc-field__label" for="manager-mc-month-select"><?php p($l->t('Calendar month')); ?></label>
								<select id="manager-mc-month-select"
									class="form-select manager-mc-field__control"
									aria-busy="true"
									aria-describedby="manager-mc-month-hint manager-mc-month-load-status">
									<option value=""><?php p($l->t('Loading…')); ?></option>
								</select>
							</div>
							<p id="manager-mc-month-load-status" class="manager-mc-status" role="status" aria-live="polite"></p>
						</div>
					</li>

					<li class="manager-mc-step">
						<div class="manager-mc-step__head">
							<span class="manager-mc-step__badge" aria-hidden="true">2</span>
							<div class="manager-mc-step__titles">
								<h3 id="manager-mc-step-people-title" class="manager-mc-step__title"><?php p($l->t('Download for each person')); ?></h3>
								<p id="manager-mc-people-hint" class="manager-mc-step__hint">
									<?php p($l->t('Everyone listed has a finalized month for your selection and matches your permissions.')); ?>
								</p>
							</div>
						</div>
						<div class="manager-mc-step__body">
							<div id="manager-mc-people-region"
								class="manager-mc-people"
								role="region"
								aria-labelledby="manager-mc-step-people-title"
								aria-describedby="manager-mc-people-hint manager-mc-people-status">
								<div id="manager-mc-people-empty" class="azc-empty-state manager-mc-people__empty" hidden>
									<p class="azc-empty-state__text"></p>
								</div>
								<ul id="manager-mc-people-list" class="manager-mc-people__list" hidden></ul>
							</div>
							<p id="manager-mc-people-status" class="manager-mc-status" role="status" aria-live="polite"></p>
						</div>
					</li>
				</ol>

				<div id="manager-mc-page-error" class="azc-callout azc-callout--danger manager-mc-page-error" role="alert" aria-live="assertive" hidden>
					<p class="azc-callout__text"></p>
				</div>
			</div>
		</section>

		<?php endif; ?>
	</div>
</div>

<?php include __DIR__ . '/common/page-end.php'; ?>
