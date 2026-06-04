<?php

declare(strict_types=1);

/**
 * Shown on admin pages when the database schema is incomplete after install/upgrade.
 *
 * @var array $_
 * @var \OCP\IL10N $l
 */

$schema = (array) ($_['schema_health'] ?? []);
if (empty($schema['show_banner'])) {
	return;
}

$missingCount = (int) ($schema['missing_count'] ?? 0);
$preview = (string) ($schema['missing_preview'] ?? '');

$calloutVariant = 'danger';
$calloutRole = 'alert';
$calloutId = 'azc-schema-upgrade-callout';
$calloutTitleId = 'azc-schema-upgrade-title';
$calloutTitle = $l->t('Database update required');
$calloutText = $l->n(
	'ArbeitszeitCheck cannot save data until the server finishes the database setup (%n missing table). '
	. 'Ask your Nextcloud administrator to run “Update apps” or `php occ upgrade`, then reload this page. '
	. 'Deploy the complete app package — copying single files leaves repair jobs broken.',
	'ArbeitszeitCheck cannot save data until the server finishes the database setup (%n missing tables). '
	. 'Ask your Nextcloud administrator to run “Update apps” or `php occ upgrade`, then reload this page. '
	. 'Deploy the complete app package — copying single files leaves repair jobs broken.',
	$missingCount,
);
if ($preview !== '') {
	$calloutText .= ' ' . $l->t('Missing: %s', [$preview]);
}
$calloutExtraClass = 'azc-schema-upgrade-callout';
$calloutActions = [];

include __DIR__ . '/alert-callout.php';
