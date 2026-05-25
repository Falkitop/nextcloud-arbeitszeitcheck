<?php

declare(strict_types=1);

/** @var array $_ */
/** @var \OCP\IL10N $l */
$l = $_['l'] ?? \OCP\Util::getL10N('arbeitszeitcheck');
?>

<?php include __DIR__ . '/common/page-start.php'; ?>


        <div class="section manager-time-entries-page__content">
<section class="section manager-time-entries-page__filters" aria-labelledby="employee-time-entries-filters-title">
				<header class="manager-time-entries-page__filters-head">
					<h2 id="employee-time-entries-filters-title"><?php p($l->t('Filter')); ?></h2>
					<p class="manager-time-entries-page__filters-intro">
						<?php p($l->t('Choose who and which period to view, then click Show. Optional: narrow by status.')); ?>
					</p>
				</header>
				<form
					id="employee-time-entries-filter-form"
					class="manager-time-entries-page__filters-form"
					novalidate
					aria-describedby="employee-time-entries-filter-help employee-time-entries-filter-error"
				>
					<div class="manager-time-entries-page__filters-grid" role="group" aria-label="<?php p($l->t('Filter options')); ?>">
						<div class="manager-time-entries-page__filter-field">
							<label for="employee-filter" class="manager-time-entries-page__filter-label"><?php p($l->t('Employee')); ?></label>
							<select id="employee-filter" name="employee_id" class="form-select">
								<option value=""><?php p($l->t('All in my scope')); ?></option>
							</select>
						</div>

						<div
							class="manager-time-entries-page__filter-field manager-time-entries-page__filter-field--dates"
							role="group"
							aria-labelledby="employee-time-entries-date-range-label"
						>
							<span id="employee-time-entries-date-range-label" class="manager-time-entries-page__filter-label"><?php p($l->t('Date range')); ?></span>
							<div class="manager-time-entries-page__date-range">
								<div class="manager-time-entries-page__date-range-part">
									<label for="start-date-filter" class="manager-time-entries-page__date-range-sublabel"><?php p($l->t('From')); ?></label>
									<input
										id="start-date-filter"
										name="start_date"
										type="text"
										class="form-input datepicker-input"
										placeholder="<?php p($l->t('dd.mm.yyyy')); ?>"
										pattern="\d{2}\.\d{2}\.\d{4}"
										maxlength="10"
										readonly
										required
										autocomplete="off"
										aria-required="true"
										aria-label="<?php p($l->t('From')); ?>"
									/>
								</div>
								<span class="manager-time-entries-page__date-range-sep" aria-hidden="true"><?php p($l->t('to')); ?></span>
								<div class="manager-time-entries-page__date-range-part">
									<label for="end-date-filter" class="manager-time-entries-page__date-range-sublabel"><?php p($l->t('To')); ?></label>
									<input
										id="end-date-filter"
										name="end_date"
										type="text"
										class="form-input datepicker-input"
										placeholder="<?php p($l->t('dd.mm.yyyy')); ?>"
										pattern="\d{2}\.\d{2}\.\d{4}"
										maxlength="10"
										readonly
										required
										autocomplete="off"
										aria-required="true"
										aria-label="<?php p($l->t('To')); ?>"
									/>
								</div>
							</div>
						</div>

						<div class="manager-time-entries-page__filter-field">
							<label for="status-filter" class="manager-time-entries-page__filter-label"><?php p($l->t('Status')); ?></label>
							<select id="status-filter" name="status" class="form-select">
								<option value=""><?php p($l->t('All Statuses')); ?></option>
								<option value="active"><?php p($l->t('Clocked In')); ?></option>
								<option value="break"><?php p($l->t('On Break')); ?></option>
								<option value="paused"><?php p($l->t('Paused')); ?></option>
								<option value="completed"><?php p($l->t('Completed')); ?></option>
								<option value="pending_approval"><?php p($l->t('Pending Approval')); ?></option>
								<option value="rejected"><?php p($l->t('Rejected')); ?></option>
							</select>
						</div>

						<div class="manager-time-entries-page__filter-actions">
							<span class="manager-time-entries-page__filter-label manager-time-entries-page__filter-label--spacer" aria-hidden="true">&#8203;</span>
							<div class="manager-time-entries-page__filter-actions-btns">
								<button type="submit" class="btn btn--primary" id="employee-time-entries-submit">
									<?php p($l->t('Show')); ?>
								</button>
								<button type="button" id="employee-time-entries-clear" class="btn btn--secondary">
									<?php p($l->t('Reset')); ?>
								</button>
							</div>
						</div>
					</div>

					<p id="employee-time-entries-filter-error" class="manager-time-entries-page__filters-error" role="alert" hidden></p>
					<p id="employee-time-entries-filter-help" class="manager-time-entries-page__filters-hint" role="note">
						<?php p($l->t('For security and performance, entries load only when you click Show with a valid date range.')); ?>
					</p>
				</form>
			</section>

			<section class="section manager-time-entries-page__results" aria-labelledby="employee-time-entries-results-title" aria-busy="false">
				<div class="section-header manager-time-entries-page__results-header">
					<h2 id="employee-time-entries-results-title"><?php p($l->t('Time Entries')); ?></h2>
					<p id="employee-time-entries-count" class="manager-time-entries-page__count" role="status" aria-live="polite"></p>
				</div>

				<div id="employee-time-entries-empty" class="empty-state">
					<h3 class="empty-state__title"><?php p($l->t('Select filters first')); ?></h3>
					<p class="empty-state__description"><?php p($l->t('Choose a date range to load entries.')); ?></p>
				</div>

				<div id="employee-time-entries-table-wrap" class="table-container visually-hidden" aria-live="polite">
					<table class="table table--hover" aria-label="<?php p($l->t('Employee time entries')); ?>">
						<thead>
							<tr>
								<th scope="col"><?php p($l->t('Name')); ?></th>
								<th scope="col"><?php p($l->t('Date')); ?></th>
								<th scope="col"><?php p($l->t('Start')); ?></th>
								<th scope="col"><?php p($l->t('End')); ?></th>
								<th scope="col"><?php p($l->t('Working Hours')); ?></th>
								<th scope="col"><?php p($l->t('Break')); ?></th>
								<th scope="col"><?php p($l->t('Status')); ?></th>
								<th scope="col"><?php p($l->t('Description')); ?></th>
								<th scope="col"><?php p($l->t('Actions')); ?></th>
							</tr>
						</thead>
						<tbody id="employee-time-entries-body"></tbody>
					</table>
				</div>

				<div class="pagination manager-time-entries-page__pagination">
					<button type="button" id="employee-time-entries-prev" class="btn btn--secondary" disabled><?php p($l->t('Previous')); ?></button>
					<span id="employee-time-entries-page-indicator" class="pagination-info"></span>
					<button type="button" id="employee-time-entries-next" class="btn btn--secondary" disabled><?php p($l->t('Next')); ?></button>
				</div>
			</section>
</div>

<?php include __DIR__ . '/common/manager-correction-l10n.php'; ?>

<?php include __DIR__ . '/common/manager-employee-list-l10n.php'; ?>

<?php include __DIR__ . '/common/page-end.php'; ?>
