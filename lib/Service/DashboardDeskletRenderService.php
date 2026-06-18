<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Service;

use OCP\IL10N;

/**
 * Renders the dashboard desklet workspace partial for NC home embed + InitialState.
 */
class DashboardDeskletRenderService
{
	public function __construct(
		private readonly DashboardDeskletConfigService $deskletConfigService,
		private readonly IL10N $l10n,
		private readonly DashboardDeskletWorkspaceRenderer $workspaceRenderer,
	) {
	}

	/**
	 * @return array{config: array<string, mixed>, workspaceHtml: string}
	 */
	public function renderForUser(string $userId): array
	{
		$config = $this->deskletConfigService->buildForUser($userId);

		return [
			'config' => $config,
			'workspaceHtml' => $this->workspaceRenderer->render($config, $this->l10n),
		];
	}
}
