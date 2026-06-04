<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Service;

use OCA\ArbeitszeitCheck\AppInfo\Application;
use OCP\IL10N;

/**
 * Renders the dashboard desklet workspace partial for NC home embed + InitialState.
 */
class DashboardDeskletRenderService
{
	public function __construct(
		private readonly DashboardDeskletConfigService $deskletConfigService,
		private readonly IL10N $l10n,
	) {
	}

	/**
	 * @return array{config: array<string, mixed>, workspaceHtml: string}
	 */
	public function renderForUser(string $userId): array
	{
		$config = $this->deskletConfigService->buildForUser($userId);
		$template = new \OCP\Template(
			Application::APP_ID,
			'partials/dashboard-desklet-workspace',
			'blank',
		);
		$template->assign('deskletConfig', $config);
		$template->assign('l', $this->l10n);

		return [
			'config' => $config,
			'workspaceHtml' => $template->fetchPage(),
		];
	}
}
