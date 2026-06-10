<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Middleware;

use Exception;

class ClientLicenseRequiredException extends Exception
{
	public function __construct(
		private readonly string $reason,
	) {
		parent::__construct('LICENSE_REQUIRED:' . $reason);
	}

	public function getReason(): string
	{
		return $this->reason;
	}
}
