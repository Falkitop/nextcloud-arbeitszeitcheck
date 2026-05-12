<?php

declare(strict_types=1);

/**
 * Admin · Vacation entitlement layers (L0 / L1 / L2 / L3 + simulator).
 *
 * UX goals:
 *  - Clear, scannable layout that walks an admin through the precedence
 *    chain visually (L3 highest, L0 lowest).
 *  - Each layer is a self-contained card with an inline "Add / edit"
 *    drawer and a live "What this will do" preview via the simulator.
 *  - Empty states explicitly tell the admin "the default fallback (25 d.)
 *    is used until you configure this layer".
 *  - WCAG 2.1 AA: semantic landmarks, labelled form fields, role=tab[panel],
 *    aria-live status, sufficient contrast (via CSS variables).
 *  - Responsive: cards stack <= 920px; layer chips wrap; simulator full-width
 *    on mobile.
 *
 * @copyright Copyright (c) 2026 Alexander Mäule
 * @license AGPL-3.0-or-later
 */

/** @var array<string, mixed> $_ */
/** @var \OCP\IL10N $l */
$l = $_['l'] ?? \OCP\Util::getL10N('arbeitszeitcheck');
$urlGenerator = $_['urlGenerator'] ?? \OCP\Server::get(\OCP\IURLGenerator::class);
$layeredEnabled = (bool)($_['layeredEnabled'] ?? true);
?>

<?php include __DIR__ . '/common/navigation.php'; ?>

<main id="app-content" role="main" aria-labelledby="azc-vacation-layers-title">
    <div id="app-content-wrapper" class="admin-vacation-layers">

        <header class="vacation-layers__header" role="banner">
            <h1 id="azc-vacation-layers-title" class="vacation-layers__title">
                <?php p($l->t('Vacation entitlement layers')); ?>
            </h1>
            <p class="vacation-layers__lede">
                <?php p($l->t('Define how many vacation days an employee is entitled to. Rules are evaluated top-down: a higher layer always wins. This page lets you see, edit, and simulate the full precedence chain.')); ?>
            </p>
            <div class="vacation-layers__feature-flag" role="status" aria-live="polite">
                <?php if ($layeredEnabled): ?>
                    <span class="badge badge--ok" aria-label="<?php p($l->t('Layered resolution is currently active')); ?>"><?php p($l->t('Layered resolution active')); ?></span>
                <?php else: ?>
                    <span class="badge badge--warn" aria-label="<?php p($l->t('Layered resolution is disabled — only individual L3 rules and the legacy fallback are evaluated')); ?>"><?php p($l->t('Layered resolution disabled — only L3 + legacy fallback active')); ?></span>
                <?php endif; ?>
            </div>
        </header>

        <!-- Stepper / precedence overview -->
        <nav class="vacation-layers__stepper" aria-label="<?php p($l->t('Layer precedence overview')); ?>">
            <ol class="stepper">
                <li class="stepper__item stepper__item--l3"><span class="stepper__rank">1</span><span class="stepper__label"><?php p($l->t('L3 · Individual')); ?></span></li>
                <li class="stepper__item stepper__item--l2"><span class="stepper__rank">2</span><span class="stepper__label"><?php p($l->t('L2 · Team / Cohort')); ?></span></li>
                <li class="stepper__item stepper__item--l1"><span class="stepper__rank">3</span><span class="stepper__label"><?php p($l->t('L1 · Working time model')); ?></span></li>
                <li class="stepper__item stepper__item--l0"><span class="stepper__rank">4</span><span class="stepper__label"><?php p($l->t('L0 · Organisation default')); ?></span></li>
                <li class="stepper__item stepper__item--legacy"><span class="stepper__rank">5</span><span class="stepper__label"><?php p($l->t('Legacy fallback (25 d.)')); ?></span></li>
            </ol>
        </nav>

        <!-- L0 — Organisation -->
        <section class="layer-card layer-card--l0" id="layer-l0" aria-labelledby="layer-l0-heading">
            <header class="layer-card__header">
                <div>
                    <h2 id="layer-l0-heading" class="layer-card__title">
                        <span class="layer-card__chip">L0</span>
                        <?php p($l->t('Organisation default')); ?>
                    </h2>
                    <p class="layer-card__desc">
                        <?php p($l->t('Used when no team policy, model default, or individual rule applies. This is the safety net for new employees.')); ?>
                    </p>
                </div>
                <button type="button" class="btn btn--primary" data-action="add-org"
                        aria-label="<?php p($l->t('Add or supersede the organisation default')); ?>">
                    <?php p($l->t('Add organisation default')); ?>
                </button>
            </header>
            <div class="layer-card__body">
                <div id="l0-active" class="layer-card__active" aria-live="polite">
                    <p class="layer-card__placeholder"><?php p($l->t('Loading…')); ?></p>
                </div>
                <details class="layer-card__history">
                    <summary><?php p($l->t('Show full history')); ?></summary>
                    <table class="layer-card__history-table" aria-label="<?php p($l->t('Organisation default history')); ?>">
                        <thead>
                            <tr>
                                <th scope="col"><?php p($l->t('Effective')); ?></th>
                                <th scope="col"><?php p($l->t('Mode')); ?></th>
                                <th scope="col"><?php p($l->t('Days')); ?></th>
                                <th scope="col"><?php p($l->t('Tariff rule set')); ?></th>
                                <th scope="col"><?php p($l->t('Description')); ?></th>
                                <th scope="col" class="layer-card__history-actions"><?php p($l->t('Actions')); ?></th>
                            </tr>
                        </thead>
                        <tbody id="l0-history-rows">
                            <tr><td colspan="6" class="layer-card__placeholder"><?php p($l->t('Loading…')); ?></td></tr>
                        </tbody>
                    </table>
                </details>
            </div>
        </section>

        <!-- L1 — Working-time-model defaults -->
        <section class="layer-card layer-card--l1" id="layer-l1" aria-labelledby="layer-l1-heading">
            <header class="layer-card__header">
                <div>
                    <h2 id="layer-l1-heading" class="layer-card__title">
                        <span class="layer-card__chip">L1</span>
                        <?php p($l->t('Working time model defaults')); ?>
                    </h2>
                    <p class="layer-card__desc">
                        <?php p($l->t('Defaults attached to a working time model (e.g. “30 hours / week”). Apply automatically to every employee with that model who has no individual or team rule.')); ?>
                    </p>
                </div>
                <button type="button" class="btn btn--primary" data-action="add-model"
                        aria-label="<?php p($l->t('Add a default for a working time model')); ?>">
                    <?php p($l->t('Add model default')); ?>
                </button>
            </header>
            <div class="layer-card__body">
                <table class="layer-card__history-table" aria-label="<?php p($l->t('Working time model defaults')); ?>">
                    <thead>
                        <tr>
                            <th scope="col"><?php p($l->t('Model')); ?></th>
                            <th scope="col"><?php p($l->t('Effective')); ?></th>
                            <th scope="col"><?php p($l->t('Mode')); ?></th>
                            <th scope="col"><?php p($l->t('Days')); ?></th>
                            <th scope="col"><?php p($l->t('Tariff rule set')); ?></th>
                            <th scope="col"><?php p($l->t('Description')); ?></th>
                            <th scope="col" class="layer-card__history-actions"><?php p($l->t('Actions')); ?></th>
                        </tr>
                    </thead>
                    <tbody id="l1-rows">
                        <tr><td colspan="7" class="layer-card__placeholder"><?php p($l->t('Loading…')); ?></td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- L2 — Team policies -->
        <section class="layer-card layer-card--l2" id="layer-l2" aria-labelledby="layer-l2-heading">
            <header class="layer-card__header">
                <div>
                    <h2 id="layer-l2-heading" class="layer-card__title">
                        <span class="layer-card__chip">L2</span>
                        <?php p($l->t('Team / cohort policies')); ?>
                    </h2>
                    <p class="layer-card__desc">
                        <?php p($l->t('Policies attached to a team. When an employee belongs to several teams, the policy attached to the deepest team in the hierarchy wins; ties are broken by the higher priority, then by the smallest team ID.')); ?>
                    </p>
                </div>
                <button type="button" class="btn btn--primary" data-action="add-team"
                        aria-label="<?php p($l->t('Add a team vacation policy')); ?>">
                    <?php p($l->t('Add team policy')); ?>
                </button>
            </header>
            <div class="layer-card__body">
                <table class="layer-card__history-table" aria-label="<?php p($l->t('Team vacation policies')); ?>">
                    <thead>
                        <tr>
                            <th scope="col"><?php p($l->t('Team')); ?></th>
                            <th scope="col"><?php p($l->t('Effective')); ?></th>
                            <th scope="col"><?php p($l->t('Mode')); ?></th>
                            <th scope="col"><?php p($l->t('Days')); ?></th>
                            <th scope="col"><?php p($l->t('Priority')); ?></th>
                            <th scope="col"><?php p($l->t('Description')); ?></th>
                            <th scope="col" class="layer-card__history-actions"><?php p($l->t('Actions')); ?></th>
                        </tr>
                    </thead>
                    <tbody id="l2-rows">
                        <tr><td colspan="7" class="layer-card__placeholder"><?php p($l->t('Loading…')); ?></td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Simulator -->
        <section class="layer-card layer-card--sim" id="layer-sim" aria-labelledby="sim-heading">
            <header class="layer-card__header">
                <div>
                    <h2 id="sim-heading" class="layer-card__title">
                        <i data-lucide="flask-conical" class="lucide-icon" aria-hidden="true"></i>
                        <?php p($l->t('Simulator')); ?>
                    </h2>
                    <p class="layer-card__desc">
                        <?php p($l->t('Try out: how many vacation days does an employee actually get on a given date? See exactly which layer was applied and why.')); ?>
                    </p>
                </div>
            </header>
            <div class="layer-card__body">
                <form id="sim-form" class="layer-form" novalidate>
                    <div class="layer-form__row">
                        <label for="sim-user" class="form-label">
                            <?php p($l->t('Employee')); ?>
                            <span class="visually-hidden"> (<?php p($l->t('required')); ?>)</span>
                        </label>
                        <input type="text" id="sim-user" name="userId" class="form-input"
                               placeholder="<?php p($l->t('Type to search for an employee')); ?>"
                               autocomplete="off"
                               aria-describedby="sim-user-help" required>
                        <p id="sim-user-help" class="form-help"><?php p($l->t('Start typing the user name or login — suggestions will appear.')); ?></p>
                        <ul id="sim-user-suggest" class="form-suggest" role="listbox" hidden></ul>
                    </div>
                    <div class="layer-form__row">
                        <label for="sim-date" class="form-label"><?php p($l->t('As-of date')); ?></label>
                        <input type="date" id="sim-date" name="asOfDate" class="form-input"
                               value="<?php p(date('Y-m-d')); ?>">
                    </div>
                    <div class="layer-form__row">
                        <button type="submit" class="btn btn--primary">
                            <?php p($l->t('Run simulation')); ?>
                        </button>
                    </div>
                </form>
                <div id="sim-result" class="layer-sim__result" role="region"
                     aria-live="polite" aria-labelledby="sim-heading"></div>
            </div>
        </section>

    </div>
</main>
</div><!-- /#arbeitszeitcheck-app -->

<!-- Hidden form drawers -->
<dialog id="layer-dialog" class="layer-dialog" aria-labelledby="layer-dialog-title">
    <form id="layer-dialog-form" class="layer-dialog__form" method="dialog" novalidate>
        <h2 id="layer-dialog-title" class="layer-dialog__title"><?php p($l->t('Edit layer')); ?></h2>
        <p id="layer-dialog-intro" class="layer-dialog__intro"></p>
        <div id="layer-dialog-body" class="layer-dialog__body"></div>
        <p id="layer-dialog-feedback" class="layer-dialog__feedback" role="alert" aria-live="assertive"></p>
        <div class="layer-dialog__actions">
            <button type="button" id="layer-dialog-cancel" class="btn btn--secondary">
                <?php p($l->t('Cancel')); ?>
            </button>
            <button type="submit" id="layer-dialog-save" class="btn btn--primary">
                <?php p($l->t('Save')); ?>
            </button>
        </div>
    </form>
</dialog>

<div role="status" aria-live="polite" id="vacation-layers-status" class="visually-hidden"></div>

<script id="vacation-layers-bootstrap" type="application/json">
<?php
echo json_encode([
    'urls' => [
        'overview' => $urlGenerator->linkToRoute('arbeitszeitcheck.admin.getVacationLayers'),
        'org' => $urlGenerator->linkToRoute('arbeitszeitcheck.admin.saveOrgVacationDefault'),
        'orgDelete' => $urlGenerator->linkToRoute('arbeitszeitcheck.admin.deleteOrgVacationDefault', ['id' => 0]),
        'model' => $urlGenerator->linkToRoute('arbeitszeitcheck.admin.saveModelVacationDefault'),
        'modelDelete' => $urlGenerator->linkToRoute('arbeitszeitcheck.admin.deleteModelVacationDefault', ['id' => 0]),
        'team' => $urlGenerator->linkToRoute('arbeitszeitcheck.admin.saveTeamVacationPolicy'),
        'teamDelete' => $urlGenerator->linkToRoute('arbeitszeitcheck.admin.deleteTeamVacationPolicy', ['id' => 0]),
        'simulate' => $urlGenerator->linkToRoute('arbeitszeitcheck.admin.simulateVacationPolicy'),
        'userSearch' => $urlGenerator->linkToRoute('arbeitszeitcheck.admin.searchVacationLayersUsers'),
    ],
    'layeredEnabled' => $layeredEnabled,
], JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
</script>
