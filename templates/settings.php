<?php
declare(strict_types=1);

/**
 * Settings template for arbeitszeitcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

use OCP\Util;

/** @var array $_ */
/** @var \OCP\IL10N $l */

// Assets registered by PageController::registerFrontEndAssets

$urlGenerator = $_['urlGenerator'] ?? \OCP\Server::get(\OCP\IURLGenerator::class);
$urls = $_['urls'] ?? [];
?>

<?php include __DIR__ . '/common/page-start.php'; ?>

        <div class="azc-page-stack settings-container" aria-label="<?php p($l->t('Settings options')); ?>">
                <!-- Working Time Preferences -->
                <div class="settings-section azc-card">
                    <h3 id="settings-sections-heading" class="section-title azc-card__title"><?php p($l->t('Working Time Preferences')); ?></h3>
                    <form id="working-time-settings-form" class="form">
                        <div class="form-group">
                            <div class="form-checkbox">
                                <input type="checkbox" 
                                       id="auto-break-calculation" 
                                       name="auto_break_calculation"
                                       checked
                                       aria-describedby="auto-break-calculation-help">
                                <label for="auto-break-calculation" class="form-label">
                                    <?php p($l->t('Calculate breaks automatically')); ?>
                                </label>
                            </div>
                            <p id="auto-break-calculation-help" class="form-help">
                                <?php p($l->t('The system will automatically calculate when you need to take breaks according to German labor law. For example, if you work more than 6 hours, you must take at least a 30-minute break.')); ?>
                            </p>
                        </div>

                        <div class="card-actions">
                            <button type="submit" 
                                    class="azc-btn azc-btn--primary"
                                    aria-label="<?php p($l->t('Save your preferences')); ?>"
                                    title="<?php p($l->t('Click to save your preferences')); ?>">
                                <?php p($l->t('Save Settings')); ?>
                            </button>
                            <a href="<?php p($urlGenerator->linkToRoute('arbeitszeitcheck.page.index')); ?>"
                               class="azc-btn azc-btn--secondary"
                               aria-label="<?php p($l->t('Cancel and go back to dashboard')); ?>"
                               title="<?php p($l->t('Click to cancel and go back without saving changes')); ?>">
                                <?php p($l->t('Cancel')); ?>
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Notifications -->
                <div class="settings-section azc-card">
                    <h3 class="section-title azc-card__title"><?php p($l->t('Notifications')); ?></h3>
                    <form id="notification-settings-form" class="form">
                        <div class="form-group">
                            <div class="form-checkbox">
                                <input type="checkbox" 
                                       id="notifications-enabled" 
                                       name="notifications_enabled"
                                       checked>
                                <label for="notifications-enabled" class="form-label">
                                    <?php p($l->t('Enable Notifications')); ?>
                                </label>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="form-checkbox">
                                <input type="checkbox" 
                                       id="break-reminders" 
                                       name="break_reminders_enabled"
                                       checked
                                       aria-describedby="break-reminders-help">
                                <label for="break-reminders" class="form-label">
                                    <?php p($l->t('Remind me to take breaks')); ?>
                                </label>
                            </div>
                            <p id="break-reminders-help" class="form-help">
                                <?php p($l->t('Get a notification when it\'s time to take a required break. For example, if you work more than 6 hours, you\'ll get a reminder to take at least a 30-minute break.')); ?>
                            </p>
                        </div>

                        <div class="form-group">
                            <div class="form-checkbox">
                                <input type="checkbox"
                                       id="missing-clock-in-reminders"
                                       name="missing_clock_in_reminders_enabled"
                                       checked
                                       aria-describedby="missing-clock-in-reminders-help">
                                <label for="missing-clock-in-reminders" class="form-label">
                                    <?php p($l->t('Remind me when I forgot to clock in (for expected workdays)')); ?>
                                </label>
                            </div>
                            <p id="missing-clock-in-reminders-help" class="form-help">
                                <?php p($l->t('You receive this reminder only on regular working days. No reminder is sent on weekends, holidays, or approved absences.')); ?>
                            </p>
                        </div>

                        <div class="card-actions">
                            <button type="submit" 
                                    class="azc-btn azc-btn--primary"
                                    aria-label="<?php p($l->t('Save your working time settings')); ?>"
                                    title="<?php p($l->t('Click to save your working time preferences')); ?>">
                                <?php p($l->t('Save Settings')); ?>
                            </button>
                            <a href="<?php p($urlGenerator->linkToRoute('arbeitszeitcheck.page.index')); ?>"
                               class="azc-btn azc-btn--secondary"
                               aria-label="<?php p($l->t('Cancel and go back to dashboard')); ?>"
                               title="<?php p($l->t('Click to cancel and go back without saving changes')); ?>">
                                <?php p($l->t('Cancel')); ?>
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Working Time Model -->
                <div class="settings-section azc-card">
                    <h3 class="section-title azc-card__title"><?php p($l->t('Working Time Model')); ?></h3>
                    <div id="working-time-model-info" class="info-box">
                        <p><?php p($l->t('Your working time model, vacation days, and working hours are assigned by your administrator. Contact your administrator if you have questions or need changes.')); ?></p>
                    </div>
                </div>

                <!-- Compliance Information -->
                <div class="settings-section azc-card">
                    <h3 class="section-title azc-card__title"><?php p($l->t('Compliance Information')); ?></h3>
                    <div class="info-box">
                        <h4><?php p($l->t('German Labor Law (Arbeitszeitgesetz - ArbZG)')); ?></h4>
                        <ul>
                            <li><?php p($l->t('Maximum working time: 8 hours per day (can be extended to 10 hours)')); ?></li>
                            <li><?php p($l->t('Minimum rest period: 11 hours between working days')); ?></li>
                            <li><?php p($l->t('Mandatory breaks: 30 min after 6 hours, 45 min after 9 hours')); ?></li>
                            <li><?php p($l->t('Sunday work is generally prohibited with exceptions')); ?></li>
                        </ul>
                    </div>
                </div>

                <!-- Data and privacy -->
                <div class="settings-section azc-card" id="settings-data-privacy">
                    <h3 class="section-title azc-card__title"><?php p($l->t('Data and privacy')); ?></h3>
                    <p><?php p($l->t('Export or permanently delete your personal ArbeitszeitCheck data in accordance with GDPR.')); ?></p>
                    <div class="card-actions">
                        <a href="<?php print_unescaped((string)($urls['gdprExport'] ?? $urlGenerator->linkToRoute('arbeitszeitcheck.gdpr.export'))); ?>"
                           class="azc-btn azc-btn--secondary"
                           download>
                            <?php p($l->t('Export My Data')); ?>
                        </a>
                        <button type="button"
                                id="btn-gdpr-delete"
                                class="azc-btn azc-btn--danger"
                                data-delete-url="<?php p((string)($urls['gdprDelete'] ?? $urlGenerator->linkToRoute('arbeitszeitcheck.gdpr.delete'))); ?>">
                            <?php p($l->t('Delete my ArbeitszeitCheck data')); ?>
                        </button>
                    </div>
                    <p class="form-help" id="gdpr-delete-help">
                        <?php p($l->t('Deleting your data permanently removes time entries, absences, and settings stored by this app. This cannot be undone.')); ?>
                    </p>
                </div>

                <!-- Version Information -->
                <div class="settings-section azc-card">
                    <h3 class="section-title azc-card__title"><?php p($l->t('Version Information')); ?></h3>
                    <div class="info-box">
                        <p>
                            <strong><?php p($l->t('ArbeitszeitCheck')); ?></strong>
                            <?php p($l->t('Version:')); ?> <?php p(\OCP\Server::get(\OCP\App\IAppManager::class)->getAppVersion('arbeitszeitcheck')); ?>
                        </p>
                        <p><?php p($l->t('German labor law compliant time tracking for Nextcloud')); ?></p>
                    </div>
                </div>
            </div>
        </div><!-- /.azc-page-stack -->

<script nonce="<?php p($_['cspNonce'] ?? ''); ?>">
    window.ArbeitszeitCheck = window.ArbeitszeitCheck || {};
    window.ArbeitszeitCheck.page = 'settings';
    
    window.ArbeitszeitCheck.l10n = window.ArbeitszeitCheck.l10n || {};
    window.ArbeitszeitCheck.l10n.settingsSaved = <?php echo json_encode($l->t('Settings saved successfully'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.error = <?php echo json_encode($l->t('An error occurred'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.saving = <?php echo json_encode($l->t('Saving...'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.failedToSaveSettings = <?php echo json_encode($l->t('Failed to save settings'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    
    window.ArbeitszeitCheck.apiUrl = {
        updateSettings: <?php echo json_encode($urlGenerator->linkToRoute('arbeitszeitcheck.settings.update'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        gdprDelete: <?php echo json_encode((string)($urls['gdprDelete'] ?? $urlGenerator->linkToRoute('arbeitszeitcheck.gdpr.delete')), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
    };
</script>
<?php include __DIR__ . '/common/page-end.php'; ?>
