<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2026 Alexander Mäule
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\Support;

use OCA\ArbeitszeitCheck\Support\StrictYmdDates;
use PHPUnit\Framework\TestCase;

class StrictYmdDatesTest extends TestCase
{
	public function testParsesValidIsoDay(): void
	{
		$d = StrictYmdDates::parseRequired('2026-06-15');
		self::assertNotNull($d);
		self::assertSame('2026-06-15', $d->format('Y-m-d'));
	}

	public function testRejectsLocaleFormattedDate(): void
	{
		self::assertNull(StrictYmdDates::parseRequired('15.06.2026'));
	}

	public function testRejectsOverflowCalendarDay(): void
	{
		self::assertNull(StrictYmdDates::parseRequired('2026-02-30'));
	}

	public function testRejectsEmpty(): void
	{
		self::assertNull(StrictYmdDates::parseRequired(''));
		self::assertNull(StrictYmdDates::parseRequired('   '));
	}
}
