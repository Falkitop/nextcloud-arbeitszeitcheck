<?php

declare(strict_types=1);

/**
 * Substitution requests (Vertretungs-Freigabe) template
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

/** @var array $_ */
/** @var \OCP\IL10N $l */
$l = $_['l'] ?? \OCP\Util::getL10N('arbeitszeitcheck');
$requests = $_['requests'] ?? [];
$error = $_['error'] ?? null;
$urlGenerator = $_['urlGenerator'] ?? \OCP\Server::get(\OCP\IURLGenerator::class);
$apiListUrl = $urlGenerator->linkToRoute('arbeitszeitcheck.substitute.getPending');
$apiApproveUrl = $urlGenerator->linkToRoute('arbeitszeitcheck.substitute.approve', ['absenceId' => '__ID__']);
$apiDeclineUrl = $urlGenerator->linkToRoute('arbeitszeitcheck.substitute.decline', ['absenceId' => '__ID__']);
?>

<?php include __DIR__ . '/common/page-start.php'; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert--error" role="alert">
                <p><?php p($error); ?></p>
            </div>
        <?php endif; ?>

        <section class="section azc-card substitution-requests__list" id="substitution-requests-section" aria-labelledby="requests-heading">
            <h2 id="requests-heading" class="azc-card__title"><?php p($l->t('Pending substitution requests')); ?></h2>
            <p class="azc-card__lead"><?php p($l->t('You have been asked to cover for colleagues during their absence. Approve or decline each request.')); ?></p>

            <div id="substitution-requests-content" class="substitution-requests-content" role="region" aria-live="polite">
                <p id="substitution-requests-loading" class="substitution-requests-loading" role="status" aria-live="polite" aria-busy="true"><?php p($l->t('Loading…')); ?></p>
                <div id="substitution-requests-items" class="substitution-requests-items" aria-hidden="true"></div>
                <div id="substitution-requests-empty" class="substitution-requests-empty azc-empty-state visually-hidden" role="status">
                    <p class="azc-empty-state__title"><?php p($l->t('No substitution requests')); ?></p>
                    <p class="substitution-requests-empty__hint"><?php p($l->t('When a colleague requests an absence and selects you as their substitute, you will see the request here.')); ?></p>
                </div>
            </div>
        </section>

<script nonce="<?php p($_['cspNonce'] ?? ''); ?>">
window.ArbeitszeitCheck = window.ArbeitszeitCheck || {};
window.ArbeitszeitCheck.substitutionApi = {
    list: <?php echo json_encode($apiListUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
    approve: <?php echo json_encode($apiApproveUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
    decline: <?php echo json_encode($apiDeclineUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
};
</script>

<?php include __DIR__ . '/common/page-end.php'; ?>
