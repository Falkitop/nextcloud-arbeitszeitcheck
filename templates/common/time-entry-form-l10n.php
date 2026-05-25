<?php

declare(strict_types=1);

use OCA\ArbeitszeitCheck\Util\TemplateL10n;

/**
 * Server-translated strings for js/time-entry-form.js.
 *
 * @var \OCP\IL10N $l
 */
$l = $l ?? ($_['l'] ?? \OCP\Util::getL10N('arbeitszeitcheck'));

$timeEntryFormMessageIds = [
	'breakRowStartLabel' => 'Break %1$s start',
	'breakRowEndLabel' => 'Break %1$s end',
	'autoBreakAddedCompliance' => 'Automatic %s break added for legal compliance',
	'autoBreakNote' => 'Automatically added for German labor law compliance (ArbZG §4)',
	'autoBreakDisabled' => 'Automatic break generation disabled',
	'autoBreakStateOn' => 'Enabled',
	'autoBreakStateOff' => 'Disabled',
	'maxBreaksAllowed' => 'Maximum of %d breaks allowed',
	'breakStartHour' => 'Break start hour',
	'breakStartMinute' => 'Break start minute',
	'breakEndHour' => 'Break end hour',
	'breakEndMinute' => 'Break end minute',
	'removeBreak' => 'Remove break',
	'removeThisBreak' => 'Remove this break',
	'remove' => 'Remove',
	'dateRequired' => 'Date is required',
	'invalidDate' => 'Invalid date',
	'dateFuture' => 'Date cannot be in the future',
	'dateTooOld' => 'Date cannot be more than 1 year in the past',
	'complianceMaxHours' => 'Working hours exceed legal maximum (ArbZG §3)',
	'complianceApproachingMax' => 'Approaching maximum working hours',
	'complianceRecalculatingBreak' => 'Recalculating automatic break...',
	'complianceBreakNotMet' => 'Break requirement not met (ArbZG §4)',
	'complianceShortShift' => 'Short shift - no breaks required',
	'complianceAuto30' => 'Compliant - automatic 30 min break',
	'complianceManual30' => 'Compliant - 30 min break provided',
	'complianceAuto45' => 'Compliant - automatic 45 min break',
	'complianceManual45' => 'Compliant - 45 min break provided',
	'complianceOk' => 'Compliant with German labor law',
	'startTimeRequired' => 'Start time is required',
	'endTimeRequired' => 'End time is required',
	'endAfterStart' => 'End time must be after start time',
	'workMin15' => 'Work period must be at least 15 minutes',
	'workMax16' => 'Work period cannot exceed 16 hours',
	'breaksExceedWork' => 'Total break time cannot exceed work time',
	'breakRequiredNone' => 'No breaks required for shifts under 6 hours',
	'breakRequired30' => '30 minutes break required (ArbZG §4)',
	'breakRequired45' => '45 minutes break required (ArbZG §4)',
	'savedSuccess' => 'Time entry saved successfully',
	'saveError' => 'An error occurred while saving',
	'timeoutError' => 'Request timed out. Please try again.',
	'htmlResponseError' => 'The server returned a login or error page instead of data. Please reload the page or sign in again.',
	'serverError' => 'Server error occurred. Please try again.',
	'missingFieldsError' => 'Please fill in all required fields (date, start time, end time)',
	'invalidDateError' => 'Please enter a valid date',
	'invalidTimesError' => 'Please enter valid start and end times',
	'networkError' => 'Network error occurred',
	'submitting' => 'Submitting...',
	'initFailed' => 'Form initialization failed. Please refresh the page.',
];

$timeEntryFormL10n = [];
foreach ($timeEntryFormMessageIds as $key => $messageId) {
	$timeEntryFormL10n[$key] = TemplateL10n::translate($l, $messageId);
}

?>
<script nonce="<?php p($_['cspNonce'] ?? ''); ?>">
window.ArbeitszeitCheck = window.ArbeitszeitCheck || {};
window.ArbeitszeitCheck.l10n = window.ArbeitszeitCheck.l10n || {};
Object.assign(window.ArbeitszeitCheck.l10n, <?php echo json_encode($timeEntryFormL10n, TemplateL10n::JSON_ENCODE_FLAGS); ?>);
</script>
