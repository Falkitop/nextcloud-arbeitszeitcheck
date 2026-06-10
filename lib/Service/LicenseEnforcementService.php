<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Service;

use OCA\ArbeitszeitCheck\Service\Kiosk\KioskTerminalService;
use Psr\Log\LoggerInterface;

/**
 * Enforces AZC2 seat/device limits after license changes.
 */
class LicenseEnforcementService
{
	public function __construct(
		private readonly LicenseService $licenseService,
		private readonly MobileSeatService $mobileSeatService,
		private readonly KioskTerminalService $kioskTerminalService,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * @return array{mobileSeatsRemoved: int, terminalsRevoked: int}
	 */
	public function enforceCurrentLimits(): array
	{
		$mobileLimit = $this->licenseService->getMobileSeatLimit();
		$terminalLimit = $this->licenseService->getTerminalDeviceLimit();

		$seatsRemoved = $this->mobileSeatService->trimToLimit($mobileLimit);
		$terminalsRevoked = $this->kioskTerminalService->trimActiveToDeviceLimit($terminalLimit);

		if ($seatsRemoved > 0 || $terminalsRevoked > 0) {
			$this->logger->info('AZC2 license limits enforced', [
				'mobileSeatsRemoved' => $seatsRemoved,
				'terminalsRevoked' => $terminalsRevoked,
				'mobileLimit' => $mobileLimit,
				'terminalLimit' => $terminalLimit,
			]);
		}

		return [
			'mobileSeatsRemoved' => $seatsRemoved,
			'terminalsRevoked' => $terminalsRevoked,
		];
	}

	/**
	 * @return array{mobileSeatsRemoved: int, terminalsRevoked: int}
	 */
	public function clearAllCommercialState(): array
	{
		$seatsRemoved = $this->mobileSeatService->removeAllSeats();
		$terminalsRevoked = $this->kioskTerminalService->revokeAllActiveAndPending();
		$this->licenseService->clearLicense();

		$this->logger->warning('AZC2 license and commercial assignments cleared by admin', [
			'mobileSeatsRemoved' => $seatsRemoved,
			'terminalsRevoked' => $terminalsRevoked,
		]);

		return [
			'mobileSeatsRemoved' => $seatsRemoved,
			'terminalsRevoked' => $terminalsRevoked,
		];
	}
}
