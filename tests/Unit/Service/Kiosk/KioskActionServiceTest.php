<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service\Kiosk;

use OCA\ArbeitszeitCheck\Db\AuditLogMapper;
use OCA\ArbeitszeitCheck\Db\KioskSession;
use OCA\ArbeitszeitCheck\Db\KioskSessionMapper;
use OCA\ArbeitszeitCheck\Db\KioskTerminal;
use OCA\ArbeitszeitCheck\Service\Kiosk\KioskActionService;
use OCA\ArbeitszeitCheck\Service\Kiosk\KioskAuthService;
use OCA\ArbeitszeitCheck\Service\Kiosk\KioskException;
use OCA\ArbeitszeitCheck\Service\TimeTrackingService;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;

final class KioskActionServiceTest extends TestCase
{
	public function testPerformActionReChecksUserEligibility(): void
	{
		$terminal = new KioskTerminal();
		$terminal->setTerminalId('tid-1');

		$session = new KioskSession();
		$session->setId(9);
		$session->setUserId('alice');

		$auth = $this->createMock(KioskAuthService::class);
		$auth->method('validateSession')->willReturn($session);
		$auth->expects(self::once())
			->method('assertUserEligibleForAction')
			->with('alice')
			->willThrowException(new KioskException('KIOSK_USER_NOT_ALLOWED'));

		$service = new KioskActionService(
			$auth,
			$this->createMock(KioskSessionMapper::class),
			$this->createMock(TimeTrackingService::class),
			$this->createMock(AuditLogMapper::class),
			$this->createMock(IL10N::class),
		);

		$this->expectException(KioskException::class);
		$service->performAction($terminal, 'session-token', 'clock_in');
	}
}
