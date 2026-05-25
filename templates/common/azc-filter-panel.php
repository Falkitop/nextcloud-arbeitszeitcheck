<?php
/**
 * Reusable filter panel chrome (wraps page-specific filter form markup).
 *
 * Expected $_ keys:
 * - filterPanelId: string (element id prefix, optional)
 * - filterTitle: string (h2 text)
 * - filterIntro: string (optional lead)
 * - filterAriaLabelledby: string (id for h2, default derived)
 *
 * @var array $_
 * @var \OCP\IL10N $l
 */
$filterPanelId = (string)($_['filterPanelId'] ?? 'azc-filter');
$filterTitle = (string)($_['filterTitle'] ?? $l->t('Filter'));
$filterIntro = (string)($_['filterIntro'] ?? '');
$headingId = (string)($_['filterAriaLabelledby'] ?? $filterPanelId . '-title');
?>
<section class="azc-card azc-filter-panel" aria-labelledby="<?php p($headingId); ?>">
	<header class="azc-filter-panel__head">
		<h2 id="<?php p($headingId); ?>"><?php p($filterTitle); ?></h2>
		<?php if ($filterIntro !== ''): ?>
			<p class="azc-filter-panel__intro"><?php p($filterIntro); ?></p>
		<?php endif; ?>
	</header>
	<div class="azc-filter-panel__body">
