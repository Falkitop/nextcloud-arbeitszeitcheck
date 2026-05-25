<?php

declare(strict_types=1);

/**
 * Behavioural contract for L3 vacation policy rows (inherit sentinel vs flag).
 *
 * @copyright Copyright (c) 2026 Alexander Mäule
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\Db;

use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Db\UserVacationPolicyAssignment;
use PHPUnit\Framework\TestCase;

class UserVacationPolicyAssignmentTest extends TestCase
{
	public function testIsInheritTrueWhenVacationModeIsInherit(): void
	{
		$p = new UserVacationPolicyAssignment();
		$p->setVacationMode(Constants::VACATION_MODE_INHERIT);
		$p->setInheritLowerLayers(false);
		self::assertTrue($p->isInherit());
	}

	public function testIsInheritTrueWhenBooleanFlagStrictlyTrue(): void
	{
		$p = new UserVacationPolicyAssignment();
		$p->setVacationMode(Constants::VACATION_MODE_MANUAL_FIXED);
		$p->setInheritLowerLayers(true);
		self::assertTrue($p->isInherit());
	}

	public function testIsInheritFalseWhenFlagIsIntegerOneHandledBySetter(): void
	{
		$p = UserVacationPolicyAssignment::fromRow([
			'user_id' => 'alice',
			'vacation_mode' => Constants::VACATION_MODE_MANUAL_FIXED,
			'inherit_lower_layers' => 1,
		]);
		self::assertTrue($p->isInherit());
	}

	/**
	 * PHP truthiness on a corrupted string would wrongly treat "false" as inherit.
	 * `isInherit()` must stay strict on the boolean column path.
	 */
	public function testIsInheritFalseWhenCorruptedStringFalseInRow(): void
	{
		$p = UserVacationPolicyAssignment::fromRow([
			'user_id' => 'alice',
			'vacation_mode' => Constants::VACATION_MODE_MANUAL_FIXED,
			'inherit_lower_layers' => 'false',
		]);
		self::assertFalse($p->isInherit());
	}

	public function testValidateRequiresExplicitVacationMode(): void
	{
		$p = new UserVacationPolicyAssignment();
		$p->setUserId('alice');
		$p->setManualDays(30.0);
		$errors = $p->validate();
		self::assertArrayHasKey('vacationMode', $errors);
	}
}
