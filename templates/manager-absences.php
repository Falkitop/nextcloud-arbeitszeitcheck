<?php

declare(strict_types=1);

use OCA\ArbeitszeitCheck\Service\IconCatalog;

/** @var array $_ */
/** @var \OCP\IL10N $l */
$l = $_['l'] ?? \OCP\Util::getL10N('arbeitszeitcheck');
?>

<?php include __DIR__ . '/common/page-start.php'; ?>


        <div class="section manager-time-entries-page__content">
<section class="section manager-time-entries-page__filters" aria-labelledby="employee-absences-filters-title">
				<header class="manager-time-entries-page__filters-head">
					<h2 id="employee-absences-filters-title"><?php p($l->t('Filter')); ?></h2>
					<p class="manager-time-entries-page__filters-intro">
						<?php p($l->t('Choose who and which period to view, then click Show. Optional: narrow by type or status.')); ?>
					</p>
				</header>
				<form
					id="employee-absences-filter-form"
					class="manager-time-entries-page__filters-form"
					novalidate
					aria-describedby="employee-absences-filter-help employee-absences-filter-error"
				>
					<div class="manager-time-entries-page__filters-grid manager-time-entries-page__filters-grid--extended" role="group" aria-label="<?php p($l->t('Filter options')); ?>">
						<div class="manager-time-entries-page__filter-field">
							<label for="employee-absences-employee-filter" class="manager-time-entries-page__filter-label"><?php p($l->t('Employee')); ?></label>
							<select id="employee-absences-employee-filter" name="employee_id" class="form-select">
								<option value=""><?php p($l->t('All in my scope')); ?></option>
							</select>
						</div>

						<div
							class="manager-time-entries-page__filter-field manager-time-entries-page__filter-field--dates"
							role="group"
							aria-labelledby="employee-absences-date-range-label"
						>
							<span id="employee-absences-date-range-label" class="manager-time-entries-page__filter-label"><?php p($l->t('Date range')); ?></span>
							<div class="manager-time-entries-page__date-range">
								<div class="manager-time-entries-page__date-range-part">
									<label for="employee-absences-start-date-filter" class="manager-time-entries-page__date-range-sublabel"><?php p($l->t('From')); ?></label>
									<input
										id="employee-absences-start-date-filter"
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
									<label for="employee-absences-end-date-filter" class="manager-time-entries-page__date-range-sublabel"><?php p($l->t('To')); ?></label>
									<input
										id="employee-absences-end-date-filter"
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
							<label for="employee-absences-type-filter" class="manager-time-entries-page__filter-label"><?php p($l->t('Type')); ?></label>
							<select id="employee-absences-type-filter" name="type" class="form-select">
								<option value=""><?php p($l->t('All types')); ?></option>
								<option value="vacation"><?php p($l->t('Vacation')); ?></option>
								<option value="sick_leave"><?php p($l->t('Sick leave')); ?></option>
								<option value="personal_leave"><?php p($l->t('Personal leave')); ?></option>
								<option value="parental_leave"><?php p($l->t('Parental leave')); ?></option>
								<option value="special_leave"><?php p($l->t('Special leave')); ?></option>
								<option value="unpaid_leave"><?php p($l->t('Unpaid leave')); ?></option>
								<option value="home_office"><?php p($l->t('Home office')); ?></option>
								<option value="business_trip"><?php p($l->t('Business trip')); ?></option>
							</select>
						</div>

						<div class="manager-time-entries-page__filter-field">
							<label for="employee-absences-status-filter" class="manager-time-entries-page__filter-label"><?php p($l->t('Status')); ?></label>
							<select id="employee-absences-status-filter" name="status" class="form-select">
								<option value=""><?php p($l->t('All Statuses')); ?></option>
								<option value="pending"><?php p($l->t('Pending')); ?></option>
								<option value="substitute_pending"><?php p($l->t('Substitute pending')); ?></option>
								<option value="substitute_declined"><?php p($l->t('Substitute declined')); ?></option>
								<option value="approved"><?php p($l->t('Approved')); ?></option>
								<option value="rejected"><?php p($l->t('Rejected')); ?></option>
								<option value="cancelled"><?php p($l->t('Cancelled')); ?></option>
							</select>
						</div>

						<div class="manager-time-entries-page__filter-actions">
							<span class="manager-time-entries-page__filter-label manager-time-entries-page__filter-label--spacer" aria-hidden="true">&#8203;</span>
							<div class="manager-time-entries-page__filter-actions-btns">
								<button type="submit" class="btn btn--primary" id="employee-absences-submit">
									<?php p($l->t('Show')); ?>
								</button>
								<button type="button" id="employee-absences-clear" class="btn btn--secondary">
									<?php p($l->t('Reset')); ?>
								</button>
							</div>
						</div>
					</div>

					<p id="employee-absences-filter-error" class="manager-time-entries-page__filters-error" role="alert" hidden></p>
					<p id="employee-absences-filter-help" class="manager-time-entries-page__filters-hint" role="note">
						<?php p($l->t('For security and performance, entries load only when you click Show with a valid date range.')); ?>
					</p>
				</form>
			</section>

			<section class="section manager-time-entries-page__filters manager-absence-record" aria-labelledby="manager-absence-record-title">
				<h2 id="manager-absence-record-title" class="manager-absence-record__title"><?php p($l->t('Record approved absence for an employee')); ?></h2>
				<p class="manager-absence-record__desc"><?php p($l->t('Saves immediately as approved. For past or future dates; substitute rules do not apply. Finalized months stay blocked until an administrator reopens them.')); ?></p>
				<div id="manager-absence-record-historical-hint"
				     class="absence-historical-hint manager-absence-record__historical-hint"
				     role="status"
				     aria-live="polite"
				     hidden>
					<span class="absence-historical-hint__icon" aria-hidden="true"><?php print_unescaped(IconCatalog::render('clock', 'absence-historical-hint__icon-svg')); ?></span>
					<div class="absence-historical-hint__body">
						<strong class="absence-historical-hint__title"><?php p($l->t('Historical entry – the dates you selected are in the past')); ?></strong>
						<p class="absence-historical-hint__text"><?php p($l->t('This will be saved immediately as approved and shown with the "Past record" badge in calendars, timelines and reports. Audit trail captures who recorded it.')); ?></p>
					</div>
				</div>
				<form id="manager-absence-record-form" class="manager-time-entries-page__filters-form" novalidate>
					<div class="manager-time-entries-page__filters-grid manager-time-entries-page__filters-grid--record" role="group" aria-label="<?php p($l->t('Record absence')); ?>">
						<div class="manager-time-entries-page__filter-field">
							<label for="manager-absence-record-employee" class="manager-time-entries-page__filter-label"><?php p($l->t('Employee (required)')); ?></label>
							<select id="manager-absence-record-employee" name="record_employee_id" class="form-select" required>
								<option value=""><?php p($l->t('Select an employee')); ?></option>
							</select>
						</div>
						<div class="manager-time-entries-page__filter-field">
							<label for="manager-absence-record-type" class="manager-time-entries-page__filter-label"><?php p($l->t('Type')); ?></label>
							<select id="manager-absence-record-type" name="record_type" class="form-select" required>
								<option value="vacation"><?php p($l->t('Vacation')); ?></option>
								<option value="sick_leave"><?php p($l->t('Sick leave')); ?></option>
								<option value="personal_leave"><?php p($l->t('Personal leave')); ?></option>
								<option value="parental_leave"><?php p($l->t('Parental leave')); ?></option>
								<option value="special_leave"><?php p($l->t('Special leave')); ?></option>
								<option value="unpaid_leave"><?php p($l->t('Unpaid leave')); ?></option>
								<option value="home_office"><?php p($l->t('Home office')); ?></option>
								<option value="business_trip"><?php p($l->t('Business trip')); ?></option>
							</select>
						</div>
						<div class="manager-time-entries-page__filter-field">
							<label for="manager-absence-record-start" class="manager-time-entries-page__filter-label"><?php p($l->t('From')); ?></label>
							<input id="manager-absence-record-start" name="record_start_date" type="text" class="form-input datepicker-input" placeholder="<?php p($l->t('dd.mm.yyyy')); ?>" pattern="\d{2}\.\d{2}\.\d{4}" maxlength="10" readonly required autocomplete="off" aria-label="<?php p($l->t('From')); ?>" />
						</div>
						<div class="manager-time-entries-page__filter-field">
							<label for="manager-absence-record-end" class="manager-time-entries-page__filter-label"><?php p($l->t('To')); ?></label>
							<input id="manager-absence-record-end" name="record_end_date" type="text" class="form-input datepicker-input" placeholder="<?php p($l->t('dd.mm.yyyy')); ?>" pattern="\d{2}\.\d{2}\.\d{4}" maxlength="10" readonly required autocomplete="off" aria-label="<?php p($l->t('To')); ?>" />
						</div>
						<div class="manager-time-entries-page__filter-actions">
							<span class="manager-time-entries-page__filter-label manager-time-entries-page__filter-label--spacer" aria-hidden="true">&#8203;</span>
							<div class="manager-time-entries-page__filter-actions-btns">
								<button type="submit" class="btn btn--primary" id="manager-absence-record-submit"><?php p($l->t('Save as approved')); ?></button>
							</div>
						</div>
					</div>
					<div class="manager-time-entries-page__filter-field manager-time-entries-page__filter-field--full">
						<label for="manager-absence-record-reason" class="manager-time-entries-page__filter-label"><?php p($l->t('Reason')); ?></label>
						<textarea id="manager-absence-record-reason" name="record_reason" class="form-textarea form-input" rows="3" maxlength="8000" placeholder="<?php p($l->t('Optional reason or notes for your absence request')); ?>"></textarea>
					</div>
				</form>
			</section>

			<section class="section manager-time-entries-page__results" aria-labelledby="employee-absences-results-title" aria-busy="false">
				<div class="section-header manager-time-entries-page__results-header">
					<h2 id="employee-absences-results-title"><?php p($l->t('Absences')); ?></h2>
					<p id="employee-absences-count" class="manager-time-entries-page__count" role="status" aria-live="polite"></p>
				</div>

				<div id="employee-absences-empty" class="empty-state">
					<h3 class="empty-state__title"><?php p($l->t('Select filters first')); ?></h3>
					<p class="empty-state__description"><?php p($l->t('Choose a date range to load absences.')); ?></p>
				</div>

				<div id="employee-absences-table-wrap" class="table-container visually-hidden" aria-live="polite">
					<table class="table table--hover" aria-label="<?php p($l->t('Employee absences')); ?>">
						<thead>
							<tr>
								<th scope="col"><?php p($l->t('Name')); ?></th>
								<th scope="col"><?php p($l->t('Type')); ?></th>
								<th scope="col"><?php p($l->t('Start date')); ?></th>
								<th scope="col"><?php p($l->t('End date')); ?></th>
								<th scope="col"><?php p($l->t('Days')); ?></th>
								<th scope="col"><?php p($l->t('Status')); ?></th>
								<th scope="col"><?php p($l->t('Reason')); ?></th>
							</tr>
						</thead>
						<tbody id="employee-absences-body"></tbody>
					</table>
				</div>

				<div class="pagination manager-time-entries-page__pagination">
					<button type="button" id="employee-absences-prev" class="btn btn--secondary" disabled><?php p($l->t('Previous')); ?></button>
					<span id="employee-absences-page-indicator" class="pagination-info"></span>
					<button type="button" id="employee-absences-next" class="btn btn--secondary" disabled><?php p($l->t('Next')); ?></button>
				</div>
			</section>
</div>

<?php include __DIR__ . '/common/manager-employee-list-l10n.php'; ?>

<?php include __DIR__ . '/common/page-end.php'; ?>
