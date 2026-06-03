<?php

declare(strict_types=1);

/**
 * Neutral callout when ProjectCheck is installed but the admin connection is off.
 *
 * Expects:
 *   - $l (\OCP\IL10N)
 *   - $azcProjectCheckCalloutContext (optional): 'dashboard' | 'time-entry' | 'edit'
 *
 * @var \OCP\IL10N $l
 */

use OCA\ArbeitszeitCheck\Service\IconCatalog;

$context = isset($azcProjectCheckCalloutContext) ? (string)$azcProjectCheckCalloutContext : 'dashboard';
$title = $l->t('ProjectCheck linking is turned off');
$body = $context === 'edit'
	? $l->t('An administrator turned off new project links. You can still see or remove an existing link on this entry, but you cannot pick a different project.')
	: $l->t('An administrator must enable the ProjectCheck connection in Global settings before you can link hours to a project.');
?>
<div class="azc-callout azc-callout--neutral azc-projectcheck-linking-off" role="status">
	<span class="azc-callout__icon azc-notif-icon-well" aria-hidden="true"><?php print_unescaped(IconCatalog::render('briefcase', 'azc-callout__icon-svg')); ?></span>
	<div class="azc-callout__body">
		<p class="azc-callout__title"><?php p($title); ?></p>
		<p class="azc-callout__text"><?php p($body); ?></p>
	</div>
</div>
