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
		$catalog = ArbeitszeitCheckTableCatalog::TABLES;
		self::assertContains('azc_license_state', $catalog);
		self::assertContains('at_kiosk_terminals', $catalog);
		self::assertGreaterThanOrEqual(25, count($catalog));
		self::assertTrue(ArbeitszeitCheckTableCatalog::isLegacyDroppedTable('at_absence_calendar'));
	}
}
