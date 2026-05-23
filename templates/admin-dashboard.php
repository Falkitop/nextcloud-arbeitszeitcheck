<?php

declare(strict_types=1);

/**
 * Admin dashboard template for arbeitszeitcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */


/** @var array $_ */
/** @var \OCP\IL10N $l */
$l = $_['l'] ?? \OCP\Util::getL10N('arbeitszeitcheck');
?>

<?php include __DIR__ . '/common/navigation.php'; ?>

<main id="app-content" role="main" aria-label="<?php p($l->t('Admin dashboard content')); ?>" class="admin-dashboard">
    <div id="app-content-wrapper">
        <div class="section">
            <div class="section-header">
                <h1><?php p($l->t('Administration - Status')); ?></h1>
                <p><?php p($l->t('Current key metrics and open working-time compliance issues. Detailed settings are available in the left navigation.')); ?></p>
            </div>

            <?php
            $overtimeOnboarding = $_['overtime_onboarding'] ?? [];
            $showOvertimeBanner = !empty($overtimeOnboarding['show_banner']);
            $usersAdminUrl = $_['urlGenerator']->linkToRoute('arbeitszeitcheck.admin.users');
            $violationsUrl = $_['urlGenerator']->linkToRoute('arbeitszeitcheck.compliance.violations');
            ?>
            <?php if ($showOvertimeBanner): ?>
            <div id="admin-overtime-onboarding-banner" class="callout callout--warning admin-overtime-onboarding" role="region" aria-labelledby="admin-overtime-onboarding-title">
                <h2 id="admin-overtime-onboarding-title" class="callout__title"><?php p($l->t('Configure overtime balances')); ?></h2>
                <p class="callout__text">
                    <?php p($l->t('%s of %s employees have no overtime tracking start date (Stichtag). Without it, year-to-date balances are calculated from 1 January and may show large undertime until configured.', [
                        (string)($overtimeOnboarding['without_tracking'] ?? 0),
                        (string)($overtimeOnboarding['total_users'] ?? 0),
                    ])); ?>
                </p>
                <p class="callout__actions">
                    <a id="admin-overtime-onboarding-link" class="btn btn--secondary btn--sm" href="<?php p($usersAdminUrl); ?>">
                        <?php p($l->t('Open user administration')); ?>
                    </a>
                </p>
            </div>
            <?php endif; ?>

            <?php
            $overtimePolicy = $_['overtime_policy'] ?? [];
            $notificationsUrl = $_['urlGenerator']->linkToRoute('arbeitszeitcheck.admin.notifications');
            $payoutsUrl = $_['urlGenerator']->linkToRoute('arbeitszeitcheck.overtime_payout.index');
            ?>
            <?php if (!empty($overtimePolicy['bank_enabled']) || !empty($overtimePolicy['traffic_light_enabled'])): ?>
            <section class="admin-overtime-policy card" aria-labelledby="admin-overtime-policy-title">
                <div class="card-header">
                    <h2 id="admin-overtime-policy-title" class="card-title"><?php p($l->t('Overtime policy (active)')); ?></h2>
                    <p class="form-help"><?php p($l->t('Summary of current overtime settings. Change values under Notifications & overtime.')); ?></p>
                </div>
                <div class="card-body admin-overtime-policy__grid">
                    <dl>
                        <dt><?php p($l->t('Balance alerts')); ?></dt>
                        <dd><?php p(!empty($overtimePolicy['traffic_light_enabled']) ? $l->t('On') : $l->t('Off')); ?></dd>
                        <?php if (!empty($overtimePolicy['traffic_light_enabled'])): ?>
                        <dt><?php p($l->t('Alert thresholds (h)')); ?></dt>
                        <dd><?php p($l->t('Over: yellow %s / red %s · Under: yellow %s / red %s', [
                            (string)($overtimePolicy['threshold_yellow_over'] ?? ''),
                            (string)($overtimePolicy['threshold_red_over'] ?? ''),
                            (string)($overtimePolicy['threshold_yellow_under'] ?? ''),
                            (string)($overtimePolicy['threshold_red_under'] ?? ''),
                        ])); ?></dd>
                        <?php endif; ?>
                    </dl>
                    <dl>
                        <dt><?php p($l->t('Overtime bank')); ?></dt>
                        <dd><?php p(!empty($overtimePolicy['bank_enabled']) ? $l->t('On') : $l->t('Off')); ?></dd>
                        <?php if (!empty($overtimePolicy['bank_enabled'])): ?>
                        <dt><?php p($l->t('Bank cap')); ?></dt>
                        <dd><?php p($l->t('%s h (yellow %s%% · red %s%%)', [
                            number_format((float)($overtimePolicy['bank_max_hours'] ?? 100), 0),
                            (string)($overtimePolicy['bank_yellow_percent'] ?? 80),
                            (string)($overtimePolicy['bank_red_percent'] ?? 95),
                        ])); ?></dd>
                        <dt><?php p($l->t('Block month closure until payout')); ?></dt>
                        <dd><?php p(!empty($overtimePolicy['block_month_closure_pending_payout']) ? $l->t('Yes') : $l->t('No')); ?></dd>
                        <?php endif; ?>
                    </dl>
                </div>
                <div class="card-footer admin-overtime-policy__actions">
                    <a class="btn btn--secondary btn--small" href="<?php p($notificationsUrl); ?>"><?php p($l->t('Notification settings')); ?></a>
                    <?php if (!empty($overtimePolicy['bank_enabled'])): ?>
                    <a class="btn btn--secondary btn--small" href="<?php p($payoutsUrl); ?>"><?php p($l->t('Overtime payouts')); ?></a>
                    <?php endif; ?>
                </div>
            </section>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <button type="button" class="stat-card stat-card--drilldown" data-stat="total_users" data-drilldown-filter="all"
                    aria-expanded="false"
                    aria-label="<?php p($l->t('Total employees: %s. Show employee list.', [$_['statistics']['total_users'] ?? 0])); ?>">
                    <span class="stat-number"><?php p($_['statistics']['total_users'] ?? 0); ?></span>
                    <span class="stat-label"><?php p($l->t('Total employees')); ?></span>
                </button>
                <button type="button" class="stat-card stat-card--drilldown" data-stat="active_users_today" data-drilldown-filter="active_today"
                    aria-expanded="false"
                    aria-label="<?php p($l->t('Employees active today: %s. Show list.', [$_['statistics']['active_users_today'] ?? 0])); ?>">
                    <span class="stat-number"><?php p($_['statistics']['active_users_today'] ?? 0); ?></span>
                    <span class="stat-label"><?php p($l->t('Active today')); ?></span>
                </button>
                <a class="stat-card stat-card--link" data-stat="unresolved_violations" data-drilldown-filter="violations" data-href="<?php p($violationsUrl); ?>"
                     href="<?php p($violationsUrl); ?>"
                     title="<?php p($l->t('Number of open working-time compliance violations')); ?>"
                     aria-label="<?php p($l->t('Unresolved violations: %s. Open compliance violations.', [$_['statistics']['unresolved_violations'] ?? 0])); ?>">
                    <span class="stat-number"><?php p($_['statistics']['unresolved_violations'] ?? 0); ?></span>
                    <span class="stat-label"><?php p($l->t('Open issues')); ?></span>
                </a>
            </div>

            <!-- Recent Violations -->
            <div class="section admin-dashboard-problems">
                <div class="section-header">
                    <h2><?php p($l->t('Current issues')); ?></h2>
                    <p><?php p($l->t('Working-time compliance violations that require your attention')); ?></p>
                </div>

                <?php if (empty($_['recent_violations'])): ?>
                    <div class="empty-state">
                        <h3 class="empty-state__title"><?php p($l->t('No problems found')); ?></h3>
                        <p class="empty-state__description">
                            <?php p($l->t('Great! All employees are following the working time rules correctly.')); ?>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive" role="region" aria-label="<?php p($l->t('Recent compliance violations')); ?>">
                        <table class="table" role="table" aria-label="<?php p($l->t('Recent compliance violations')); ?>">
                            <thead>
                                <tr>
                                    <th scope="col"><?php p($l->t('Employee')); ?></th>
                                    <th scope="col"><?php p($l->t('Problem Type')); ?></th>
                                    <th scope="col"><?php p($l->t('How Serious')); ?></th>
                                    <th scope="col"><?php p($l->t('Date')); ?></th>
                                    <th scope="col"><?php p($l->t('Fixed?')); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (($_['recent_violations'] ?? []) as $violation): ?>
                                    <?php
                                    $typeKey = $violation['type'] ?? '';
                                    $typeLabel = match ($typeKey) {
                                        'missing_break' => $l->t('Missing break'),
                                        'excessive_working_hours' => $l->t('Excessive working hours'),
                                        'insufficient_rest_period' => $l->t('Insufficient rest period'),
                                        'daily_hours_limit_exceeded' => $l->t('Daily hours limit exceeded'),
                                        'weekly_hours_limit_exceeded' => $l->t('Weekly hours limit exceeded'),
                                        'night_work' => $l->t('Night work'),
                                        'sunday_work' => $l->t('Sunday work'),
                                        'holiday_work' => $l->t('Holiday work'),
                                        default => $typeKey,
                                    };
                                    $severityKey = $violation['severity'] ?? '';
                                    $severityLabel = match ($severityKey) {
                                        'error' => $l->t('High'),
                                        'warning' => $l->t('Medium'),
                                        'info' => $l->t('Low'),
                                        default => $severityKey,
                                    };
                                    $severityBadge = match ($severityKey) {
                                        'error' => 'error',
                                        'warning' => 'warning',
                                        default => 'primary',
                                    };
                                    ?>
                                    <tr>
                                        <td><?php p($violation['userDisplayName'] ?? $violation['userId']); ?></td>
                                        <td><?php p($typeLabel); ?></td>
                                        <td>
                                            <span class="badge badge--<?php p($severityBadge); ?>">
                                                <?php p($severityLabel); ?>
                                            </span>
                                        </td>
                                        <td><?php p($violation['date'] ?? '-'); ?></td>
                                        <td>
                                            <?php if ($violation['resolved']): ?>
                                                <span class="badge badge--success"><?php p($l->t('Resolved')); ?></span>
                                            <?php else: ?>
                                                <span class="badge badge--error"><?php p($l->t('Unresolved')); ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>
</div><!-- /#arbeitszeitcheck-app -->

<?php
$adminDashboardL10n = [
	'activeTodayDrilldownTitle' => $l->t('Active today'),
	'totalEmployeesDrilldownTitle' => $l->t('All employees'),
	'drilldownHelp' => $l->t('Search by name or user ID. Export downloads the full filtered list.'),
	'Search employees' => $l->t('Search employees'),
	'Search employees…' => $l->t('Search employees…'),
	'Export CSV' => $l->t('Export CSV'),
	'Loading…' => $l->t('Loading…'),
	'Name' => $l->t('Name'),
	'User ID' => $l->t('User ID'),
	'Active today' => $l->t('Active today'),
	'Overtime tracking set' => $l->t('Overtime tracking set'),
	'No employees found.' => $l->t('No employees found.'),
	'Yes' => $l->t('Yes'),
	'No' => $l->t('No'),
	'drilldownCount' => $l->t('{count} employees'),
	'drilldownLoadError' => $l->t('Could not load employee list.'),
	'drilldownTruncatedNotice' => $l->t('Showing the first results. Use search to narrow down the list.'),
	'statisticsRefreshError' => $l->t('Could not refresh statistics.'),
];
?>
<script nonce="<?php p($_['cspNonce'] ?? ''); ?>">
window.ArbeitszeitCheck = window.ArbeitszeitCheck || {};
window.ArbeitszeitCheck.l10n = window.ArbeitszeitCheck.l10n || {};
Object.assign(window.ArbeitszeitCheck.l10n, <?php echo json_encode($adminDashboardL10n, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>);
</script>
