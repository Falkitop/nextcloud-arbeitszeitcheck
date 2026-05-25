<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Controller;

use OCA\ArbeitszeitCheck\Service\NavigationFlagsService;
use OCP\IUserSession;

/**
 * Shared navigation visibility flags for templates (single source: NavigationFlagsService).
 *
 * Controllers using this trait must expose:
 *  - protected NavigationFlagsService $navigationFlags
 *  - protected IUserSession $userSession
 */
trait NavigationFlagsTrait
{
	/**
	 * @return array{
	 *   showSubstitutionLink: bool,
	 *   showManagerLink: bool,
	 *   showReportsLink: bool,
	 *   showAdminNav: bool,
	 *   monthClosureEnabled: bool
	 * }
	 */
	protected function getNavigationFlags(string $userId): array
	{
		return $this->navigationFlags->forUser($userId);
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
	protected function getNavigationFlagsForSession(): array
	{
		$user = $this->userSession->getUser();
		if ($user === null) {
			return $this->navigationFlags->emptyFlags();
		}

		return $this->getNavigationFlags($user->getUID());
	}
}
