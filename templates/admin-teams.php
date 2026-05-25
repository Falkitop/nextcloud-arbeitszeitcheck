<?php

declare(strict_types=1);

/**
 * Admin teams template: app-owned teams/departments with members and managers.
 * Clear sections, WCAG 2.1 AA, responsive.
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

/** @var array $_ */
/** @var \OCP\IL10N $l */
$l = $_['l'] ?? \OCP\Util::getL10N('arbeitszeitcheck');
$urlGenerator = $_['urlGenerator'] ?? \OCP\Server::get(\OCP\IURLGenerator::class);
?>

<?php include __DIR__ . '/common/page-start.php'; ?>


        <div class="azc-page-stack">

        <?php
        $useAppTeams = (bool)($_['useAppTeams'] ?? false);
        $adminTeamsUrl = (string)($_['adminTeamsUrl'] ?? '');
        if (!$useAppTeams):
        ?>
        <div class="azc-callout azc-callout--warning" role="status" aria-labelledby="teams-enable-app-teams-title">
            <p id="teams-enable-app-teams-title" class="azc-callout__title"><?php p($l->t('App teams are disabled')); ?></p>
            <p class="azc-callout__text">
                <?php p($l->t('Manager approvals, the manager dashboard, and team-scoped reports require ArbeitszeitCheck teams. Enable the option below and assign at least one manager per unit.')); ?>
            </p>
        </div>
        <?php endif; ?>

        <div class="admin-teams">
        <section class="azc-card section--config" aria-labelledby="teams-config-heading">
            <h2 id="teams-config-heading" class="section__heading"><?php p($l->t('Manager assignment')); ?></h2>
            <div class="config-card">
                <div class="form-checkbox form-checkbox--switch">
                    <input type="checkbox" id="use-app-teams" name="useAppTeams" class="config-toggle"
                           aria-describedby="use-app-teams-desc"
                           aria-label="<?php p($l->t('Use ArbeitszeitCheck teams instead of Nextcloud groups')); ?>">
                    <label for="use-app-teams" class="form-label">
                        <?php p($l->t('Use ArbeitszeitCheck teams for approvals')); ?>
                    </label>
                </div>
                <p id="use-app-teams-desc" class="form-help">
                    <?php p($l->t('If enabled: managers are resolved from ArbeitszeitCheck teams defined below. If disabled: managers are resolved from shared Nextcloud groups (default behavior).')); ?>
                </p>
            </div>
        </section>

        <!-- Teams list -->
        <section class="section section--teams-list" aria-labelledby="teams-list-heading">
            <div class="section__header flex flex--between flex--wrap flex--gap">
                <h2 id="teams-list-heading" class="section__heading"><?php p($l->t('Structure')); ?></h2>
                <button type="button" id="admin-teams-add" class="azc-btn azc-btn--primary"
                        aria-label="<?php p($l->t('Add new organizational unit')); ?>">
                    <?php p($l->t('Add unit')); ?>
                </button>
            </div>
            <div id="admin-teams-tree" class="teams-tree org-tree" role="tree" aria-label="<?php p($l->t('Organization structure')); ?>">
                <p id="teams-loading" class="teams-loading" aria-live="polite"><?php p($l->t('Loading…')); ?></p>
                <p id="teams-empty" class="teams-empty hidden" aria-live="polite"><?php p($l->t('No units yet. Add a unit to build your organization structure.')); ?></p>
            </div>
        </section>

        <!-- Selected unit: members & managers -->
        <section id="admin-team-detail" class="section section--team-detail hidden" aria-labelledby="team-detail-heading">
            <h2 id="team-detail-heading" class="section__heading">
                <span id="team-detail-name"></span>
            </h2>
            <div class="team-detail-tabs" role="tablist" aria-label="<?php p($l->t('Team members and managers')); ?>">
                <button type="button" id="tab-members" role="tab" aria-selected="true" aria-controls="panel-members">
                    <?php p($l->t('Members')); ?>
                </button>
                <button type="button" id="tab-managers" role="tab" aria-selected="false" aria-controls="panel-managers">
                    <?php p($l->t('Managers')); ?>
                </button>
            </div>
            <div id="panel-members" role="tabpanel" aria-labelledby="tab-members" class="team-panel">
                <div class="panel-actions">
                    <button type="button" id="team-add-member" class="btn btn--secondary" aria-label="<?php p($l->t('Add member to team')); ?>">
                        <?php p($l->t('Add member')); ?>
                    </button>
                </div>
                <ul id="team-members-list" class="team-list" aria-label="<?php p($l->t('Team members')); ?>"></ul>
            </div>
            <div id="panel-managers" role="tabpanel" aria-labelledby="tab-managers" class="team-panel hidden">
                <div class="panel-actions">
                    <button type="button" id="team-add-manager" class="btn btn--secondary" aria-label="<?php p($l->t('Add manager to team')); ?>">
                        <?php p($l->t('Add manager')); ?>
                    </button>
                </div>
                <ul id="team-managers-list" class="team-list" aria-label="<?php p($l->t('Team managers')); ?>"></ul>
            </div>
        </section>
    </div>
<div role="status" aria-live="polite" id="admin-teams-status" class="visually-hidden"></div>

<?php include __DIR__ . '/common/teams-l10n.php'; ?>

</div><!-- /.azc-page-stack -->
<?php include __DIR__ . '/common/page-end.php'; ?>
