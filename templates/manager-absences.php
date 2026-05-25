<?php

declare(strict_types=1);

use OCA\ArbeitszeitCheck\Service\IconCatalog;

/** @var array $_ */
/** @var \OCP\IL10N $l */
$l = $_['l'] ?? \OCP\Util::getL10N('arbeitszeitcheck');
?>

<?php include __DIR__ . '/common/page-start.php'; ?>

<div class="azc-page-stack">
	<div class="manager-scope-page manager-scope-page--absences">

		<section class="azc-card manager-scope-page__results" aria-labelledby="employee-absences-results-title" aria-busy="false">
			<header class="azc-card__header">
				<div class="azc-card__header-text">
					<h2 id="employee-absences-results-title" class="azc-card__title"><?php p($l->t('Employee absences')); ?></h2>
					<p class="azc-card__lead">
						<?php p($l->t('Choose who and which period to view, then click Show. The table below lists matching requests.')); ?>
					</p>
				</div>
				<p id="employee-absences-count" class="azc-badge azc-badge--neutral" role="status" aria-live="polite"></p>
			</header>
			<div class="azc-card__body">
				<div class="manager-scope-page__toolbar" role="search" aria-label="<?php p($l->t('Filter employee absences')); ?>">
					<form
						id="employee-absences-filter-form"
						class="manager-scope-page__filter-form"
						novalidate
						aria-describedby="employee-absences-filter-help employee-absences-filter-error"
					>
						<div class="manager-scope-page__filter-grid manager-scope-page__filter-grid--extended azc-filter-grid azc-filter-grid--extended" role="group" aria-label="<?php p($l->t('Filter options')); ?>">
							<div class="azc-filter-field">
								<label for="employee-absences-employee-filter" class="azc-filter-field__label"><?php p($l->t('Employee')); ?></label>
								<div class="azc-filter-field__control">
									<select id="employee-absences-employee-filter" name="employee_id" class="form-select">
										<option value=""><?php p($l->t('All in my scope')); ?></option>
									</select>
								</div>
							</div>

							<div
								class="azc-filter-field azc-filter-field--dates"
								role="group"
								aria-labelledby="employee-absences-date-range-label"
							>
								<span id="employee-absences-date-range-label" class="azc-filter-field__label"><?php p($l->t('Date range')); ?></span>
								<div class="azc-filter-field__control">
									<div class="azc-date-range">
										<div class="azc-date-range__part">
											<label for="employee-absences-start-date-filter" class="azc-date-range__sublabel"><?php p($l->t('From')); ?></label>
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
										<span class="azc-date-range__sep" aria-hidden="true"><?php p($l->t('to')); ?></span>
										<div class="azc-date-range__part">
											<label for="employee-absences-end-date-filter" class="azc-date-range__sublabel"><?php p($l->t('To')); ?></label>
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
							</div>

							<div class="azc-filter-field">
								<label for="employee-absences-type-filter" class="azc-filter-field__label"><?php p($l->t('Type')); ?></label>
								<div class="azc-filter-field__control">
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
							</div>

							<div class="azc-filter-field">
								<label for="employee-absences-status-filter" class="azc-filter-field__label"><?php p($l->t('Status')); ?></label>
								<div class="azc-filter-field__control">
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
							</div>

							<div class="azc-filter-actions">
								<button type="submit" class="azc-btn azc-btn--primary" id="employee-absences-submit">
									<?php p($l->t('Show')); ?>
								</button>
								<button type="button" id="employee-absences-clear" class="azc-btn azc-btn--secondary">
									<?php p($l->t('Reset')); ?>
								</button>
							</div>
						</div>

						<p id="employee-absences-filter-error" class="azc-callout azc-callout--danger manager-scope-page__toolbar-feedback" role="alert" hidden>
							<span class="azc-callout__text"></span>
						</p>
					</form>
					<p id="employee-absences-filter-help" class="manager-scope-page__toolbar-footnote" role="note">
						<?php p($l->t('Entries load only after you click Show with a valid date range.')); ?>
					</p>
				</div>

				<div id="employee-absences-empty" class="azc-empty-state">
					<h3 class="azc-empty-state__title"><?php p($l->t('Select filters first')); ?></h3>
					<p class="azc-empty-state__text"><?php p($l->t('Choose a date range to load absences.')); ?></p>
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

				<nav class="manager-scope-page__pagination" aria-label="<?php p($l->t('Results pagination')); ?>">
					<button type="button" id="employee-absences-prev" class="azc-btn azc-btn--secondary" disabled><?php p($l->t('Previous')); ?></button>
					<span id="employee-absences-page-indicator" class="manager-scope-page__pagination-info"></span>
					<button type="button" id="employee-absences-next" class="azc-btn azc-btn--secondary" disabled><?php p($l->t('Next')); ?></button>
				</nav>
			</div>
		</section>

		<section class="azc-card manager-scope-page__record" aria-labelledby="manager-absence-record-title">
			<header class="azc-card__header">
				<div class="azc-card__header-text">
					<h2 id="manager-absence-record-title" class="azc-card__title"><?php p($l->t('Record approved absence for an employee')); ?></h2>
					<p class="azc-card__lead">
						<?php p($l->t('Saves immediately as approved. For past or future dates; substitute rules do not apply. Finalized months stay blocked until an administrator reopens them.')); ?>
					</p>
				</div>
			</header>
			<div class="azc-card__body">
				<div id="manager-absence-record-historical-hint"
					class="azc-callout azc-callout--info manager-scope-page__historical-hint"
					role="status"
					aria-live="polite"
					hidden>
					<span class="azc-callout__icon" aria-hidden="true"><?php print_unescaped(IconCatalog::render('clock', 'azc-callout__icon-svg')); ?></span>
					<div>
						<p class="azc-callout__title"><?php p($l->t('Historical entry – the dates you selected are in the past')); ?></p>
						<p class="azc-callout__text">
							<?php p($l->t('This will be saved immediately as approved and shown with the "Past record" badge in calendars, timelines and reports. Audit trail captures who recorded it.')); ?>
						</p>
					</div>
				</div>
				<form id="manager-absence-record-form" class="azc-filter-panel__form" novalidate>
					<div class="azc-filter-grid azc-filter-grid--record" role="group" aria-label="<?php p($l->t('Record absence')); ?>">
						<div class="azc-filter-field">
							<label for="manager-absence-record-employee" class="azc-filter-field__label"><?php p($l->t('Employee (required)')); ?></label>
							<div class="azc-filter-field__control">
								<select id="manager-absence-record-employee" name="record_employee_id" class="form-select" required>
									<option value=""><?php p($l->t('Select an employee')); ?></option>
								</select>
							</div>
						</div>
						<div class="azc-filter-field">
							<label for="manager-absence-record-type" class="azc-filter-field__label"><?php p($l->t('Type')); ?></label>
							<div class="azc-filter-field__control">
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
						</div>
						<div class="azc-filter-field">
							<label for="manager-absence-record-start" class="azc-filter-field__label"><?php p($l->t('From')); ?></label>
							<div class="azc-filter-field__control">
								<input id="manager-absence-record-start" name="record_start_date" type="text" class="form-input datepicker-input" placeholder="<?php p($l->t('dd.mm.yyyy')); ?>" pattern="\d{2}\.\d{2}\.\d{4}" maxlength="10" readonly required autocomplete="off" aria-label="<?php p($l->t('From')); ?>" />
							</div>
						</div>
						<div class="azc-filter-field">
							<label for="manager-absence-record-end" class="azc-filter-field__label"><?php p($l->t('To')); ?></label>
							<div class="azc-filter-field__control">
								<input id="manager-absence-record-end" name="record_end_date" type="text" class="form-input datepicker-input" placeholder="<?php p($l->t('dd.mm.yyyy')); ?>" pattern="\d{2}\.\d{2}\.\d{4}" maxlength="10" readonly required autocomplete="off" aria-label="<?php p($l->t('To')); ?>" />
							</div>
						</div>
						<div class="azc-filter-actions">
							<button type="submit" class="azc-btn azc-btn--primary" id="manager-absence-record-submit"><?php p($l->t('Save as approved')); ?></button>
						</div>
					</div>
					<div class="manager-scope-page__reason-field">
						<label for="manager-absence-record-reason" class="azc-filter-field__label"><?php p($l->t('Reason')); ?></label>
						<textarea id="manager-absence-record-reason" name="record_reason" class="form-textarea form-input" rows="3" maxlength="8000" placeholder="<?php p($l->t('Optional reason or notes for your absence request')); ?>"></textarea>
					</div>
				</form>
			</div>
		</section>

	</div>
</div>

<?php include __DIR__ . '/common/manager-employee-list-l10n.php'; ?>

<?php include __DIR__ . '/common/page-end.php'; ?>
