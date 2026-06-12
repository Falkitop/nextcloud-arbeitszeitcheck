<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Service;

use OCA\ArbeitszeitCheck\Util\TemplateL10n;
use OCP\IL10N;
use OCP\IURLGenerator;

/**
 * JSON config for the dashboard desklet workspace (NC home + optional app embed).
 */
class DashboardDeskletConfigService
{
	public function __construct(
		private readonly IURLGenerator $urlGenerator,
		private readonly PermissionService $permissionService,
		private readonly IL10N $l10n,
	) {
	}

	/**
	 * @return array<string, mixed>
	 */
	public function buildForUser(string $userId): array
	{
		$isManager = $this->permissionService->canAccessManagerDashboard($userId);
		$isAdmin = $this->permissionService->isAdmin($userId);

		return [
			'employeeDataUrl' => $this->urlGenerator->linkToRoute('arbeitszeitcheck.dashboard_widget.employeeData'),
			'managerDataUrl' => $this->urlGenerator->linkToRoute('arbeitszeitcheck.dashboard_widget.managerData'),
			'adminDataUrl' => $this->urlGenerator->linkToRoute('arbeitszeitcheck.dashboard_widget.adminData'),
			'clockInUrl' => $this->urlGenerator->linkToRoute('arbeitszeitcheck.dashboard_widget.clockIn'),
			'startBreakUrl' => $this->urlGenerator->linkToRoute('arbeitszeitcheck.dashboard_widget.startBreak'),
			'endBreakUrl' => $this->urlGenerator->linkToRoute('arbeitszeitcheck.dashboard_widget.endBreak'),
			'clockOutUrl' => $this->urlGenerator->linkToRoute('arbeitszeitcheck.dashboard_widget.clockOut'),
			'dashboardUrl' => $this->urlGenerator->linkToRoute('arbeitszeitcheck.page.dashboard'),
			'timeEntriesUrl' => $this->urlGenerator->linkToRoute('arbeitszeitcheck.page.timeEntries'),
			'isManager' => $isManager,
			'isAdmin' => $isAdmin,
			'l10n' => $this->buildL10n(),
		];
	}

	/**
	 * @return array<string, string>
	 */
	private function buildL10n(): array
	{
		$l = $this->l10n;

		return [
			'working' => TemplateL10n::translate($l, 'Working'),
			'onBreak' => TemplateL10n::translate($l, 'On Break'),
			'paused' => TemplateL10n::translate($l, 'Paused'),
			'clockedOut' => TemplateL10n::translate($l, 'Clocked Out'),
			'statusLine' => TemplateL10n::translate($l, 'Status: %1$s'),
			'workedToday' => TemplateL10n::translate($l, 'Worked today'),
			'sessionDuration' => TemplateL10n::translate($l, 'Session'),
			'clockIn' => TemplateL10n::translate($l, 'Clock In'),
			'startBreak' => TemplateL10n::translate($l, 'Start Break'),
			'endBreak' => TemplateL10n::translate($l, 'End Break'),
			'clockOut' => TemplateL10n::translate($l, 'Clock Out'),
			'openDashboard' => TemplateL10n::translate($l, 'Open full dashboard'),
			'openTimeEntries' => TemplateL10n::translate($l, 'Open time entries'),
			'teamOverview' => TemplateL10n::translate($l, 'Team overview'),
			'companyOverview' => TemplateL10n::translate($l, 'Company overview'),
			'lastUpdated' => TemplateL10n::translate($l, 'Last updated: %1$s'),
			'actionFailed' => TemplateL10n::translate($l, 'Action failed'),
			'actionDone' => TemplateL10n::translate($l, '%1$s successful'),
			'networkError' => TemplateL10n::translate($l, 'Could not load status. Please check your connection.'),
			'sessionExpired' => TemplateL10n::translate($l, 'Your session has expired. Please refresh the page and try again.'),
			'noTeamMembers' => TemplateL10n::translate($l, 'No team members found.'),
			'noUsersFound' => TemplateL10n::translate($l, 'No users found.'),
			'peopleRow' => TemplateL10n::translate($l, '%1$s: %2$s (%3$s h)'),
			'stampingDisabledTitle' => TemplateL10n::translate($l, 'Clock in/out is turned off for you'),
			'stampingDisabledBodyManual' => TemplateL10n::translate($l, 'Add your hours under Time entries in the app.'),
			'stampingDisabledBody' => TemplateL10n::translate($l, 'Contact HR if you need to record time.'),
			'stampingDisabledPausedBody' => TemplateL10n::translate($l, 'Finish the paused session on the dashboard, or contact your administrator.'),
			'deskletTitle' => TemplateL10n::translate($l, 'Quick time tracking'),
			'deskletLead' => TemplateL10n::translate($l, 'Clock in, take a break, or clock out from here.'),
		];
	}
}
