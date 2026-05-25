<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Service;

use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Db\AbsenceMapper;
use OCP\IConfig;

/**
 * Shared navigation visibility flags for page templates.
 */
final class NavigationFlagsService
{
	public function __construct(
		private readonly AbsenceMapper $absenceMapper,
		private readonly PermissionService $permissionService,
		private readonly IConfig $config,
	) {
	}

	/**
	 * @return array{
	 *   showSubstitutionLink: bool,
	 *   showManagerLink: bool,
	 *   showReportsLink: bool,
	 *   showAdminNav: bool,
	 *   monthClosureEnabled: bool
	 * }
	 */
	public function forUser(string $userId): array
	{
		$showSubstitutionLink = false;
		$showManagerLink = false;
		$showReportsLink = false;
		$showAdminNav = false;

		try {
			$pending = $this->absenceMapper->findSubstitutePendingForUser($userId, 1, 0);
			$showSubstitutionLink = \is_array($pending) && \count($pending) > 0;
		} catch (\Throwable) {
			$showSubstitutionLink = false;
		}

		try {
			$canAccessManagerDashboard = $this->permissionService->canAccessManagerDashboard($userId);
			$isAdmin = $this->permissionService->isAdmin($userId);

			$showManagerLink = $canAccessManagerDashboard;
			$showReportsLink = $canAccessManagerDashboard || $isAdmin;
			$showAdminNav = $isAdmin;
		} catch (\Throwable) {
			$showManagerLink = false;
			$showReportsLink = false;
			$showAdminNav = false;
		}

		return [
			'showSubstitutionLink' => $showSubstitutionLink,
			'showManagerLink' => $showManagerLink,
			'showReportsLink' => $showReportsLink,
			'showAdminNav' => $showAdminNav,
			'monthClosureEnabled' => $this->config->getAppValue(
				'arbeitszeitcheck',
				Constants::CONFIG_MONTH_CLOSURE_ENABLED,
				'0'
			) === '1',
		];
	}

	/**
	 * Admin-only pages: manager/reports links still apply; substitution hidden unless pending.
	 *
	 * @return array{
	 *   showSubstitutionLink: bool,
	 *   showManagerLink: bool,
	 *   showReportsLink: bool,
	 *   showAdminNav: bool,
	 *   monthClosureEnabled: bool
	 * }
	 */
	public function forAdminUser(string $userId): array
	{
		$flags = $this->forUser($userId);
		$flags['showAdminNav'] = true;

		return $flags;
	}
}
