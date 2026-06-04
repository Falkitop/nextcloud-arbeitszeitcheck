<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Support;

use OCA\ArbeitszeitCheck\Support\SchemaHealth;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

final class SchemaHealthTest extends TestCase
{
	public function testAssessReadyWhenAllTablesExist(): void
	{
		$connection = $this->createMock(IDBConnection::class);
		$connection->method('tableExists')->willReturn(true);

		$result = SchemaHealth::assess($connection);
		self::assertTrue($result['ready']);
		self::assertFalse($result['show_banner']);
		self::assertSame(0, $result['missing_count']);
	}

	public function testAssessReportsMissingTables(): void
	{
		$connection = $this->createMock(IDBConnection::class);
		$connection->method('tableExists')->willReturnCallback(
			static fn (string $table): bool => $table !== 'at_settings',
		);

		$result = SchemaHealth::assess($connection);
		self::assertFalse($result['ready']);
		self::assertTrue($result['show_banner']);
		self::assertContains('at_settings', $result['missing_tables']);
	}
}
