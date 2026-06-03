<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Service;

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
		return [
			'working' => $this->l10n->t('Working'),
			'onBreak' => $this->l10n->t('On Break'),
			'paused' => $this->l10n->t('Paused'),
			'clockedOut' => $this->l10n->t('Clocked Out'),
			'statusLine' => $this->l10n->t('Status: %1$s'),
			'workedToday' => $this->l10n->t('Worked today'),
			'sessionDuration' => $this->l10n->t('Session'),
			'clockIn' => $this->l10n->t('Clock In'),
			'startBreak' => $this->l10n->t('Start Break'),
			'endBreak' => $this->l10n->t('End Break'),
			'clockOut' => $this->l10n->t('Clock Out'),
			'openDashboard' => $this->l10n->t('Open full dashboard'),
			'openTimeEntries' => $this->l10n->t('Open time entries'),
			'teamOverview' => $this->l10n->t('Team overview'),
			'companyOverview' => $this->l10n->t('Company overview'),
			'lastUpdated' => $this->l10n->t('Last updated: %1$s'),
			'actionFailed' => $this->l10n->t('Action failed'),
			'actionDone' => $this->l10n->t('%1$s successful'),
			'networkError' => $this->l10n->t('Could not load status. Please check your connection.'),
			'sessionExpired' => $this->l10n->t('Your session has expired. Please refresh the page and try again.'),
			'noTeamMembers' => $this->l10n->t('No team members found.'),
			'noUsersFound' => $this->l10n->t('No users found.'),
			'peopleRow' => $this->l10n->t('%1$s: %2$s (%3$s h)'),
			'stampingDisabledTitle' => $this->l10n->t('Clock in/out is turned off for you'),
			'stampingDisabledBodyManual' => $this->l10n->t('Add your hours under Time entries in the app.'),
			'stampingDisabledBody' => $this->l10n->t('Contact HR if you need to record time.'),
			'stampingDisabledPausedBody' => $this->l10n->t('Finish the paused session on the dashboard, or contact your administrator.'),
			'deskletTitle' => $this->l10n->t('Quick time tracking'),
			'deskletLead' => $this->l10n->t('Clock in, take a break, or clock out from here.'),
		];
	}
}
