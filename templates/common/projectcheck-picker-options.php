<?php

declare(strict_types=1);

/**
 * Shared <option>/<optgroup> markup for the ProjectCheck project pickers
 * (dashboard clock-in and manual time entry).
 *
 * Projects are grouped by customer so the list stays scannable, and every
 * option carries a human-readable label. This is the single place that turns
 * the project rows from {@see \OCA\ArbeitszeitCheck\Service\ProjectCheckIntegrationService::getAvailableProjects()}
 * into accessible select options, so both pickers behave identically.
 *
 * Inputs (set by the including template before `include`):
 *   - $azcPickerProjects  list<array{id,name,customerName,displayName,...}>
 *   - $azcPickerSelectedId string  Currently linked project id ('' when none)
 *
 * @var \OCP\IL10N $l
 * @copyright Copyright (c) 2026 Software by Design / Alexander Mäule
 * @license AGPL-3.0-or-later
 */

$projects = isset($azcPickerProjects) && is_array($azcPickerProjects) ? $azcPickerProjects : [];
$selectedId = isset($azcPickerSelectedId) ? (string)$azcPickerSelectedId : '';

/**
 * Resolve a non-empty, human-readable option label (never emit blank text).
 */
$azcResolveProjectOptionLabel = static function (array $pcProject, string $pid) use ($l): string {
	$name = trim((string)($pcProject['name'] ?? ''));
	if ($name !== '') {
		return $name;
	}
	$display = trim((string)($pcProject['displayName'] ?? ''));
	if ($display !== '') {
		return $display;
	}
	return (string)$l->t('Project #%s', [$pid]);
};

$groups = [];
$groupOrder = [];
$listedIds = [];
foreach ($projects as $pcProject) {
	if (!is_array($pcProject)) {
		continue;
	}
	$pid = (string)($pcProject['id'] ?? '');
	if ($pid === '') {
		continue;
	}
	$label = $azcResolveProjectOptionLabel($pcProject, $pid);
	$customer = trim((string)($pcProject['customerName'] ?? ''));
	if ($customer === '') {
		$customer = $l->t('No customer');
	}
	if (!isset($groups[$customer])) {
		$groups[$customer] = [];
		$groupOrder[] = $customer;
	}
	$groups[$customer][] = ['id' => $pid, 'label' => $label];
	$listedIds[$pid] = true;
}
?>
<option value=""><?php p($l->t('No project — just track my time')); ?></option>
<?php foreach ($groupOrder as $customer): ?>
	<optgroup label="<?php p($customer); ?>">
		<?php foreach ($groups[$customer] as $proj): ?>
			<option value="<?php p($proj['id']); ?>"<?php if ($selectedId !== '' && $selectedId === $proj['id']) {
				p(' selected');
			} ?>><?php p($proj['label']); ?></option>
		<?php endforeach; ?>
	</optgroup>
<?php endforeach; ?>
<?php if ($selectedId !== '' && !isset($listedIds[$selectedId])): ?>
	<option value="<?php p($selectedId); ?>" selected><?php p($l->t('Linked project #%s (no longer in your list)', [$selectedId])); ?></option>
<?php endif; ?>
