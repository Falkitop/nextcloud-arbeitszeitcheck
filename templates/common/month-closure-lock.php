<?php
declare(strict_types=1);

/**
 * Reusable notice when a calendar month is finalized (immutable).
 *
 * @var array $_  message (optional string), id (optional string for aria)
 * @var \OCP\IL10N $l
 */

use OCA\ArbeitszeitCheck\Service\IconCatalog;

$message = (string)($_['message'] ?? $l->t('This calendar month is finalized. Contact an administrator if a correction must be made.'));
$noticeId = (string)($_['id'] ?? 'month-closure-lock-notice');
?>
<div class="azc-callout azc-callout--info azc-month-closure-lock" id="<?php p($noticeId); ?>" role="status">
	<span class="azc-callout__icon azc-notif-icon-well" aria-hidden="true"><?php print_unescaped(IconCatalog::render('lock', 'azc-callout__icon-svg')); ?></span>
	<p class="azc-callout__text"><?php p($message); ?></p>
</div>
