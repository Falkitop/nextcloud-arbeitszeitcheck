<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Service;

use OCA\ArbeitszeitCheck\AppInfo\Application;
use OCP\IL10N;

/**
 * Renders the dashboard desklet workspace partial via the legacy Nextcloud template API.
 */
class DashboardDeskletWorkspaceRenderer
{
	/**
	 * @param array<string, mixed> $config
	 */
	public function render(array $config, IL10N $l10n): string
	{
		$template = new \OCP\Template(
			Application::APP_ID,
			'partials/dashboard-desklet-workspace',
			'blank',
		);
		$template->assign('deskletConfig', $config);
		$template->assign('l', $l10n);

		return $template->fetchPage();
	}
}
