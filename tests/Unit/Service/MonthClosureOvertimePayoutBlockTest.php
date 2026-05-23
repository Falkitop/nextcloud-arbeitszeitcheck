<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service;

use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Db\AbsenceMapper;
use OCA\ArbeitszeitCheck\Db\AuditLogMapper;
use OCA\ArbeitszeitCheck\Db\MonthClosureMapper;
use OCA\ArbeitszeitCheck\Db\MonthClosureRevisionMapper;
use OCA\ArbeitszeitCheck\Db\OvertimePayoutMapper;
use OCA\ArbeitszeitCheck\Db\TimeEntryMapper;
use OCA\ArbeitszeitCheck\Service\MonthClosureService;
use OCA\ArbeitszeitCheck\Service\OvertimeBankService;
use OCA\ArbeitszeitCheck\Service\PermissionService;
use OCA\ArbeitszeitCheck\Service\ReportingService;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class MonthClosureOvertimePayoutBlockTest extends TestCase
{
	public function testPendingOvertimePayoutBlocksWhenConfigEnabled(): void
	{
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(static function (string $app, string $key, $default) {
			if ($key === Constants::CONFIG_OVERTIME_BLOCK_MONTH_CLOSURE_PENDING_PAYOUT) {
				return '1';
			}

			return $default;
		});

		$bank = $this->createMock(OvertimeBankService::class);
		$bank->method('isEnabled')->willReturn(true);
		$bank->method('getMonthEndSnapshot')->willReturn([
			'payout_eligible_hours' => 5.0,
			'effective_balance' => 105.0,
			'raw_balance' => 105.0,
			'bank_max_hours' => 100.0,
		]);

		$payoutMapper = $this->createMock(OvertimePayoutMapper::class);
		$payoutMapper->method('findByUserAndMonth')->willReturn(null);

		$service = new MonthClosureService(
			$this->createMock(MonthClosureMapper::class),
			$this->createMock(MonthClosureRevisionMapper::class),
			$this->createMock(ReportingService::class),
			$this->createMock(TimeEntryMapper::class),
			$this->createMock(AbsenceMapper::class),
			$this->createMock(AuditLogMapper::class),
			$this->createMock(IDBConnection::class),
			$config,
			$this->createMock(IUserManager::class),
			$this->createMock(LoggerInterface::class),
			$this->createMock(PermissionService::class),
			$bank,
			$payoutMapper,
		);

		$this->assertSame('pending_overtime_payout', $service->getMonthFinalizeBlockReason('user1', 2025, 3));
	}

	public function testNoBlockWhenPayoutAlreadyRecorded(): void
	{
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(static function (string $app, string $key, $default) {
			if ($key === Constants::CONFIG_OVERTIME_BLOCK_MONTH_CLOSURE_PENDING_PAYOUT) {
				return '1';
			}

			return $default;
		});

		$bank = $this->createMock(OvertimeBankService::class);
		$bank->method('isEnabled')->willReturn(true);

		$payoutMapper = $this->createMock(OvertimePayoutMapper::class);
		$payoutMapper->method('findByUserAndMonth')->willReturn(new \OCA\ArbeitszeitCheck\Db\OvertimePayout());

		$service = new MonthClosureService(
			$this->createMock(MonthClosureMapper::class),
			$this->createMock(MonthClosureRevisionMapper::class),
			$this->createMock(ReportingService::class),
			$this->createMock(TimeEntryMapper::class),
			$this->createMock(AbsenceMapper::class),
			$this->createMock(AuditLogMapper::class),
			$this->createMock(IDBConnection::class),
			$config,
			$this->createMock(IUserManager::class),
			$this->createMock(LoggerInterface::class),
			$this->createMock(PermissionService::class),
			$bank,
			$payoutMapper,
		);

		$this->assertNull($service->getMonthFinalizeBlockReason('user1', 2025, 3));
	}
}
