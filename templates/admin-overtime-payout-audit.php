<?php

declare(strict_types=1);

use OCA\ArbeitszeitCheck\Util\TemplateL10n;

/** @var array $_ */
/** @var \OCP\IL10N $l */
/** @var \OCP\IURLGenerator $urlGenerator */

/** Styles/scripts are registered in OvertimePayoutController::auditIndex(). */
$urlGenerator = $_['urlGenerator'] ?? \OCP\Server::get(\OCP\IURLGenerator::class);
include __DIR__ . '/common/admin-overtime-payout-l10n.php';

$bankEnabled = (bool)($_['bankEnabled'] ?? false);
$notificationsUrl = $urlGenerator->linkToRoute('arbeitszeitcheck.admin.notifications') . '#overtime-bank-heading';
$payoutUrl = $_['payoutProcessUrl'] ?? $urlGenerator->linkToRoute('arbeitszeitcheck.overtime_payout.index');
$defaultYear = (int)($_['defaultYear'] ?? (int)date('Y'));
$monthLabels = is_array($_['monthLabels'] ?? null) ? $_['monthLabels'] : [];
?>

<?php include __DIR__ . '/common/navigation.php'; ?>

<main id="app-content" class="admin-overtime-payout-audit-page" role="main" aria-labelledby="admin-ot-payout-audit-title">
	<div id="app-content-wrapper">
		<div class="section">
			<div class="breadcrumb-container">
				<nav class="breadcrumb" aria-label="<?php p($l->t('Breadcrumb')); ?>">
					<ol>
						<li><a href="<?php p($urlGenerator->linkToRoute('arbeitszeitcheck.admin.dashboard')); ?>"><?php p($l->t('Administration')); ?></a></li>
						<li><a href="<?php p($payoutUrl); ?>"><?php p($l->t('Overtime payouts')); ?></a></li>
						<li aria-current="page"><?php p($l->t('Payout audit')); ?></li>
					</ol>
				</nav>
			</div>

			<div class="section-header section-header--stacked">
				<h1 id="admin-ot-payout-audit-title"><?php p($l->t('Overtime payout audit')); ?></h1>
				<p><?php p($l->t('Read-only registry of recorded overtime payouts. Use filters to review payroll history, open the activity log, or download month-closure PDFs.')); ?></p>
			</div>

			<div class="admin-overtime-payout-audit-header-actions">
				<a href="<?php p($payoutUrl); ?>" class="btn btn--secondary">
					<?php p($l->t('Process payouts')); ?>
				</a>
			</div>

			<?php if (!$bankEnabled): ?>
			<div class="alert alert--warning" role="alert">
				<p>
					<?php p($l->t('The overtime bank is disabled. Historical payout records may still appear below.')); ?>
					<a href="<?php p($notificationsUrl); ?>" class="alert__link"><?php p($l->t('Open overtime bank settings')); ?></a>
				</p>
			</div>
			<?php endif; ?>

			<section class="admin-overtime-payout-audit-filters" aria-labelledby="admin-ot-audit-filter-heading">
				<h2 id="admin-ot-audit-filter-heading" class="admin-overtime-payout-audit-filters__heading"><?php p($l->t('Search payouts')); ?></h2>
				<p id="admin-ot-audit-filter-help" class="sr-only">
					<?php p($l->t('Pick a year, optionally a month and one employee, then apply. Leave employee empty to see everyone.')); ?>
				</p>
				<form id="ot-audit-filter-form" class="admin-overtime-payout-audit-filters__form" novalidate>
					<div class="admin-overtime-payout-audit-filters__bar" role="group" aria-label="<?php p($l->t('Filter options')); ?>">
						<div class="admin-overtime-payout-audit-filters__cluster admin-overtime-payout-audit-filters__cluster--period">
							<div class="admin-overtime-payout-audit-filters__field admin-overtime-payout-audit-filters__field--year">
								<label for="ot-audit-year" class="admin-overtime-payout-audit-filters__label"><?php p($l->t('Year')); ?></label>
								<input type="number" class="form-input" id="ot-audit-year" name="year"
									min="2000" max="2100" step="1" required
									value="<?php p((string)$defaultYear); ?>"
									aria-describedby="admin-ot-audit-filter-help">
							</div>
							<div class="admin-overtime-payout-audit-filters__field admin-overtime-payout-audit-filters__field--month">
								<label for="ot-audit-month" class="admin-overtime-payout-audit-filters__label"><?php p($l->t('Month')); ?></label>
								<select class="form-input" id="ot-audit-month" name="month" aria-describedby="admin-ot-audit-filter-help">
									<option value=""><?php p($l->t('All months')); ?></option>
									<?php for ($m = 1; $m <= 12; $m++): ?>
									<option value="<?php p((string)$m); ?>"><?php p($monthLabels[$m] ?? (string)$m); ?></option>
									<?php endfor; ?>
								</select>
							</div>
						</div>
						<div class="admin-overtime-payout-audit-filters__cluster admin-overtime-payout-audit-filters__cluster--employee">
							<div class="admin-overtime-payout-audit-filters__field admin-overtime-payout-audit-filters__field--employee">
								<label for="ot-audit-employee-search" class="admin-overtime-payout-audit-filters__label"><?php p($l->t('Employee')); ?> <span class="admin-overtime-payout-audit-filters__optional"><?php p($l->t('optional')); ?></span></label>
								<input type="hidden" id="ot-audit-user-id" name="userId" value="">
								<div class="user-picker admin-overtime-payout-audit-filters__picker" id="ot-audit-employee-picker">
									<input type="text"
										id="ot-audit-employee-search"
										class="form-input user-picker__search"
										autocomplete="off"
										autocapitalize="none"
										spellcheck="false"
										placeholder="<?php p($l->t('Search by name or user ID…')); ?>"
										role="combobox"
										aria-autocomplete="list"
										aria-expanded="false"
										aria-controls="ot-audit-employee-listbox"
										aria-describedby="admin-ot-audit-filter-help">
									<button type="button"
										class="admin-overtime-payout-audit-filters__clear"
										id="ot-audit-clear-employee"
										hidden
										aria-label="<?php p($l->t('Clear employee')); ?>">
										<span aria-hidden="true">&times;</span>
									</button>
									<div id="ot-audit-employee-listbox"
										class="user-picker__list"
										role="listbox"
										hidden
										aria-label="<?php p($l->t('Matching employees')); ?>"></div>
								</div>
							</div>
						</div>
						<div class="admin-overtime-payout-audit-filters__cluster admin-overtime-payout-audit-filters__cluster--actions">
							<button type="submit" class="btn btn--primary" id="ot-audit-apply">
								<?php p($l->t('Apply filters')); ?>
							</button>
							<button type="button" class="admin-overtime-payout-audit-filters__reset" id="ot-audit-reset">
								<?php p($l->t('Reset filters')); ?>
							</button>
						</div>
					</div>
				</form>
			</section>

			<div id="ot-audit-summary" class="admin-overtime-stat-banner" role="status" aria-live="polite" aria-atomic="true" hidden></div>

			<section id="ot-audit-gaps-section" class="admin-overtime-panel admin-overtime-payout-audit-gaps" hidden aria-labelledby="ot-audit-gaps-heading">
				<h2 id="ot-audit-gaps-heading" class="admin-overtime-panel__title"><?php p($l->t('Compliance gaps')); ?></h2>
				<p class="form-help form-help--block">
					<?php p($l->t('These employees finalized a month with overtime above the bank cap, but no payout was recorded. Payroll should process the payout or document an exception.')); ?>
				</p>
				<p id="ot-audit-gaps-count" class="admin-overtime-payout-audit-gaps__count" role="status" aria-live="polite"></p>
				<ul id="ot-audit-gaps-list" class="admin-overtime-payout-audit-gaps__list"></ul>
			</section>

			<section class="admin-overtime-panel admin-overtime-payout-audit-table-section" aria-labelledby="ot-audit-table-heading">
				<h2 id="ot-audit-table-heading" class="admin-overtime-panel__title"><?php p($l->t('Recorded payouts')); ?></h2>
				<div class="table-responsive" role="region" aria-labelledby="ot-audit-table-heading">
					<table class="grid-table admin-overtime-payout-audit-table" id="ot-audit-table">
						<caption class="sr-only"><?php p($l->t('Recorded overtime payouts matching the current filters')); ?></caption>
						<thead>
							<tr>
								<th scope="col"><?php p($l->t('Period')); ?></th>
								<th scope="col"><?php p($l->t('Employee')); ?></th>
								<th scope="col" class="admin-overtime-payout-audit-table__num"><?php p($l->t('Hours paid')); ?></th>
								<th scope="col"><?php p($l->t('Processed')); ?></th>
								<th scope="col"><span class="sr-only"><?php p($l->t('Actions')); ?></span></th>
							</tr>
						</thead>
						<tbody id="ot-audit-tbody">
							<tr><td colspan="5"><?php p($l->t('Loading…')); ?></td></tr>
						</tbody>
					</table>
				</div>
			</section>

			<div id="ot-audit-live" class="admin-overtime-payout-audit-live" role="alert" aria-live="assertive" aria-atomic="true"></div>
		</div>
	</div>
</main>
</div><!-- /#arbeitszeitcheck-app -->

<script nonce="<?php p($_['cspNonce'] ?? ''); ?>">
window.ARBEITSZEITCHECK_OT_PAYOUT_AUDIT = {
	apiUrl: <?php echo json_encode($urlGenerator->linkToRoute('arbeitszeitcheck.overtime_payout.listAudit'), TemplateL10n::JSON_ENCODE_FLAGS); ?>,
	payoutProcessUrl: <?php echo json_encode($payoutUrl, TemplateL10n::JSON_ENCODE_FLAGS); ?>,
	adminUserSearchUrl: <?php echo json_encode($_['adminUserSearchUrl'] ?? $urlGenerator->linkToRoute('arbeitszeitcheck.admin.searchVacationLayersUsers'), TemplateL10n::JSON_ENCODE_FLAGS); ?>,
	defaultYear: <?php echo (int)$defaultYear; ?>,
	i18n: <?php echo json_encode($otPayoutAuditI18n, TemplateL10n::JSON_ENCODE_FLAGS); ?>
};
</script>
