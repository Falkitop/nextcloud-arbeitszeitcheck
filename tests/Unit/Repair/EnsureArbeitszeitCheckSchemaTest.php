<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Repair;

use OCA\ArbeitszeitCheck\Migration\ArbeitszeitCheckTableCatalog;
use OCA\ArbeitszeitCheck\Repair\EnsureArbeitszeitCheckSchema;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;

final class EnsureArbeitszeitCheckSchemaTest extends TestCase
{
	public function testSucceedsWhenAllTablesExist(): void
	{
		$connection = $this->createMock(IDBConnection::class);
		$connection->method('tableExists')->willReturn(true);
		$output = $this->createMock(IOutput::class);
		$output->expects(self::once())->method('info');

		$step = new EnsureArbeitszeitCheckSchema($connection);
		$step->run($output);
		self::assertSame(25, count(ArbeitszeitCheckTableCatalog::TABLES));
		self::assertTrue(ArbeitszeitCheckTableCatalog::isLegacyDroppedTable('at_absence_calendar'));
	}
}
