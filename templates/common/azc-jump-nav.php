<?php

declare(strict_types=1);

/**
 * In-page section jump navigation for long admin settings forms.
 *
 * Expects in scope:
 *   $l — IL10N
 *   $jumpNavAriaLabel — string (nav aria-label)
 *   $jumpNavItems — list<array{href: string, label: string}>
 *   $jumpNavLayout — optional: "sidebar" (default) or "bar" (horizontal strip above content)
 *
 * @copyright Copyright (c) 2026 Alexander Mäule
 * @license AGPL-3.0-or-later
 */

/** @var \OCP\IL10N $l */
/** @var string $jumpNavAriaLabel */
/** @var list<array{href: string, label: string}> $jumpNavItems */

if (!isset($jumpNavItems) || !is_array($jumpNavItems) || $jumpNavItems === []) {
	return;
}

$jumpNavLayout = isset($jumpNavLayout) ? (string)$jumpNavLayout : 'sidebar';
$jumpNavIsBar = $jumpNavLayout === 'bar';
$jumpNavClass = 'azc-jump-nav' . ($jumpNavIsBar ? ' azc-jump-nav--bar' : '');
?>
<nav class="<?php p($jumpNavClass); ?>" aria-label="<?php p($jumpNavAriaLabel); ?>">
	<?php if ($jumpNavIsBar): ?>
		<p class="azc-jump-nav__label" id="azc-jump-nav-label"><?php p($l->t('On this page')); ?></p>
	<?php else: ?>
		<h2 class="azc-jump-nav__title"><?php p($l->t('Quick navigation')); ?></h2>
	<?php endif; ?>
	<ol class="azc-jump-nav__list"<?php echo $jumpNavIsBar ? ' aria-labelledby="azc-jump-nav-label"' : ''; ?>>
		<?php foreach ($jumpNavItems as $item): ?>
			<?php
			$href = (string)($item['href'] ?? '');
			$label = (string)($item['label'] ?? '');
			if ($href === '' || $label === '') {
				continue;
			}
			?>
			<li class="azc-jump-nav__item">
				<a class="azc-jump-nav__link" href="<?php p($href); ?>"><?php p($label); ?></a>
			</li>
		<?php endforeach; ?>
	</ol>
</nav>
