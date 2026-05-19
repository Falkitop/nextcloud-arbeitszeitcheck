<?php

declare(strict_types=1);

/**
 * Server-translated strings for manager employee list pages (time entries & absences).
 * Never use json_encode($l->t(...)) inline — build a plain string map first.
 *
 * @var \OCP\IL10N $l
 * @var array $_
 */

use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Util\TemplateL10n;

$l = $l ?? ($_['l'] ?? \OCP\Util::getL10N('arbeitszeitcheck'));

$page = (string) ($_['managerEmployeeListPage'] ?? 'time-entries');

$sharedMessageIds = [
	'Loading...',
	'No entries found for the selected filters.',
	'Please select start and end date.',
	'Page {page} of {pages}',
	'{count} entries',
	'All in my scope',
	'Invalid date range. Please use valid dates in YYYY-MM-DD format.',
	'Invalid date range. The start date must be before the end date.',
	'Invalid date format. Please use dd.mm.yyyy (e.g., 15.01.2024).',
];

$timeEntriesMessageIds = [
	'Could not load employee time entries.',
	'No description',
	'Choose a date range to load entries.',
];

$absencesMessageIds = [
	'Could not load employee absences.',
	'No reason',
	'Choose a date range to load absences.',
	'Past record',
	'Select an employee',
	'Absence recorded and approved.',
	'Could not save absence.',
];

$messageIds = array_merge(
	$sharedMessageIds,
	$page === 'absences' ? $absencesMessageIds : $timeEntriesMessageIds,
);

$managerEmployeeListL10n = TemplateL10n::mapFromMessageIds($l, $messageIds);

$maxDays = (int) ($_['maxManagerListDateRangeDays'] ?? Constants::MAX_EXPORT_DATE_RANGE_DAYS);
$managerEmployeeListL10n['dateRangeTooLong'] = TemplateL10n::translate(
	$l,
	'Date range must not exceed %d days. Please narrow the range.',
	[$maxDays],
);

?>
<script nonce="<?php p($_['cspNonce'] ?? ''); ?>">
window.ArbeitszeitCheck = window.ArbeitszeitCheck || {};
window.ArbeitszeitCheck.l10n = window.ArbeitszeitCheck.l10n || {};
window.ArbeitszeitCheck.dateLocale = <?php echo json_encode($l->getLanguageCode(), TemplateL10n::JSON_ENCODE_FLAGS); ?>;
window.ArbeitszeitCheck.maxManagerListDateRangeDays = <?php echo $maxDays; ?>;
Object.assign(window.ArbeitszeitCheck.l10n, <?php echo json_encode($managerEmployeeListL10n, TemplateL10n::JSON_ENCODE_FLAGS); ?>);
</script>
