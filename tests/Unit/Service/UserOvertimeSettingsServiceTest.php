<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service;

use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Db\AuditLogMapper;
use OCA\ArbeitszeitCheck\Db\UserOvertimeYearBalance;
use OCA\ArbeitszeitCheck\Db\UserOvertimeYearBalanceMapper;
use OCA\ArbeitszeitCheck\Db\UserSettingsMapper;
use OCA\ArbeitszeitCheck\Service\UserOvertimeSettingsService;
use PHPUnit\Framework\TestCase;

class UserOvertimeSettingsServiceTest extends TestCase
{
	public function testSetTrackingFromAuditsWithNullEntityId(): void
	{
		$userSettings = $this->createMock(UserSettingsMapper::class);
		$userSettings->method('getStringSetting')
			->with('alice', Constants::SETTING_OVERTIME_TRACKING_FROM, '')
			->willReturn('');
		$userSettings->expects($this->once())
			->method('setSetting')
			->with('alice', Constants::SETTING_OVERTIME_TRACKING_FROM, '2025-06-01');

		$audit = $this->createMock(AuditLogMapper::class);
		$audit->expects($this->once())
			->method('logAction')
			->with(
				'alice',
				'user_overtime_tracking_from_updated',
				'user',
				null,
				['tracking_from' => null],
				['tracking_from' => '2025-06-01'],
				'admin'
			);

		$service = new UserOvertimeSettingsService(
			$userSettings,
			$this->createMock(UserOvertimeYearBalanceMapper::class),
			$audit,
		);

		$service->setTrackingFrom(
			'alice',
			new \DateTimeImmutable('2025-06-01'),
			'admin'
		);
	}

	public function testSetOpeningBalanceAuditsWithNullEntityId(): void
	{
		$balanceMapper = $this->createMock(UserOvertimeYearBalanceMapper::class);
		$balanceMapper->method('getOpeningBalanceHours')->willReturn(0.0);
		$entity = new UserOvertimeYearBalance();
		$entity->setOpeningBalanceHours(12.5);
		$balanceMapper->method('upsert')->willReturn($entity);

		$audit = $this->createMock(AuditLogMapper::class);
		$audit->expects($this->once())
			->method('logAction')
			->with(
				'alice',
				'user_overtime_opening_balance_updated',
				'user',
				null,
				['year' => 2026, 'opening_balance_hours' => 0.0],
				['year' => 2026, 'opening_balance_hours' => 12.5],
				'admin'
			);

		$service = new UserOvertimeSettingsService(
			$this->createMock(UserSettingsMapper::class),
			$balanceMapper,
			$audit,
		);

		$service->setOpeningBalance('alice', 2026, 12.5, 'admin');
	}
}
