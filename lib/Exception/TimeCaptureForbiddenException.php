<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Exception;

/**
 * Per-employee time capture method is disabled (stamping or manual entry).
 * HTTP layers map this to 403 Forbidden with a stable error_code for clients.
 */
class TimeCaptureForbiddenException extends BusinessRuleException
{
	public const CODE_CLOCK_STAMPING_DISABLED = 'clock_stamping_disabled';
	public const CODE_MANUAL_TIME_ENTRY_DISABLED = 'manual_time_entry_disabled';

	public function __construct(
		string $message,
		private readonly string $errorCode,
	) {
		parent::__construct($message);
	}

	public function getErrorCode(): string
	{
		return $this->errorCode;
	}
}
