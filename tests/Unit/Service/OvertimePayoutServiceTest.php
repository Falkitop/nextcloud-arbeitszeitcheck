<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service;

use OCA\ArbeitszeitCheck\Db\AuditLogMapper;
use OCA\ArbeitszeitCheck\Db\OvertimePayout;
use OCA\ArbeitszeitCheck\Db\OvertimePayoutMapper;
use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Service\NotificationService;
use OCA\ArbeitszeitCheck\Service\OvertimeBankService;
use OCA\ArbeitszeitCheck\Service\OvertimePayoutMailService;
use OCA\ArbeitszeitCheck\Service\OvertimePayoutService;
use OCA\ArbeitszeitCheck\Service\PermissionService;
use OCA\ArbeitszeitCheck\Service\UserOvertimeSettingsService;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;

class OvertimePayoutServiceTest extends TestCase
{
	private function createService(
		OvertimeBankService $bank,
		OvertimePayoutMapper $payoutMapper,
		?AuditLogMapper $audit = null,
		?IUserManager $userManager = null,
	): OvertimePayoutService {
		return new OvertimePayoutService(
			$bank,
			$payoutMapper,
			$audit ?? $this->createMock(AuditLogMapper::class),
			$userManager ?? $this->createMock(IUserManager::class),
			$this->createMock(UserOvertimeSettingsService::class),
			$this->createMock(PermissionService::class),
			$this->createMock(NotificationService::class),
			$this->createMock(OvertimePayoutMailService::class),
			$this->createMock(IConfig::class),
		);
	}

	public function testProcessPayoutSkipsWhenAlreadyPaid(): void
	{
		$bank = $this->createMock(OvertimeBankService::class);
		$bank->method('isEnabled')->willReturn(true);

		$payoutMapper = $this->createMock(OvertimePayoutMapper::class);
		$payoutMapper->method('existsForUserAndMonth')->willReturn(true);

		$user = $this->createMock(IUser::class);
		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('get')->willReturn($user);

		$service = $this->createService($bank, $payoutMapper, null, $userManager);

		$result = $service->processPayout('user1', 2020, 6, 'admin', false);
		$this->assertSame('skipped_already_paid', $result['action']);
	}

	public function testProcessPayoutRecordsEligibleHours(): void
	{
		$bank = $this->createMock(OvertimeBankService::class);
		$bank->method('isEnabled')->willReturn(true);
		$bank->method('getMonthEndSnapshot')->willReturn([
			'raw_balance' => 110.0,
			'effective_balance' => 110.0,
			'payout_eligible_hours' => 10.0,
			'bank_max_hours' => 100.0,
			'total_payouts_before_month' => 0.0,
		]);

		$payoutMapper = $this->createMock(OvertimePayoutMapper::class);
		$payoutMapper->method('existsForUserAndMonth')->willReturn(false);
		$saved = new OvertimePayout();
		$saved->setId(1);
		$saved->setCalendarYear(2020);
		$saved->setCalendarMonth(6);
		$saved->setHoursPaid(10.0);
		$saved->setEffectiveBalanceBefore(110.0);
		$saved->setEffectiveBalanceAfter(100.0);
		$payoutMapper->expects($this->once())->method('insertPayout')->willReturn($saved);

		$user = $this->createMock(IUser::class);
		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('get')->willReturn($user);

		$audit = $this->createMock(AuditLogMapper::class);
		$audit->expects($this->once())->method('logAction');

		$notify = $this->createMock(NotificationService::class);
		$notify->expects($this->once())->method('notifyOvertimePayout');

		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(static function (string $app, string $key, string $default) {
			if ($key === Constants::CONFIG_OVERTIME_PAYOUT_NOTIFY_IN_APP) {
				return '1';
			}

			return $default;
		});

		$service = new OvertimePayoutService(
			$bank,
			$payoutMapper,
			$audit,
			$userManager,
			$this->createMock(UserOvertimeSettingsService::class),
			$this->createMock(PermissionService::class),
			$notify,
			$this->createMock(OvertimePayoutMailService::class),
			$config,
		);

		$result = $service->processPayout('user1', 2020, 6, 'admin', false);
		$this->assertSame('paid', $result['action']);
		$this->assertSame(10.0, $result['payout']['hours_paid']);
	}

	public function testProcessPayoutRejectsFutureMonth(): void
	{
		$bank = $this->createMock(OvertimeBankService::class);
		$bank->method('isEnabled')->willReturn(true);

		$service = $this->createService(
			$bank,
			$this->createMock(OvertimePayoutMapper::class),
		);

		$futureMonth = (int)(new \DateTime('+2 months'))->format('n');
		$futureYear = (int)(new \DateTime('+2 months'))->format('Y');

		$this->expectException(\InvalidArgumentException::class);
		$service->processPayout('user1', $futureYear, $futureMonth, 'admin', false);
	}
}
