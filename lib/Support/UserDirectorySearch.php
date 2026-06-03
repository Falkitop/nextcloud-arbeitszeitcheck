<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Support;

use OCA\ArbeitszeitCheck\Constants;
use OCP\IUser;
use OCP\IUserManager;

/**
 * Directory search that matches a person by BOTH their user id and their
 * display name.
 *
 * Background (issue #14 "Mitgliederauswahl unvollständig"):
 * {@see IUserManager::search()} only matches the *user id*. On a great many
 * Nextcloud instances the user id is an email address, an employee number, an
 * LDAP DN fragment or a random UUID, while administrators search by the
 * person's *name*. Querying the id backend alone therefore silently hides most
 * of the directory — the reported symptom where members "are not shown at all"
 * when building teams. Merging {@see IUserManager::search()} with
 * {@see IUserManager::searchDisplayName()} makes "type a name, find the
 * person" work regardless of how the user id is shaped.
 *
 * This is a pure helper (no state, no DI) so it can be shared by every
 * controller that exposes a people picker without touching their constructors.
 */
final class UserDirectorySearch
{
	private function __construct()
	{
	}

	/**
	 * Search the directory by user id OR display name, merged, de-duplicated,
	 * sorted by display name and paged.
	 *
	 * @param IUserManager   $userManager
	 * @param string         $pattern         Search term (caller must clamp its length).
	 * @param int            $limit           Maximum users to return in the page (>= 1).
	 * @param int            $offset          Page offset (>= 0).
	 * @param bool           $enabledOnly     Drop disabled accounts (pickers) or keep them (management lists).
	 * @param list<string>   $excludeUserIds  User ids to omit (e.g. people already assigned to the team).
	 *
	 * @return array{users: list<IUser>, truncated: bool}
	 *         `truncated` is true when more matches exist than the returned page.
	 */
	public static function searchByIdOrName(
		IUserManager $userManager,
		string $pattern,
		int $limit,
		int $offset = 0,
		bool $enabledOnly = true,
		array $excludeUserIds = [],
	): array {
		$limit = max(1, $limit);
		$offset = max(0, $offset);

		$excludeLookup = [];
		foreach ($excludeUserIds as $id) {
			$key = trim((string)$id);
			if ($key !== '') {
				$excludeLookup[$key] = true;
			}
		}

		// Over-fetch from each backend so that de-duplication, the enabled
		// filter, the exclude list and paging cannot starve the final page
		// below $limit, while staying bounded for DoS safety on very large
		// directories.
		$want = $offset + $limit;
		$fetchTarget = ($want + count($excludeLookup)) * 2;
		$fetchCap = (int)min(max($fetchTarget, $want + 25), Constants::MAX_LIST_LIMIT);

		$byId = (array)$userManager->search($pattern, $fetchCap, 0);
		$byDisplayName = (array)$userManager->searchDisplayName($pattern, $fetchCap, 0);
		$backendCapped = count($byId) >= $fetchCap || count($byDisplayName) >= $fetchCap;

		/** @var array<string, IUser> $merged */
		$merged = [];
		foreach ([$byId, $byDisplayName] as $batch) {
			foreach ($batch as $user) {
				if (!$user instanceof IUser) {
					continue;
				}
				if ($enabledOnly && !$user->isEnabled()) {
					continue;
				}
				$uid = (string)$user->getUID();
				if ($uid === '' || isset($merged[$uid]) || isset($excludeLookup[$uid])) {
					continue;
				}
				$merged[$uid] = $user;
			}
		}

		$users = array_values($merged);
		usort($users, static function (IUser $a, IUser $b): int {
			$byName = strcasecmp((string)$a->getDisplayName(), (string)$b->getDisplayName());
			if ($byName !== 0) {
				return $byName;
			}
			return strcasecmp((string)$a->getUID(), (string)$b->getUID());
		});

		$total = count($users);
		$page = array_slice($users, $offset, $limit);
		$truncated = $backendCapped || $total > ($offset + $limit);

		return [
			'users' => $page,
			'truncated' => $truncated,
		];
	}

	/**
	 * Merge several IUser batches into a single list de-duplicated by user id,
	 * preserving first-seen order. Used by scan-and-paginate endpoints that do
	 * their own filtering/sorting but still need to look up people by id OR
	 * display name.
	 *
	 * @param list<IUser> ...$batches
	 * @return list<IUser>
	 */
	public static function mergeUnique(array ...$batches): array
	{
		/** @var array<string, IUser> $merged */
		$merged = [];
		foreach ($batches as $batch) {
			if (!is_array($batch)) {
				continue;
			}
			foreach ($batch as $user) {
				if (!$user instanceof IUser) {
					continue;
				}
				$uid = (string)$user->getUID();
				if ($uid === '' || isset($merged[$uid])) {
					continue;
				}
				$merged[$uid] = $user;
			}
		}
		return array_values($merged);
	}
}
