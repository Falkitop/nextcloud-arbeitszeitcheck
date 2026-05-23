<?php

declare(strict_types=1);

use OCA\ArbeitszeitCheck\Util\TemplateL10n;

/** @var array $_ */
/** @var \OCP\IL10N $l */
/** @var \OCP\IURLGenerator $urlGenerator */

/** Styles/scripts are registered in OvertimePayoutController::index(). */
$urlGenerator = $_['urlGenerator'] ?? \OCP\Server::get(\OCP\IURLGenerator::class);
include __DIR__ . '/common/admin-overtime-payout-l10n.php';

$bankEnabled = (bool)($_['bankEnabled'] ?? false);
$notificationsUrl = $urlGenerator->linkToRoute('arbeitszeitcheck.admin.notifications') . '#overtime-bank-heading';
$auditUrl = $urlGenerator->linkToRoute('arbeitszeitcheck.overtime_payout.auditIndex');
$bankMax = (float)($_['bankMaxHours'] ?? 100);
$defaultYear = (int)($_['defaultYear'] ?? (int)date('Y'));
$defaultMonth = (int)($_['defaultMonth'] ?? (int)date('n'));

$monthLabels = [];
$fmt = new \IntlDateFormatter(
	$l->getLanguageCode(),
	\IntlDateFormatter::NONE,
	\IntlDateFormatter::NONE,
	null,
	null,
	'MMMM'
);
for ($m = 1; $m <= 12; $m++) {
	$dt = \DateTime::createFromFormat('!Y-n-j', sprintf('%d-%d-1', $defaultYear, $m));
	$monthLabels[$m] = $dt !== false ? (string)$fmt->format($dt) : (string)$m;
}
?>

<?php include __DIR__ . '/common/navigation.php'; ?>

<main id="app-content" class="admin-overtime-payouts-page" role="main" aria-labelledby="admin-overtime-payouts-title">
	<div id="app-content-wrapper">
		<div class="breadcrumb-container">
			<nav class="breadcrumb" aria-label="<?php p($l->t('Breadcrumb')); ?>">
				<ol>
					<li><a href="<?php p($urlGenerator->linkToRoute('arbeitszeitcheck.admin.dashboard')); ?>"><?php p($l->t('Administration')); ?></a></li>
					<li><a href="<?php p($urlGenerator->linkToRoute('arbeitszeitcheck.admin.notifications')); ?>"><?php p($l->t('Notifications & overtime')); ?></a></li>
					<li aria-current="page"><?php p($l->t('Overtime payouts')); ?></li>
				</ol>
			</nav>
		</div>

		<header class="page-header-section">
			<div class="header-content">
				<div class="header-text">
					<h1 id="admin-overtime-payouts-title"><?php p($l->t('Overtime payouts')); ?></h1>
					<p class="page-description"><?php p($l->t('Record month-end payout of overtime hours above the bank cap for payroll. Each payout is stored permanently for audit.')); ?></p>
				</div>
				<div class="header-actions">
					<a href="<?php p($auditUrl); ?>" class="btn btn--secondary">
						<?php p($l->t('Payout audit')); ?>
					</a>
				</div>
			</div>
		</header>

		<?php if (!$bankEnabled): ?>
		<div class="alert alert--warning" role="alert">
			<p>
				<?php p($l->t('The overtime bank is disabled. Payouts cannot be processed until you enable it.')); ?>
				<a href="<?php p($notificationsUrl); ?>" class="alert__link"><?php p($l->t('Open overtime bank settings')); ?></a>
			</p>
		</div>
		<?php endif; ?>

		<section class="admin-overtime-payouts-steps" aria-label="<?php p($l->t('How payout works')); ?>">
			<ol class="admin-overtime-payouts-steps__list">
				<li><?php p($l->t('Select the completed calendar month.')); ?></li>
				<li><?php p($l->t('Review employees with hours above the %s h bank cap.', [number_format($bankMax, 0)])); ?></li>
				<li><?php p($l->t('Confirm payout — the employee is notified and the record cannot be deleted.')); ?></li>
			</ol>
		</section>

		<section class="admin-overtime-payouts-filters" aria-labelledby="admin-ot-payout-filter-heading">
			<h3 id="admin-ot-payout-filter-heading" class="admin-settings-section__title"><?php p($l->t('Select month')); ?></h3>
			<p id="admin-ot-payout-bank-hint" class="form-help form-help--block">
				<?php if ($bankEnabled): ?>
					<?php p($l->t('Bank cap: %s hours. Payout is only possible after the month has ended.', [number_format($bankMax, 0)])); ?>
				<?php else: ?>
					<?php p($l->t('Enable the overtime bank to process payouts.')); ?>
				<?php endif; ?>
			</p>
			<div class="form-row form-row--inline">
				<div class="form-group">
					<label for="ot-payout-year" class="form-label"><?php p($l->t('Year')); ?></label>
					<input type="number" id="ot-payout-year" class="form-input" min="2000" max="2100" value="<?php p((string)$defaultYear); ?>" aria-describedby="admin-ot-payout-bank-hint">
				</div>
				<div class="form-group">
					<label for="ot-payout-month" class="form-label"><?php p($l->t('Month')); ?></label>
					<select id="ot-payout-month" class="form-input">
						<?php foreach ($monthLabels as $m => $label): ?>
						<option value="<?php p((string)$m); ?>"<?php echo $m === $defaultMonth ? ' selected' : ''; ?>>
							<?php p($label); ?>
						</option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>
			<div class="admin-overtime-payouts-filters__actions" role="group" aria-label="<?php p($l->t('Actions')); ?>">
				<button type="button" class="btn btn--secondary" id="ot-payout-refresh">
					<?php p($l->t('Load list')); ?>
				</button>
				<button type="button" class="btn btn--secondary" id="ot-payout-export" <?php echo $bankEnabled ? '' : ' disabled'; ?>>
					<?php p($l->t('Export CSV for payroll')); ?>
				</button>
				<button type="button" class="btn btn--primary" id="ot-payout-bulk" <?php echo $bankEnabled ? '' : ' disabled'; ?>>
					<?php p($l->t('Pay out all pending')); ?>
				</button>
			</div>
		</section>

		<div id="ot-payout-summary" class="admin-overtime-payouts-summary" role="status" aria-live="polite" aria-atomic="true" hidden></div>

		<section class="admin-overtime-payouts-table-section" aria-labelledby="admin-ot-payout-table-heading">
			<h3 id="admin-ot-payout-table-heading" class="admin-settings-section__title"><?php p($l->t('Employees')); ?></h3>
			<div class="table-responsive">
				<table class="grid-table admin-overtime-payouts-table" id="ot-payout-table">
					<caption class="sr-only"><?php p($l->t('Employees with overtime eligible for payout')); ?></caption>
					<thead>
						<tr>
							<th scope="col"><?php p($l->t('Employee')); ?></th>
							<th scope="col"><?php p($l->t('Status')); ?></th>
							<th scope="col"><?php p($l->t('Eligible (h)')); ?></th>
							<th scope="col"><?php p($l->t('Paid (h)')); ?></th>
							<th scope="col"><span class="sr-only"><?php p($l->t('Actions')); ?></span></th>
						</tr>
					</thead>
					<tbody id="ot-payout-tbody">
						<tr><td colspan="5"><?php p($l->t('Choose a month and click Load list.')); ?></td></tr>
					</tbody>
				</table>
			</div>
		</section>

		<div id="ot-payout-live" class="admin-overtime-payouts-live" role="status" aria-live="polite" aria-atomic="true"></div>
	</div>
</main>

<script nonce="<?php p($_['cspNonce'] ?? ''); ?>">
window.ARBEITSZEITCHECK_OT_PAYOUT = {
	bankEnabled: <?php echo $bankEnabled ? 'true' : 'false'; ?>,
	apiList: <?php echo json_encode($urlGenerator->linkToRoute('arbeitszeitcheck.overtime_payout.listMonth'), TemplateL10n::JSON_ENCODE_FLAGS); ?>,
	apiProcess: <?php echo json_encode($urlGenerator->linkToRoute('arbeitszeitcheck.overtime_payout.processOne'), TemplateL10n::JSON_ENCODE_FLAGS); ?>,
	apiBulk: <?php echo json_encode($urlGenerator->linkToRoute('arbeitszeitcheck.overtime_payout.processBulk'), TemplateL10n::JSON_ENCODE_FLAGS); ?>,
	apiExport: <?php echo json_encode($urlGenerator->linkToRoute('arbeitszeitcheck.overtime_payout.exportCsv'), TemplateL10n::JSON_ENCODE_FLAGS); ?>,
	i18n: <?php echo json_encode($otPayoutI18n, TemplateL10n::JSON_ENCODE_FLAGS); ?>
};
</script>
