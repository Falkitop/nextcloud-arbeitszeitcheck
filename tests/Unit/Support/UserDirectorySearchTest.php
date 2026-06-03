<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Support;

use OCA\ArbeitszeitCheck\Support\UserDirectorySearch;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the directory search helper that fixes issue #14
 * (team member selection hid most of the directory because it matched
 * user ids only, never display names).
 */
class UserDirectorySearchTest extends TestCase
{
	private function user(string $uid, string $displayName, bool $enabled = true): IUser
	{
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		$user->method('getDisplayName')->willReturn($displayName);
		$user->method('isEnabled')->willReturn($enabled);
		return $user;
	}

	public function testMatchesByDisplayNameWhenUserIdSearchMissesEverything(): void
	{
		$byName = $this->user('a1b2-uuid', 'Max Mustermann');

		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('search')->willReturn([]); // id search finds nothing
		$userManager->method('searchDisplayName')->willReturn([$byName]);

		$result = UserDirectorySearch::searchByIdOrName($userManager, 'max', 20);

		$this->assertCount(1, $result['users']);
		$this->assertSame('a1b2-uuid', $result['users'][0]->getUID());
		$this->assertFalse($result['truncated']);
	}

	public function testMergesAndDeduplicatesIdAndNameMatches(): void
	{
		$shared = $this->user('alice', 'Alice Anders');
		$onlyName = $this->user('uuid-bob', 'Bob Bauer');

		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('search')->willReturn([$shared]);
		$userManager->method('searchDisplayName')->willReturn([$shared, $onlyName]);

		$result = UserDirectorySearch::searchByIdOrName($userManager, 'a', 20);

		$uids = array_map(static fn (IUser $u) => $u->getUID(), $result['users']);
		$this->assertSame(['alice', 'uuid-bob'], $uids); // deduped + sorted by display name
	}

	public function testDropsDisabledAccountsWhenEnabledOnly(): void
	{
		$enabled = $this->user('alice', 'Alice');
		$disabled = $this->user('bob', 'Bob', false);

		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('search')->willReturn([$enabled, $disabled]);
		$userManager->method('searchDisplayName')->willReturn([]);

		$result = UserDirectorySearch::searchByIdOrName($userManager, 'b', 20, 0, true);

		$this->assertCount(1, $result['users']);
		$this->assertSame('alice', $result['users'][0]->getUID());
	}

	public function testKeepsDisabledAccountsWhenNotEnabledOnly(): void
	{
		$enabled = $this->user('alice', 'Alice');
		$disabled = $this->user('bob', 'Bob', false);

		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('search')->willReturn([$enabled, $disabled]);
		$userManager->method('searchDisplayName')->willReturn([]);

		$result = UserDirectorySearch::searchByIdOrName($userManager, 'b', 20, 0, false);

		$this->assertCount(2, $result['users']);
	}

	public function testExcludesAssignedUserIds(): void
	{
		$assigned = $this->user('alice', 'Alice');
		$available = $this->user('alan', 'Alan');

		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('search')->willReturn([$assigned, $available]);
		$userManager->method('searchDisplayName')->willReturn([]);

		$result = UserDirectorySearch::searchByIdOrName($userManager, 'al', 20, 0, true, ['alice']);

		$this->assertCount(1, $result['users']);
		$this->assertSame('alan', $result['users'][0]->getUID());
	}

	public function testTruncatedWhenMoreMatchesThanLimit(): void
	{
		$users = [];
		for ($i = 0; $i < 5; $i++) {
			$users[] = $this->user('user' . $i, 'User ' . $i);
		}

		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('search')->willReturn($users);
		$userManager->method('searchDisplayName')->willReturn([]);

		$result = UserDirectorySearch::searchByIdOrName($userManager, 'user', 3);

		$this->assertCount(3, $result['users']);
		$this->assertTrue($result['truncated']);
	}

	public function testSortsByDisplayNameCaseInsensitive(): void
	{
		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('search')->willReturn([
			$this->user('u1', 'zoe'),
			$this->user('u2', 'Anna'),
			$this->user('u3', 'mike'),
		]);
		$userManager->method('searchDisplayName')->willReturn([]);

		$result = UserDirectorySearch::searchByIdOrName($userManager, 'x', 20);

		$names = array_map(static fn (IUser $u) => $u->getDisplayName(), $result['users']);
		$this->assertSame(['Anna', 'mike', 'zoe'], $names);
	}

	public function testMergeUniqueDeduplicatesAcrossBatches(): void
	{
		$alice = $this->user('alice', 'Alice');
		$bob = $this->user('bob', 'Bob');

		$merged = UserDirectorySearch::mergeUnique([$alice, $bob], [$alice]);

		$this->assertCount(2, $merged);
		$uids = array_map(static fn (IUser $u) => $u->getUID(), $merged);
		$this->assertSame(['alice', 'bob'], $uids);
	}
}
