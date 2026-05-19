<?php

declare(strict_types=1);

/**
 * Server-translated strings for manager time-entry correction UI
 * (manager-time-entries.js, manager-dashboard.js pending approvals).
 *
 * @var \OCP\IL10N $l
 */
$l = $l ?? ($_['l'] ?? \OCP\Util::getL10N('arbeitszeitcheck'));

$managerCorrectionStringIds = [
	'Correct',
	'Correct time entry',
	'Could not open correction dialog.',
	'Changes are applied immediately and the employee is notified. A reason is required for the audit log.',
	'Reason (min. 10 characters)',
	'Apply correction',
	'A reason of at least 10 characters is required.',
	'At least one field to correct is required.',
	'Time entry corrected successfully.',
	'Correction failed.',
	'Entry was modified. Reloading…',
	'No pending time entry corrections.',
	'Error loading pending time entry corrections.',
	'Time entry correction',
	'Correction comparison',
	'Current (Ist)',
	'Proposed (Soll)',
	'Start',
	'End',
	'Breaks',
	'Reason:',
	'Time entry correction approved successfully',
	'Failed to approve time entry correction.',
	'Time entry correction rejected',
	'Failed to reject time entry correction.',
	'Approve',
	'Reject',
	'Cancel',
	'Optional reason for rejection (leave empty for none):',
	'Reason for rejection (optional)',
	'Enter reason for rejection...',
	'Confirm rejection',
	'Reject Request',
	'Failed to approve.',
	'Failed to reject.',
];

$managerCorrectionL10n = [];
foreach ($managerCorrectionStringIds as $msgid) {
	$managerCorrectionL10n[$msgid] = $l->t($msgid);
}

?>
<script nonce="<?php p($_['cspNonce'] ?? ''); ?>">
window.ArbeitszeitCheck = window.ArbeitszeitCheck || {};
window.ArbeitszeitCheck.l10n = window.ArbeitszeitCheck.l10n || {};
Object.assign(window.ArbeitszeitCheck.l10n, <?php echo json_encode($managerCorrectionL10n, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>);
</script>
