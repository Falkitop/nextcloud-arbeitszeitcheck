<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Support;

use OCA\ArbeitszeitCheck\Support\OpeningBalanceYearValidator;
use PHPUnit\Framework\TestCase;

class OpeningBalanceYearValidatorTest extends TestCase
{
	public function testParseAcceptsFourDigitYearInRange(): void
	{
		[$year, $err] = OpeningBalanceYearValidator::parse('2026');
		self::assertNull($err);
		self::assertSame(2026, $year);
	}

	public function testParseRejectsTooManyDigits(): void
	{
		[$year, $err] = OpeningBalanceYearValidator::parse('20261');
		self::assertNull($year);
		self::assertSame('invalid', $err);
	}

	public function testParseRejectsOutOfRange(): void
	{
		[$year, $err] = OpeningBalanceYearValidator::parse('1999');
		self::assertNull($year);
		self::assertSame('range', $err);
	}
}
