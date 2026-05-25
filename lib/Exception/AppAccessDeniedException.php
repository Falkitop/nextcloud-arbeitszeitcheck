<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Exception;

/**
 * Thrown when a user is authenticated but not allowed to use ArbeitszeitCheck.
 */
class AppAccessDeniedException extends \Exception
{
	public function __construct(
		private readonly string $denialReason = 'access_denied',
		int $code = 0,
		?\Throwable $previous = null,
	) {
		parent::__construct('app_access_denied', $code, $previous);
	}

	public function getDenialReason(): string
	{
		return $this->denialReason;
	}
}
