<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service;

use OCA\ArbeitszeitCheck\Service\Kiosk\KioskTerminalService;
use OCA\ArbeitszeitCheck\Service\LicenseEnforcementService;
use OCA\ArbeitszeitCheck\Service\LicenseService;
use OCA\ArbeitszeitCheck\Service\MobileSeatService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class LicenseEnforcementServiceTest extends TestCase
{
	public function testEnforceCurrentLimitsDelegatesToSeatAndTerminalServices(): void
	{
		$license = $this->createMock(LicenseService::class);
		$license->method('getMobileSeatLimit')->willReturn(3);
		$license->method('getTerminalDeviceLimit')->willReturn(2);

		$seats = $this->createMock(MobileSeatService::class);
		$seats->expects(self::once())->method('trimToLimit')->with(3)->willReturn(2);

		$terminals = $this->createMock(KioskTerminalService::class);
		$terminals->expects(self::once())->method('trimActiveToDeviceLimit')->with(2)->willReturn(1);

		$service = new LicenseEnforcementService(
			$license,
			$seats,
			$terminals,
			$this->createMock(LoggerInterface::class),
		);

		$result = $service->enforceCurrentLimits();
		self::assertSame(2, $result['mobileSeatsRemoved']);
		self::assertSame(1, $result['terminalsRevoked']);
	}

	public function testClearAllCommercialStateWipesAssignmentsAndLicense(): void
	{
		$license = $this->createMock(LicenseService::class);
		$license->expects(self::once())->method('clearLicense');

		$seats = $this->createMock(MobileSeatService::class);
		$seats->expects(self::once())->method('removeAllSeats')->willReturn(4);

		$terminals = $this->createMock(KioskTerminalService::class);
		$terminals->expects(self::once())->method('revokeAllActiveAndPending')->willReturn(2);

		$service = new LicenseEnforcementService(
			$license,
			$seats,
			$terminals,
			$this->createMock(LoggerInterface::class),
		);

		$result = $service->clearAllCommercialState();
		self::assertSame(4, $result['mobileSeatsRemoved']);
		self::assertSame(2, $result['terminalsRevoked']);
	}
}
