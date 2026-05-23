<?php

declare(strict_types=1);

/**
 * Manager dashboard template for arbeitszeitcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */


/** @var array $_ */
/** @var \OCP\IL10N $l */
$l = $_['l'] ?? \OCP\Util::getL10N('arbeitszeitcheck');
/** @var \OCP\IURLGenerator $urlGenerator */
$urlGenerator = $_['urlGenerator'] ?? \OCP\Server::get(\OCP\IURLGenerator::class);

$teamStats = $_['teamStats'] ?? [];
$teamMembers = $_['teamMembers'] ?? [];
?>

<script nonce="<?php p($_['cspNonce'] ?? ''); ?>">
(function() {
	window.ArbeitszeitCheck = window.ArbeitszeitCheck || {};
	window.ArbeitszeitCheck.l10n = window.ArbeitszeitCheck.l10n || {};
	Object.assign(window.ArbeitszeitCheck.l10n, {
		"Absence": <?php echo json_encode($l->t('Absence'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
		"Vacation": <?php echo json_encode($l->t('Vacation'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
		"Sick leave": <?php echo json_encode($l->t('Sick leave'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
		"Personal leave": <?php echo json_encode($l->t('Personal leave'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
		"Parental leave": <?php echo json_encode($l->t('Parental leave'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
		"Special leave": <?php echo json_encode($l->t('Special leave'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
		"Unpaid leave": <?php echo json_encode($l->t('Unpaid leave'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
		"Home office": <?php echo json_encode($l->t('Home office'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
		"Business trip": <?php echo json_encode($l->t('Business trip'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
	});
})();
</script>

<?php include __DIR__ . '/common/navigation.php'; ?>

<main id="app-content" role="main" aria-label="<?php p($l->t('Manager dashboard content')); ?>" class="manager-dashboard">
    <div id="app-content-wrapper">
        <!-- Breadcrumb Navigation -->
        <div class="breadcrumb-container">
            <nav class="breadcrumb" aria-label="<?php p($l->t('Breadcrumb')); ?>">
                <ol>
                    <li><a href="<?php p($urlGenerator->linkToRoute('arbeitszeitcheck.page.index')); ?>"><?php p($l->t('Dashboard')); ?></a></li>
                    <li aria-current="page"><?php p($l->t('Manager Dashboard')); ?></li>
                </ol>
            </nav>
        </div>
        <div class="section manager-dashboard__content">
            <header class="manager-dashboard__header section-header">
                <h1 class="manager-dashboard__title"><?php p($l->t('Manager Dashboard')); ?></h1>
                <p class="manager-dashboard__subtitle"><?php p($l->t('See how your team is doing with time tracking and check for any problems')); ?></p>
            </header>

            <!-- Team Statistics -->
            <section class="manager-dashboard__stats section" aria-labelledby="stats-heading">
                <h2 id="stats-heading" class="visually-hidden"><?php p($l->t('Team statistics')); ?></h2>
                <div class="stats-grid manager-stats-grid">
                    <?php
                    $totalMembers   = (int)($teamStats['total_members'] ?? 0);
                    $activeToday    = (int)($teamStats['active_today'] ?? 0);
                    $hoursToday     = round((float)($teamStats['total_hours_today'] ?? 0), 1);
                    $pendingAbsences = (int)($teamStats['pending_absences'] ?? 0);
                    ?>
                    <div class="stat-card manager-stat-card" role="group"
                        aria-label="<?php p($l->n('%n team member', '%n team members', $totalMembers)); ?>">
                        <span class="stat-number" aria-hidden="true"><?php p($totalMembers); ?></span>
                        <span class="stat-label" aria-hidden="true"><?php p($l->t('Team Members')); ?></span>
                    </div>
                    <div class="stat-card manager-stat-card" role="group"
                        aria-label="<?php p($l->n('%n team member active today', '%n team members active today', $activeToday)); ?>">
                        <span class="stat-number" aria-hidden="true"><?php p($activeToday); ?></span>
                        <span class="stat-label" aria-hidden="true"><?php p($l->t('Active Today')); ?></span>
                    </div>
                    <div class="stat-card manager-stat-card" role="group"
                        aria-label="<?php p($l->t('%s hours worked today by the team', [(string)$hoursToday])); ?>">
                        <span class="stat-number" aria-hidden="true"><?php p($hoursToday); ?>h</span>
                        <span class="stat-label" aria-hidden="true"><?php p($l->t('Hours Today')); ?></span>
                    </div>
                    <a class="stat-card manager-stat-card manager-stat-card--link" href="#pending-approvals-section"
                        aria-label="<?php p($l->n('%n pending absence request — jump to approvals', '%n pending absence requests — jump to approvals', $pendingAbsences)); ?>">
                        <span class="stat-number" aria-hidden="true"><?php p($pendingAbsences); ?></span>
                        <span class="stat-label" aria-hidden="true"><?php p($l->t('Pending Absences')); ?></span>
                    </a>
                </div>
            </section>

            <!-- Pending Approvals: Absences & Time Entry Corrections -->
            <section class="manager-dashboard__approvals section section--pending-approvals" id="pending-approvals-section" aria-labelledby="pending-approvals-title">
                <div class="section-header">
                    <h2 id="pending-approvals-title"><?php p($l->t('Pending approvals')); ?></h2>
                    <p class="section__desc"><?php p($l->t('Review and approve or reject absence requests and time entry corrections from your team.')); ?></p>
                </div>
                <div class="pending-approvals-tabs" role="tablist" aria-label="<?php p($l->t('Filter pending approvals by type')); ?>">
                    <button type="button" class="pending-approvals-tab pending-approvals-tab--active" role="tab" aria-selected="true" aria-controls="pending-absences-panel" id="tab-absences"><?php p($l->t('Absences')); ?></button>
                    <button type="button" class="pending-approvals-tab" role="tab" aria-selected="false" aria-controls="pending-time-entries-panel" id="tab-time-entries"><?php p($l->t('Time entry corrections')); ?></button>
                </div>
                <div id="pending-absences-panel" class="pending-approvals-panel" role="tabpanel" aria-labelledby="tab-absences">
                    <div id="pending-approvals-list" class="pending-approvals-list" role="region" aria-live="polite" aria-label="<?php p($l->t('List of pending absence requests')); ?>">
                        <p class="pending-approvals-loading" id="pending-approvals-loading" aria-hidden="false"><?php p($l->t('Loading…')); ?></p>
                        <div id="pending-approvals-items" class="pending-approvals-items" aria-hidden="true"></div>
                        <p class="pending-approvals-empty visually-hidden" id="pending-approvals-empty"><?php p($l->t('No pending absence requests.')); ?></p>
                    </div>
                </div>
                <div id="pending-time-entries-panel" class="pending-approvals-panel pending-approvals-panel--hidden" role="tabpanel" aria-labelledby="tab-time-entries" aria-hidden="true">
                    <div id="pending-time-entries-list" class="pending-approvals-list" role="region" aria-live="polite" aria-label="<?php p($l->t('List of pending time entry corrections')); ?>">
                        <p class="pending-approvals-loading" id="pending-time-entries-loading" aria-hidden="true"><?php p($l->t('Loading…')); ?></p>
                        <div id="pending-time-entries-items" class="pending-approvals-items" aria-hidden="true"></div>
                        <p class="pending-approvals-empty visually-hidden" id="pending-time-entries-empty"><?php p($l->t('No pending time entry corrections.')); ?></p>
                    </div>
                </div>
            </section>

            <!-- Team overtime alerts (traffic light + bank) -->
            <section class="manager-dashboard__overtime section" id="team-overtime-section" aria-labelledby="team-overtime-title">
                <div class="section-header section-header--actions">
                    <div>
                        <h2 id="team-overtime-title"><?php p($l->t('Team overtime alerts')); ?></h2>
                        <p class="section__desc"><?php p($l->t('Employees who reached overtime or undertime thresholds, or whose overtime bank needs attention.')); ?></p>
                    </div>
                    <a id="team-overtime-export" class="btn btn--secondary btn--small"
                       href="<?php p($urlGenerator->linkToRoute('arbeitszeitcheck.manager.exportTeamOvertimeCsv')); ?>"
                       download
                       aria-label="<?php p($l->t('Download team overtime overview as CSV')); ?>">
                        <?php p($l->t('Export CSV')); ?>
                    </a>
                </div>
                <div id="team-overtime-content" class="team-overtime-content" role="region" aria-live="polite">
                    <p class="team-overtime-loading" id="team-overtime-loading"><?php p($l->t('Loading…')); ?></p>
                    <div id="team-overtime-summary" class="team-overtime-summary visually-hidden" aria-hidden="true"></div>
                </div>
            </section>

            <!-- Team Compliance Overview -->
            <section class="manager-dashboard__compliance section" id="team-compliance-section" aria-labelledby="team-compliance-title">
                <div class="section-header">
                    <h2 id="team-compliance-title"><?php p($l->t('Team compliance')); ?></h2>
                    <p class="section__desc"><?php p($l->t('Overview of working time compliance across your team.')); ?></p>
                </div>
                <div id="team-compliance-content" class="team-compliance-content" role="region" aria-live="polite">
                    <p class="team-compliance-loading" id="team-compliance-loading" aria-hidden="false"><?php p($l->t('Loading…')); ?></p>
                    <div id="team-compliance-summary" class="team-compliance-summary visually-hidden" aria-hidden="true"></div>
                </div>
            </section>

            <!-- Team Members -->
            <section class="manager-dashboard__team section" aria-labelledby="team-members-title">
                <div class="section-header">
                    <h2 id="team-members-title"><?php p($l->t('Team Members')); ?></h2>
                </div>

                <?php if (empty($teamMembers)): ?>
                    <div class="empty-state">
                        <p><?php p($l->t('No team members found')); ?></p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive" role="region" aria-label="<?php p($l->t('Team members overview')); ?>">
                        <table class="table" role="table" aria-label="<?php p($l->t('Team members overview')); ?>">
                            <thead>
                                <tr>
                                    <th scope="col"><?php p($l->t('Name')); ?></th>
                                    <th scope="col"><?php p($l->t('Hours Today')); ?></th>
                                    <th scope="col"><?php p($l->t('Status')); ?></th>
                                    <th scope="col"><?php p($l->t('Pending Absences')); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (($teamMembers ?? []) as $member): ?>
                                    <tr>
                                        <td><?php p($member['displayName']); ?></td>
                                        <td><?php p(round($member['todayHours'], 2)); ?>h</td>
                                        <td>
                                            <?php
                                            $statusLabels = [
                                                'active' => $l->t('Clocked In'),
                                                'break' => $l->t('On Break'),
                                                'clocked_out' => $l->t('Clocked Out')
                                            ];
                                            $statusLabel = $statusLabels[$member['status']] ?? $member['status'];
                                            ?>
                                            <span class="badge badge--primary"><?php p($statusLabel); ?></span>
                                        </td>
                                        <td><?php p($member['pendingAbsences']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </div>
</main>
</div><!-- /#arbeitszeitcheck-app -->

<?php include __DIR__ . '/common/manager-correction-l10n.php'; ?>

<script nonce="<?php p($_['cspNonce'] ?? ''); ?>">
    window.ArbeitszeitCheck = window.ArbeitszeitCheck || {};
    window.ArbeitszeitCheck.l10n = window.ArbeitszeitCheck.l10n || {};
    Object.assign(window.ArbeitszeitCheck.l10n, {
        "No pending absence requests.": <?php echo json_encode($l->t('No pending absence requests.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        "No pending time entry corrections.": <?php echo json_encode($l->t('No pending time entry corrections.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        "Error loading pending approvals.": <?php echo json_encode($l->t('Error loading pending approvals.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        "Error loading pending time entry corrections.": <?php echo json_encode($l->t('Error loading pending time entry corrections.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        "days": <?php echo json_encode($l->t('days'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        "Approve": <?php echo json_encode($l->t('Approve'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        "Reject": <?php echo json_encode($l->t('Reject'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        "Absence approved.": <?php echo json_encode($l->t('Absence approved.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        "Failed to approve.": <?php echo json_encode($l->t('Failed to approve.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        "Failed to approve absence.": <?php echo json_encode($l->t('Failed to approve absence.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        "Optional reason for rejection (leave empty for none):": <?php echo json_encode($l->t('Optional reason for rejection (leave empty for none):'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        "Reason for rejection (optional)": <?php echo json_encode($l->t('Reason for rejection (optional)'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        "Enter reason for rejection...": <?php echo json_encode($l->t('Enter reason for rejection...'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        "Cancel": <?php echo json_encode($l->t('Cancel'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        "Confirm rejection": <?php echo json_encode($l->t('Confirm rejection'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        "Reject Request": <?php echo json_encode($l->t('Reject Request'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        "Absence rejected.": <?php echo json_encode($l->t('Absence rejected.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        "Failed to reject.": <?php echo json_encode($l->t('Failed to reject.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        "Failed to reject absence.": <?php echo json_encode($l->t('Failed to reject absence.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        "Time entry correction": <?php echo json_encode($l->t('Time entry correction'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        "Original:": <?php echo json_encode($l->t('Original:'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        "Proposed:": <?php echo json_encode($l->t('Proposed:'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        "Time entry correction approved successfully": <?php echo json_encode($l->t('Time entry correction approved successfully'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        "Failed to approve time entry correction.": <?php echo json_encode($l->t('Failed to approve time entry correction.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        "Time entry correction rejected": <?php echo json_encode($l->t('Time entry correction rejected'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        "Failed to reject time entry correction.": <?php echo json_encode($l->t('Failed to reject time entry correction.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        "Unable to load compliance data.": <?php echo json_encode($l->t('Unable to load compliance data.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        "Error loading team compliance.": <?php echo json_encode($l->t('Error loading team compliance.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        "Compliant": <?php echo json_encode($l->t('Compliant'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        "Warnings": <?php echo json_encode($l->t('Warnings'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        "Critical Violations": <?php echo json_encode($l->t('Critical Violations'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        "Total Violations": <?php echo json_encode($l->t('Total Violations'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        "Some team members have compliance issues. Check the Compliance section for details.": <?php echo json_encode($l->t('Some team members have compliance issues. Check the Compliance section for details.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        "All team members are compliant.": <?php echo json_encode($l->t('All team members are compliant.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        "No team members.": <?php echo json_encode($l->t('No team members.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        "Absence": <?php echo json_encode($l->t('Absence'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        "Vacation": <?php echo json_encode($l->t('Vacation'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        "Sick leave": <?php echo json_encode($l->t('Sick leave'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        "Personal leave": <?php echo json_encode($l->t('Personal leave'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        "Parental leave": <?php echo json_encode($l->t('Parental leave'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        "Special leave": <?php echo json_encode($l->t('Special leave'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        "Unpaid leave": <?php echo json_encode($l->t('Unpaid leave'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        "Home office": <?php echo json_encode($l->t('Home office'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        "Business trip": <?php echo json_encode($l->t('Business trip'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
    });
</script>
