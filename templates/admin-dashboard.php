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

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card" 
                     title="<?php p($l->t('Total number of employees with access to time tracking')); ?>"
                     aria-label="<?php p($l->t('Total employees: %s', [$_['statistics']['total_users'] ?? 0])); ?>">
                    <div class="stat-number"><?php p($_['statistics']['total_users'] ?? 0); ?></div>
                    <div class="stat-label"><?php p($l->t('Total employees')); ?></div>
                </div>
                <div class="stat-card"
                     title="<?php p($l->t('Number of employees with bookings today')); ?>"
                     aria-label="<?php p($l->t('Employees active today: %s', [$_['statistics']['active_users_today'] ?? 0])); ?>">
                    <div class="stat-number"><?php p($_['statistics']['active_users_today'] ?? 0); ?></div>
                    <div class="stat-label"><?php p($l->t('Active today')); ?></div>
                </div>
                <div class="stat-card"
                     title="<?php p($l->t('Number of open working-time compliance violations')); ?>"
                     aria-label="<?php p($l->t('Unresolved violations: %s', [$_['statistics']['unresolved_violations'] ?? 0])); ?>">
                    <div class="stat-number"><?php p($_['statistics']['unresolved_violations'] ?? 0); ?></div>
                    <div class="stat-label"><?php p($l->t('Open issues')); ?></div>
                </div>
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
