<?php

declare(strict_types=1);

/**
 * Compact punch-clock workspace for the Nextcloud dashboard desklet and optional embeds.
 *
 * @var array $deskletConfig from DashboardDeskletConfigService::buildForUser()
 * @var \OCP\IL10N $l
 */

/** @var array $deskletConfig */
$config = is_array($deskletConfig ?? null) ? $deskletConfig : [];
$l10n = is_array($config['l10n'] ?? null) ? $config['l10n'] : [];
?>
<div class="dz-workspace" data-arbeitszeitcheck-desklet="1">
	<script type="application/json" id="dz-config"><?php print_unescaped(json_encode($config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)); ?></script>

	<header class="dz-header-section">
		<h2 class="dz-header__title"><?php p($l10n['deskletTitle'] ?? $l->t('Quick time tracking')); ?></h2>
		<p class="dz-header__desc"><?php p($l10n['deskletLead'] ?? $l->t('Clock in, take a break, or clock out from here.')); ?></p>
	</header>

	<p id="dz-live-status" class="dz-sr-only" aria-live="polite" aria-atomic="true"></p>
	<p id="dz-error" class="dz-error" role="alert" hidden></p>
	<p id="dz-feedback" class="dz-feedback" role="status" hidden></p>

	<section class="dz-section dz-status-section" id="dz-status-section" aria-labelledby="dz-status-section-title" aria-busy="false">
		<div class="dz-section__header">
			<h3 id="dz-status-section-title" class="dz-section__title"><?php p($l->t('Your status')); ?></h3>
		</div>

		<div id="dz-capture-notice" class="dz-capture-notice" hidden></div>

		<article class="dz-status-card" id="dz-status-card" data-status="clocked_out" aria-labelledby="dz-status-badge">
			<div class="dz-status-card__header">
				<div class="dz-status-title-wrap">
					<span id="dz-status-icon" class="dz-status-icon" aria-hidden="true"></span>
					<div class="dz-status-headlines">
						<p class="dz-status-eyebrow"><?php p($l->t('Current status')); ?></p>
						<p id="dz-status-badge" class="dz-status-badge" data-status="clocked_out"><?php p($l10n['clockedOut'] ?? $l->t('Clocked Out')); ?></p>
						<p id="dz-status-text" class="dz-status-text"></p>
					</div>
				</div>
			</div>
			<div class="dz-status-metrics" role="list">
				<div class="dz-metric" role="listitem">
					<p class="dz-metric__label"><?php p($l10n['workedToday'] ?? $l->t('Worked today')); ?></p>
					<p class="dz-metric__value" id="dz-worked-today">0.00</p>
				</div>
				<div class="dz-metric" role="listitem">
					<p class="dz-metric__label"><?php p($l10n['sessionDuration'] ?? $l->t('Session')); ?></p>
					<p class="dz-metric__value" id="dz-session-duration">00:00</p>
				</div>
			</div>
		</article>

		<div class="dz-button-row" role="group" aria-label="<?php p($l->t('Time tracking actions')); ?>">
			<button type="button" class="btn btn-primary" id="dz-clock-in"><?php p($l10n['clockIn'] ?? $l->t('Clock In')); ?></button>
			<button type="button" class="btn btn-secondary" id="dz-start-break"><?php p($l10n['startBreak'] ?? $l->t('Start Break')); ?></button>
			<button type="button" class="btn btn-secondary" id="dz-end-break"><?php p($l10n['endBreak'] ?? $l->t('End Break')); ?></button>
			<button type="button" class="btn btn-danger" id="dz-clock-out"><?php p($l10n['clockOut'] ?? $l->t('Clock Out')); ?></button>
		</div>

		<p id="dz-last-updated" class="dz-last-updated"></p>
	</section>

	<?php if (!empty($config['isManager'])): ?>
	<section class="dz-section" aria-labelledby="dz-team-section-title">
		<div class="dz-section__header">
			<h3 id="dz-team-section-title" class="dz-section__title"><?php p($l10n['teamOverview'] ?? $l->t('Team overview')); ?></h3>
		</div>
		<div id="dz-manager-list" class="dz-people-list" role="region" aria-live="polite"></div>
	</section>
	<?php endif; ?>

	<?php if (!empty($config['isAdmin'])): ?>
	<section class="dz-section" aria-labelledby="dz-admin-section-title">
		<div class="dz-section__header">
			<h3 id="dz-admin-section-title" class="dz-section__title"><?php p($l10n['companyOverview'] ?? $l->t('Company overview')); ?></h3>
		</div>
		<div id="dz-admin-list" class="dz-people-list" role="region" aria-live="polite"></div>
	</section>
	<?php endif; ?>

	<div class="dz-link-row">
		<a class="btn btn-secondary" href="<?php p($config['dashboardUrl'] ?? ''); ?>"><?php p($l10n['openDashboard'] ?? $l->t('Open full dashboard')); ?></a>
		<a class="btn btn-secondary" href="<?php p($config['timeEntriesUrl'] ?? ''); ?>"><?php p($l10n['openTimeEntries'] ?? $l->t('Open time entries')); ?></a>
	</div>
</div>
