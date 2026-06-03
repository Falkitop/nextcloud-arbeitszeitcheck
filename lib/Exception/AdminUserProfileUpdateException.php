<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Exception;

/**
 * Validation or business-rule failure while updating an employee profile.
 * Mapped to JSON by {@see \OCA\ArbeitszeitCheck\Controller\AdminController}.
 */
class AdminUserProfileUpdateException extends \RuntimeException
{
	/**
	 * @param array<string, string> $fieldErrors
	 */
	public function __construct(
		public readonly string $userMessage,
		public readonly int $httpStatus = 400,
		public readonly array $fieldErrors = [],
	) {
		parent::__construct($userMessage);
	}
}
